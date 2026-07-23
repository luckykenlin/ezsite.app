<?php

declare(strict_types=1);

use App\Models\Tenant;
use Illuminate\Support\Facades\Log;

it('renders a healthy block even when a sibling block is unrenderable', function (array|string $badBlock): void {
    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantPage($tenant, [
        $badBlock,
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Still here']],
    ]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('Still here');
})->with([
    'unknown type' => [['type' => 'ghost', 'data' => []]],
    'missing type key' => [['data' => ['heading' => 'x']]],
    'invalid variant' => [['type' => 'hero', 'data' => ['variant' => 'bogus', 'heading' => 'x']]],
    'non-array block entry' => ['just a string'],
    'non-array data (tolerated, not skipped)' => [['type' => 'hero', 'data' => 'oops']],
]);

it('logs a warning when it skips an unrenderable block', function (array|string $badBlock): void {
    Log::spy();

    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantPage($tenant, [$badBlock]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))->assertOk();

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message): bool => $message === 'fabricator.block_skipped')
        ->atLeast()->once();
})->with([
    'unknown type' => [['type' => 'ghost', 'data' => []]],
    'missing type key' => [['data' => ['heading' => 'x']]],
    'invalid variant' => [['type' => 'hero', 'data' => ['variant' => 'bogus', 'heading' => 'x']]],
    'non-array block entry' => ['just a string'],
]);
