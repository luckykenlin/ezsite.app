<?php

declare(strict_types=1);

use App\Models\Domain;
use App\Models\Tenant;

test('the custom tenant and domain models are bound in config', function (): void {
    expect(config('tenancy.models.tenant'))->toBe(Tenant::class)
        ->and(config('tenancy.models.domain'))->toBe(Domain::class);
});
