<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Who staged the page editor's current design draft.
 *
 * Load-bearing rather than bookkeeping: only a chat-staged draft is worth
 * carrying across a reload and showing an "Apply to site" gate for, because only
 * that one is an unanswered question. A rail draft belongs to a pane the
 * operator is looking at, so it lives and dies with the page they are on. The
 * two have to be told apart by something the browser cannot invent, which is why
 * the field holding this is `#[Locked]`.
 *
 * There used to be a third producer, the Design modal, and its transience was
 * the reason this enum was introduced: `unmountAction()` discarded the draft on
 * ANY modal close, which was right for the modal's own fields and catastrophic
 * for the assistant's. The modal is gone — the Site Styles rail replaced it —
 * and that discard rule went with it, since neither survivor is bounded by a
 * modal.
 */
enum DesignDraftSource: string
{
    /** A chat turn, whose result outlives a reload until the operator answers it. */
    case Chat = 'chat';

    /** The Site Styles rail, a persistent pane whose controls stay on screen. */
    case Rail = 'rail';
}
