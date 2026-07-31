<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Pages\CacheChatTurn;
use Generator;
use Illuminate\Http\Request;
use Illuminate\Support\Sleep;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Server-sent events for the page editor's chat: the reply as it is written.
 *
 * The turn itself runs on a queue worker ({@see \App\Jobs\ChatEditPageJob}),
 * which appends to {@see CacheChatTurn} as the provider streams. This route
 * tails that and forwards only what is new, so the operator watches the answer
 * arrive token by token instead of once a second.
 *
 * Why a route of its own rather than Livewire's `wire:stream`: that writes into
 * the body of a `POST /livewire/update` whose headers are not committed yet, so
 * anything going wrong mid-turn (it used to be PHP's `max_execution_time`) can
 * no longer produce a valid response — the editor rendered a blank modal. Here
 * the headers are committed with the first byte and this connection carries no
 * state, so losing it costs nothing: the worker finishes regardless, and the
 * editor's poll still picks the result up.
 *
 * Gated exactly like the canvas preview: an authenticated user AND an unguessable
 * token in a tenant-prefixed cache, so it only resolves inside the tenant that
 * wrote it. The token alone used to be the whole gate — the route group carries no
 * `auth` middleware — which left an unauthenticated endpoint streaming the
 * assistant's reply. 404 rather than 403, matching the unknown-token branch, so
 * neither confirms that a turn exists.
 */
final class PageEditorChatStreamController extends Controller
{
    /**
     * How long a single connection is held before the browser is asked to come
     * back. Comfortably longer than a turn, so reconnects are the exception.
     */
    private const int MAX_SECONDS = 180;

    /**
     * How often the cache is checked for new text. Paired with the worker's own
     * write interval: reading slower than it writes is what turns a stream of
     * tokens back into a handful of paragraphs.
     */
    private const int TAIL_INTERVAL_MS = 50;

    public function __invoke(Request $request, CacheChatTurn $turns): StreamedResponse
    {
        // check() rather than hasUser(), for the reason spelled out in
        // PageEditorPreviewController: nothing here resolves the guard first.
        abort_unless(auth()->check(), 404);

        $token = (string) $request->query('token');

        abort_if($turns->read($token) === null, 404);

        return response()->eventStream(fn (): Generator => $this->tail($token, $turns));
    }

    /**
     * Yield each new slice of the reply, and each new activity line, until the turn
     * finishes, the operator stops it (the entry is forgotten), or this connection
     * has been open long enough. Sending only the increment keeps the browser's job
     * to appending.
     *
     * Frames are JSON, NOT raw text. `eventStream()` writes `data: <message>` with
     * no encoding of its own, so a newline inside the message ends the SSE frame
     * early and the browser silently drops everything after it — which is most of
     * a markdown reply. JSON escapes newlines, and it also gives the two kinds of
     * frame somewhere to say which they are: `text` appends to the reply bubble,
     * `activity` appends to the progress list above it.
     *
     * @return Generator<int, string>
     */
    private function tail(string $token, CacheChatTurn $turns): Generator
    {
        $sent = 0;
        $announced = 0;

        // Bounded by reads rather than by a deadline. Between reads this loop
        // does nothing but sleep, so the two are equivalent in production — but
        // counting reads also terminates where the clock does not advance (a
        // frozen test clock, a faked Sleep), instead of spinning forever.
        $reads = intdiv(self::MAX_SECONDS * 1000, self::TAIL_INTERVAL_MS);

        for ($read = 0; $read < $reads; $read++) {
            $turn = $turns->read($token);

            if ($turn === null) {
                return;
            }

            // Activity before text, deliberately: the line announcing a tool call
            // is written before the model narrates what it did, and reversing them
            // on screen would read as the assistant talking about work it has not
            // started.
            foreach (array_slice($turn['activity'], $announced) as $line) {
                yield $this->frame('activity', $line);
            }

            $announced = count($turn['activity']);

            $reply = $turn['reply'];

            if (mb_strlen($reply) > $sent) {
                yield $this->frame('text', mb_substr($reply, $sent));

                $sent = mb_strlen($reply);
            }

            if ($turn['status'] === 'done') {
                return;
            }

            Sleep::for(self::TAIL_INTERVAL_MS)->milliseconds();
        }
    }

    /**
     * One frame, as the browser parses it — see {@see tail()} for why this is JSON.
     *
     * `JSON_THROW_ON_ERROR` is deliberate: a frame that cannot be encoded (invalid
     * UTF-8 mid-stream from a provider) must break this connection rather than
     * write a `data: false` the browser would take for a real message. The turn
     * itself survives — it is on the worker, and the editor's poll still lands it.
     */
    private function frame(string $kind, string $value): string
    {
        return json_encode(['t' => $kind, 'v' => $value], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
