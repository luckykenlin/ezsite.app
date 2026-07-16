<?php

declare(strict_types=1);

use App\Filament\Tenant\Resources\Posts\Pages\ListPosts;
use App\Models\Post;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->tenant = $this->actingAsTenantPanelMember();
});

test('can list posts', function (): void {
    $posts = Post::factory()->count(3)->for($this->tenant)->create();

    Livewire::test(ListPosts::class)
        ->call('loadTable')
        ->assertCanSeeTableRecords($posts);
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

test('can update a post', function (): void {
    $post = Post::factory()->for($this->tenant)->create(['title' => 'Old Title']);

    Livewire::test(ListPosts::class)
        ->callAction(TestAction::make(EditAction::class)->table($post), data: ['title' => 'New Title'])
        ->assertHasNoFormErrors();

    expect($post->refresh()->title)->toBe('New Title');
});

test('can delete a post', function (): void {
    $post = Post::factory()->for($this->tenant)->create();

    Livewire::test(ListPosts::class)
        ->callAction(TestAction::make(DeleteAction::class)->table($post));

    $this->assertModelMissing($post);
});

test('can bulk delete posts', function (): void {
    $posts = Post::factory()->count(2)->for($this->tenant)->create();

    Livewire::test(ListPosts::class)
        ->selectTableRecords($posts)
        ->callAction(TestAction::make(DeleteBulkAction::class)->table()->bulk());

    expect(Post::query()->count())->toBe(0);
});
