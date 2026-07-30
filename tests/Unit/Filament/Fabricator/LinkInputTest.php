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

it('rejects a url whose scheme would execute, and accepts the shapes blocks store', function (string $url, bool $passes): void {
    // The render guard drops these anyway
    // ({@see BlockRegistry::denyExecutableUrls()}), but silently — an operator
    // who pastes one should be told, not watch their link vanish on save.
    $livewire = new class extends LivewireComponent implements HasSchemas
    {
        use InteractsWithSchemas;
    };

    $field = LinkInput::make('cta_url')->container(Schema::make($livewire));

    $failed = false;
    $fail = function () use (&$failed): void {
        $failed = true;
    };

    foreach ($field->getValidationRules() as $rule) {
        if ($rule instanceof Closure) {
            $rule('cta_url', $url, $fail);
        }
    }

    expect($failed)->toBe(! $passes);
})->with([
    'javascript' => ['javascript:alert(1)', false],
    'vbscript' => ['vbscript:msgbox(1)', false],
    'data' => ['data:text/html;base64,PHNjcmlwdD4x', false],
    'obfuscated javascript' => ["java\tscript:alert(1)", false],
    'relative path' => ['/contact', true],
    'anchor' => ['#book', true],
    'https' => ['https://example.com', true],
    'mailto' => ['mailto:hi@example.com', true],
    'tel' => ['tel:+15551234567', true],
]);
