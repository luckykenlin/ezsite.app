<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a chat turn is allowed to act — the composer's Edit/Ask toggle.
 *
 * Lovable's Plan mode and Base44's Discuss mode exist for the same reason this
 * does: "what should my pricing page say?" is a question, and a bot that
 * answers questions by rearranging the page teaches the operator not to ask.
 * Ask withholds the ENTIRE tool roster, so "guaranteed to change nothing" is
 * structural — there is no tool to call — rather than a promise in a prompt.
 */
enum ChatMode: string
{
    case Edit = 'edit';
    case Ask = 'ask';

    /**
     * Whether turns in this mode may mutate the page (call tools) at all.
     */
    public function edits(): bool
    {
        return $this === self::Edit;
    }
}
