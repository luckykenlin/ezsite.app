<?php

declare(strict_types=1);

use App\Models\Tenant;
use Illuminate\Support\Facades\Log;

it('skips an unrenderable block with a warning while healthy siblings still render', function (array|string $badBlock): void {
    Log::spy();

    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantPage($tenant, [
        $badBlock,
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Still here']],
    ]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('Still here');

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message): bool => $message === 'fabricator.block_skipped')
        ->atLeast()->once();
})->with([
    'unknown type' => [['type' => 'ghost', 'data' => []]],
    'missing type key' => [['data' => ['heading' => 'x']]],
    'invalid variant' => [['type' => 'hero', 'data' => ['variant' => 'bogus', 'heading' => 'x']]],
    'non-array block entry' => ['just a string'],
]);

it('tolerates non-array block data rather than skipping the block', function (): void {
    Log::spy();

    $tenant = Tenant::factory()->withDomain('acme')->create();
    $this->createTenantPage($tenant, [
        ['type' => 'hero', 'data' => 'oops'],
        ['type' => 'hero', 'data' => ['variant' => 'centered-minimal', 'heading' => 'Still here']],
    ]);

    $this->get(sprintf('http://acme.%s/', $this->centralDomain()))
        ->assertOk()
        ->assertSee('Still here');

    Log::shouldNotHaveReceived('warning');
});
