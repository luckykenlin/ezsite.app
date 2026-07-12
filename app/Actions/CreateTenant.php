<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Domain;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class CreateTenant
{
    public function handle(string $name, ?string $email = null): Tenant
    {
        return DB::transaction(function () use ($name, $email): Tenant {
            $tenant = Tenant::query()->create([
                'name' => $name,
                'email' => $email,
            ]);

            $tenant->domains()->create([
                'domain' => $this->generateUniqueSubdomain($name),
            ]);

            return $tenant;
        });
    }

    private function generateUniqueSubdomain(string $name): string
    {
        $slug = Str::slug($name) ?: Str::random(8);
        $subdomain = $slug;
        $attempt = 1;

        while (Domain::query()->where('domain', $subdomain)->exists()) {
            $attempt++;
            $subdomain = sprintf('%s-%d', $slug, $attempt);
        }

        return $subdomain;
    }
}
