<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Who wrote a line of an editor chat transcript. Mirrors the two roles the AI
 * SDK's `Message` understands, which is all the editor chat needs — tool calls
 * are an implementation detail of a single assistant turn and are never stored
 * as messages of their own.
 */
enum ChatRole: string
{
    case User = 'user';
    case Assistant = 'assistant';
}
