<?php

declare(strict_types=1);

use App\Models\Domain;

test('the url expands a bare subdomain label with the central host and uses a custom domain as-is', function (string $registered, bool $isCustom): void {
    $domain = Domain::factory()->create(['domain' => $registered]);

    $expectedHost = $isCustom
        ? $registered
        : sprintf('%s.%s', $registered, uri(config()->string('app.url'))->host());

    expect($domain->getUrl())->toBe(sprintf('http://%s/', $expectedHost));
})->with('tenant_domains');

test('to array', function (): void {
    $domain = Domain::factory()->create();
    $domain = Domain::query()->findOrFail($domain->getKey());

    expect(array_keys($domain->toArray()))
        ->toBe([
            'id',
            'domain',
            'tenant_id',
            'created_at',
            'updated_at',
        ]);
});
