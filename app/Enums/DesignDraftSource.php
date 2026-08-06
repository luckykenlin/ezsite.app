<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Who staged the page editor's current design draft.
 *
 * Load-bearing rather than bookkeeping: `unmountAction()` discards the draft on
 * ANY modal close, which is right for the Design modal's own transient fields
 * and catastrophic for the assistant's — opening Page settings would silently
 * throw away a restyle the operator was still reviewing. The same distinction
 * also decides whether the chat rail shows its "Apply to site" gate, so the two
 * producers have to be told apart by something the browser cannot invent.
 */
enum DesignDraftSource: string
{
    /** The Design modal's live fields, which vanish with the modal. */
    case Modal = 'modal';

    /** A chat turn, whose result outlives every modal until answered. */
    case Chat = 'chat';
}
