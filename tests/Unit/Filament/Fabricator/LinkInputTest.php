<?php

declare(strict_types=1);

use App\Filament\Fabricator\Fields\LinkInput;
use App\Models\Page;
use App\Models\Tenant;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Livewire\Component as LivewireComponent;

it('suggests the tenant pages as full parent-chain paths', function (): void {
    $tenant = Tenant::factory()->create();

    $options = $this->runInTenant($tenant, function () use ($tenant): ?array {
        $parent = Page::query()->create([
            'tenant_id' => $tenant->id, 'title' => 'Services', 'slug' => 'services', 'layout' => 'main', 'blocks' => [],
        ]);
        Page::query()->create([
            'tenant_id' => $tenant->id, 'title' => 'Plumbing', 'slug' => 'plumbing', 'layout' => 'main', 'parent_id' => $parent->id, 'blocks' => [],
        ]);

        $livewire = new class extends LivewireComponent implements HasSchemas
        {
            use InteractsWithSchemas;
        };

        return LinkInput::make('url')
            ->container(Schema::make($livewire))
            ->getDatalistOptions();
    });

    expect($options)->toEqualCanonicalizing(['/services', '/services/plumbing']);
});
