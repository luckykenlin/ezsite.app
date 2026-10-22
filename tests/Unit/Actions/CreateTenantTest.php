<?php

declare(strict_types=1);

use App\Actions\CreateTenant;
use App\Enums\Vertical;
use App\Models\Tenant;

test('a tenant is created with the vertical it is given', function (?Vertical $vertical): void {
    $tenant = resolve(CreateTenant::class)->handle('Polished Nail Bar', 'hello@polished.test', vertical: $vertical);

    expect(Tenant::query()->findOrFail($tenant->getKey())->vertical)->toBe($vertical);
})->with([
    'a nail salon' => [Vertical::NailSalon],
    'no vertical' => [null],
]);
