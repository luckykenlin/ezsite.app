<?php

declare(strict_types=1);

use App\Ai\Agents\PageEditorAgent;
use App\Ai\Agents\SiteDraftAgent;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\UseCheapestModel;
use Laravel\Ai\Attributes\UseSmartestModel;

/**
 * The two agents sit on opposite ends of one latency/quality trade, and the
 * assignment is the point: the chat agent runs while the operator watches, so
 * it gets the provider's cheap/fast tier; the site composer runs once,
 * unwatched, and its output IS the first-run product moment, so it gets the
 * smartest tier. Swapping them (or "upgrading" the chat agent to the smart
 * tier for quality) re-creates the slow-turn problem the tiers exist to solve.
 */
it('keeps the interactive chat agent on the cheapest tier and the site composer on the smartest', function (): void {
    expect(new ReflectionClass(PageEditorAgent::class)->getAttributes(UseCheapestModel::class))
        ->not->toBeEmpty()
        ->and(new ReflectionClass(SiteDraftAgent::class)->getAttributes(UseSmartestModel::class))
        ->not->toBeEmpty();
});

it('never pins a literal provider or model on an agent', function (string $agent): void {
    // Which model each tier means lives in config (ai.providers.*.models.text),
    // so swapping providers stays a .env change. A #[Model('...')] or
    // #[Provider(...)] here would silently override that and marry the app to
    // one vendor's model names.
    $reflection = new ReflectionClass($agent);

    expect($reflection->getAttributes(Model::class))->toBeEmpty()
        ->and($reflection->getAttributes(Provider::class))->toBeEmpty();
})->with([PageEditorAgent::class, SiteDraftAgent::class]);
