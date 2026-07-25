<?php

declare(strict_types=1);

use App\Filament\Tenant\Resources\Posts\Pages\ListPosts;
use App\Models\Post;
use Filament\Actions\CreateAction;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->tenant = $this->actingAsTenantPanelMember();
});

test('can create a post scoped to the current tenant', function (): void {
    Livewire::test(ListPosts::class)
        ->callAction(CreateAction::class, [
            'title' => 'Hello World',
            'body' => 'Some body text',
        ])
        ->assertHasNoFormErrors();

    $post = Post::query()->where('title', 'Hello World')->firstOrFail();

    expect($post->tenant_id)->toBe($this->tenant->id);
});
