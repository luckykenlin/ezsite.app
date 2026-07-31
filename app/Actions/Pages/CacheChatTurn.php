<?php

declare(strict_types=1);

namespace App\Actions\Pages;

use Illuminate\Support\Facades\Cache;

/**
 * The handoff between a chat turn running on the queue worker
 * ({@see \App\Jobs\ChatEditPageJob}) and the editor component polling for it.
 *
 * A turn cannot return through the request that started it: an LLM turn takes
 * tens of seconds, and holding a php-fpm worker open that long is what used to
 * kill the request on `max_execution_time` — as an uncatchable fatal, mid
 * `wire:stream`, which the editor rendered as a blank modal. So the worker
 * writes progress here and the component reads it, the same token-through-cache
 * pattern {@see CachePageEditorPreview} uses for the canvas preview.
 *
 * Deliberately plain arrays, so no `cache.serializable_classes` allowlisting is
 * needed. The panel and the worker both run inside the tenant (the job is
 * TenantAware), so CacheTenancyBootstrapper prefixes both sides identically and
 * a token is only resolvable inside the tenant that wrote it.
 */
final readonly class CacheChatTurn
{
    /**
     * Long enough to outlive any turn the job's own timeout allows, short enough
     * that an abandoned turn does not linger.
     */
    private const int TTL_MINUTES = 30;

    public static function key(string $token): string
    {
        return 'page-chat-turn:'.$token;
    }

    /**
     * Publish the turn's state.
     *
     * Called repeatedly while the provider streams (reply only, so the operator
     * watches the answer arrive instead of a spinner) and once at the end with
     * the resulting blocks. Carrying the blocks IS what marks it finished —
     * there is no result to apply until they exist, and a failed turn still
     * carries them, unchanged.
     *
     * @param  list<array{key: string, type: string, data: array<string, mixed>}>|null  $blocks  null while still running
     * @param  list<string>  $activity  what the turn has done so far, one line per tool
     *                                  call. Cumulative like `$reply`, and for the
     *                                  same reason: the stream forwards only the
     *                                  increment, so a browser that reconnects
     *                                  mid-turn still gets the lines it missed.
     */
    public function handle(string $token, string $reply, ?array $blocks = null, bool $failed = false, array $activity = []): void
    {
        Cache::put(self::key($token), [
            'status' => $blocks === null ? 'running' : 'done',
            'reply' => $reply,
            'blocks' => $blocks,
            'failed' => $failed,
            'activity' => $activity,
        ], now()->addMinutes(self::TTL_MINUTES));
    }

    /**
     * The turn as the editor consumes it. Normalised rather than trusted: this
     * comes back from an external store and its `blocks` go straight into the
     * editor's state and from there into the page, so a malformed entry is
     * dropped here instead of downstream.
     *
     * @return array{status: string, reply: string, blocks: list<array{key: string, type: string, data: array<string, mixed>}>|null, failed: bool, activity: list<string>}|null
     */
    public function read(string $token): ?array
    {
        $turn = Cache::get(self::key($token));

        if (! is_array($turn)) {
            return null;
        }

        return [
            'status' => ($turn['status'] ?? null) === 'done' ? 'done' : 'running',
            'reply' => is_string($turn['reply'] ?? null) ? $turn['reply'] : '',
            'blocks' => $this->normalisedBlocks($turn['blocks'] ?? null),
            'failed' => (bool) ($turn['failed'] ?? false),
            'activity' => $this->normalisedActivity($turn['activity'] ?? null),
        ];
    }

    public function forget(string $token): void
    {
        Cache::forget(self::key($token));
    }

    /**
     * The activity lines that are actually strings. They are rendered in the chat
     * rail, so anything else in the entry is dropped rather than reaching a view.
     *
     * @return list<string>
     */
    private function normalisedActivity(mixed $activity): array
    {
        if (! is_array($activity)) {
            return [];
        }

        return array_values(array_filter($activity, is_string(...)));
    }

    /**
     * Every entry that carries the editor's block shape; null when the turn has
     * not finished (there is no result yet) or stored nothing usable.
     *
     * @return list<array{key: string, type: string, data: array<string, mixed>}>|null
     */
    private function normalisedBlocks(mixed $blocks): ?array
    {
        if (! is_array($blocks)) {
            return null;
        }

        $normalised = [];

        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            if (! is_string($block['key'] ?? null)) {
                continue;
            }

            if (! is_string($block['type'] ?? null)) {
                continue;
            }

            $data = $block['data'] ?? null;
            $fields = [];

            foreach (is_array($data) ? $data : [] as $field => $value) {
                $fields[(string) $field] = $value;
            }

            $normalised[] = ['key' => $block['key'], 'type' => $block['type'], 'data' => $fields];
        }

        return $normalised;
    }
}
