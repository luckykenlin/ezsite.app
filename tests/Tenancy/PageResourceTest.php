<?php

declare(strict_types=1);

use App\Filament\Tenant\Resources\PageResource\Pages\CreatePage;
use App\Models\Page;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();

    $user = User::factory()->create();
    $user->tenants()->attach($this->tenant);
    $this->actingAs($user);

    tenancy()->initialize($this->tenant);

    Filament::setCurrentPanel(Filament::getPanel('tenant'));
    Filament::setTenant($this->tenant);
});

afterEach(function (): void {
    tenancy()->end();
});

test('can create a page scoped to the current tenant', function (): void {
    Livewire::test(CreatePage::class)
        ->fillForm([
            'title' => 'Home',
            'slug' => '/',
            'layout' => 'main',
            'blocks' => [
                ['type' => 'heading', 'data' => ['content' => 'Welcome']],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $page = Page::query()->where('slug', '/')->firstOrFail();

    expect($page->tenant_id)->toBe($this->tenant->id)
        ->and($page->blocks)->toBe([['type' => 'heading', 'data' => ['content' => 'Welcome']]]);
});
