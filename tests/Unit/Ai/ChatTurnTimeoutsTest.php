<?php

declare(strict_types=1);

use App\Actions\Pages\ChatEditPage;
use App\Ai\Agents\PageEditorAgent;
use App\Filament\Tenant\Resources\PageResource\Concerns\InteractsWithPageChat;
use App\Http\Controllers\PageEditorChatStreamController;
use App\Jobs\ChatEditPageJob;
use Illuminate\Queue\Attributes\Timeout as QueueTimeout;
use Laravel\Ai\Attributes\Timeout as AgentTimeout;

/**
 * One timeout policy, five layers.
 *
 * A chat turn is bounded five times over, and the numbers only work if they stay
 * in this order — each layer must outlive the one it wraps, or the outer one fires
 * first and the inner one's careful error handling never runs. The worst case is
 * the job timing out mid-stream: a PHP fatal is not a Throwable, so
 * ChatEditPage's catch is bypassed, `wire:stream` has already written to the
 * response body, and the operator gets a blank modal instead of an apology.
 *
 * Deliberately NOT collapsed into a shared constants class. Each of these numbers
 * carries a long, layer-specific rationale where it lives (fpm/nginx read
 * timeouts, uncatchable fatals, SSE header timing, a dead-worker backstop);
 * hoisting the values into one holder would strand every one of those comments.
 * The ORDER is the invariant, so the order is what gets a test.
 */
/**
 * The `#[Timeout]` a class declares. Two DIFFERENT attributes carry this name —
 * `Laravel\Ai\Attributes\Timeout` bounds one provider request,
 * `Illuminate\Queue\Attributes\Timeout` bounds the whole job — and the chat path
 * uses one of each. That collision is itself worth pinning: reading `#[Timeout]`
 * in a diff tells you nothing until you check the import.
 *
 * @param  class-string  $class
 * @param  class-string  $attribute
 */
function timeoutAttribute(string $class, string $attribute): int
{
    $attributes = new ReflectionClass($class)->getAttributes($attribute);

    expect($attributes)->not->toBeEmpty();

    $value = $attributes[0]->getArguments()[0] ?? null;

    expect($value)->toBeInt();

    /** @var int $value */
    return $value;
}

function privateConstant(string $class, string $name): int
{
    $value = new ReflectionClass($class)->getConstants()[$name] ?? null;

    expect($value)->toBeInt();

    /** @var int $value */
    return $value;
}

it('keeps the chat turn timeouts ordered from innermost to outermost', function (): void {
    $budget = privateConstant(ChatEditPage::class, 'TURN_BUDGET_SECONDS');
    $agent = timeoutAttribute(PageEditorAgent::class, AgentTimeout::class);
    $job = timeoutAttribute(ChatEditPageJob::class, QueueTimeout::class);
    $stream = privateConstant(PageEditorChatStreamController::class, 'MAX_SECONDS');
    $editor = privateConstant(
        InteractsWithPageChat::class,
        'CHAT_TURN_TIMEOUT_SECONDS',
    );

    // The turn's own wall-clock budget must fire FIRST, because it is the only one
    // that throws catchably and so the only one that can apologise.
    expect($budget)->toBeLessThan($agent)
        // A single provider request must not outlive the turn that contains it.
        ->and($agent)->toBeLessThan($job)
        // The SSE tail and the editor's give-up must both outlive the worker, or
        // they abandon a turn that was still going to succeed.
        ->and($job)->toBeLessThanOrEqual($stream)
        ->and($job)->toBeLessThanOrEqual($editor);
});

it('reads real values, so the ordering assertions cannot pass vacuously', function (): void {
    // A renamed constant or a dropped #[Timeout] would otherwise make the test
    // above compare nulls and stay green.
    expect(privateConstant(ChatEditPage::class, 'TURN_BUDGET_SECONDS'))->toBe(90)
        ->and(timeoutAttribute(PageEditorAgent::class, AgentTimeout::class))->toBe(120)
        ->and(timeoutAttribute(ChatEditPageJob::class, QueueTimeout::class))->toBe(150)
        ->and(privateConstant(PageEditorChatStreamController::class, 'MAX_SECONDS'))->toBe(180);
});
