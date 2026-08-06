<?php

declare(strict_types=1);

use App\Enums\PostCtaAction;
use App\Enums\PostKind;
use App\Enums\PostStatus;
use App\Filament\Tenant\Resources\Posts\Pages\CreatePost;
use App\Filament\Tenant\Resources\Posts\Pages\EditPost;
use App\Filament\Tenant\Resources\Posts\Pages\ListPosts;
use App\Models\Post;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

test('can create an update scoped to the current tenant', function (): void {
    // The tenant stamp moved from CreateAction::mutateDataUsing on the list page to
    // CreatePost::mutateFormDataBeforeCreate when the resource grew real pages.
    // Without it every update is written with a null tenant_id and the RLS policy
    // rejects it — so this is the test that catches the move being forgotten.
    Livewire::test(CreatePost::class)
        ->fillForm([
            'title' => 'Spring gel sets are here',
            'excerpt' => 'Six new colours, in the chair from Tuesday.',
            'kind' => PostKind::Update->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $post = Post::query()->where('title', 'Spring gel sets are here')->sole();

    expect($post->tenant_id)->toBe($this->tenant->id)
        ->and($post->slug)->toBe('spring-gel-sets-are-here')
        ->and($post->status)->toBe(PostStatus::Draft)
        ->and($post->published_at)->toBeNull();
});

test('an offer must carry the dates Google requires for one', function (): void {
    // The commonest 400 in a first Business Profile implementation is an OFFER with
    // no event{} — so the form asks for the window rather than the connector
    // discovering it is missing.
    Livewire::test(CreatePost::class)
        ->fillForm([
            'title' => 'Ten percent off',
            'kind' => PostKind::Offer->value,
            'starts_at' => null,
            'ends_at' => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['starts_at' => 'required', 'ends_at' => 'required']);
});

test('an offer window has to run forwards', function (): void {
    Livewire::test(CreatePost::class)
        ->fillForm([
            'title' => 'Ten percent off',
            'kind' => PostKind::Offer->value,
            'starts_at' => now()->addWeek(),
            'ends_at' => now(),
        ])
        ->call('create')
        ->assertHasFormErrors(['ends_at']);
});

test('a call button needs no url, every other button does', function (PostCtaAction $action, bool $needsUrl): void {
    // Google uses the profile's own phone number for CALL and rejects an action
    // carrying a url, so this asymmetry is the platform's, not ours.
    $form = Livewire::test(CreatePost::class)
        ->fillForm([
            'title' => 'Book in for spring',
            'kind' => PostKind::Update->value,
            'cta_action' => $action->value,
            'cta_url' => null,
        ])
        ->call('create');

    $needsUrl
        ? $form->assertHasFormErrors(['cta_url' => 'required'])
        : $form->assertHasNoFormErrors();
})->with([
    'call' => [PostCtaAction::Call, false],
    'book' => [PostCtaAction::Book, true],
    'order' => [PostCtaAction::Order, true],
]);

test('publishing from the table stamps the moment it went live', function (): void {
    $post = Post::factory()->create(['tenant_id' => $this->tenant->id]);

    Livewire::test(ListPosts::class)
        ->callAction(TestAction::make('publish')->table($post))
        ->assertNotified();

    $published = Post::query()->findOrFail($post->getKey());

    expect($published->status)->toBe(PostStatus::Published)
        ->and($published->published_at)->not->toBeNull();
});

test('re-publishing does not push an old update back to the top of the feed', function (): void {
    // published_at is the date a visitor reads and the date the feed sorts by, so
    // an operator fixing a typo six weeks later must not bump their own
    // announcement above everything published since.
    $post = Post::factory()->published()->create([
        'tenant_id' => $this->tenant->id,
        'published_at' => now()->subMonth(),
    ]);
    $originallyAt = $post->published_at;

    Livewire::test(ListPosts::class)
        ->callAction(TestAction::make('publish')->table($post))   // unpublish
        ->callAction(TestAction::make('publish')->table($post));  // and back

    expect(Post::query()->findOrFail($post->getKey())->published_at?->getTimestamp())
        ->toBe($originallyAt?->getTimestamp());
});

test('the composer publishes what the operator is looking at', function (): void {
    // Save first, then publish — the "publish what you see" contract PublishPage
    // documents for pages.
    $post = Post::factory()->create(['tenant_id' => $this->tenant->id, 'title' => 'Draft title']);

    Livewire::test(EditPost::class, ['record' => $post->getKey()])
        ->fillForm(['title' => 'Edited then published'])
        ->callAction('publish')
        ->assertNotified();

    $published = Post::query()->findOrFail($post->getKey());

    expect($published->title)->toBe('Edited then published')
        ->and($published->status)->toBe(PostStatus::Published);
});

test('the view link only exists once there is something to view', function (): void {
    $draft = Post::factory()->create(['tenant_id' => $this->tenant->id]);

    Livewire::test(EditPost::class, ['record' => $draft->getKey()])
        ->assertActionHidden('visit');

    $published = Post::factory()->published()->create(['tenant_id' => $this->tenant->id]);

    Livewire::test(EditPost::class, ['record' => $published->getKey()])
        ->assertActionVisible('visit');
});

test('the table shows which updates have run out', function (): void {
    // Not a status badge: "finished" is a fact about the dates, not a state the
    // operator sets — and one who cannot see it publishes a second offer on top of
    // a live one.
    $running = Post::factory()->offer()->create(['tenant_id' => $this->tenant->id, 'title' => 'Running offer']);
    $finished = Post::factory()->offer()->expired()->create(['tenant_id' => $this->tenant->id, 'title' => 'Finished offer']);

    Livewire::test(ListPosts::class)
        // Tables are deferLoading() panel-wide, so nothing renders until asked.
        ->call('loadTable')
        ->assertCanSeeTableRecords([$running, $finished])
        ->assertTableColumnStateSet('expired', false, $running)
        ->assertTableColumnStateSet('expired', true, $finished);
});

test('the composer opens an existing offer with its own sections already showing', function (): void {
    // Hydrated form state holds the ENUM INSTANCE, not its string value, while a
    // freshly-picked one holds the string — so the conditional sections have to
    // handle both. Opening a saved offer is the only path that exercises the first.
    $post = Post::factory()->offer()->create(['tenant_id' => $this->tenant->id]);

    Livewire::test(EditPost::class, ['record' => $post->getKey()])
        ->assertSchemaComponentVisible('starts_at', 'form')
        ->assertSchemaComponentVisible('ends_at', 'form')
        ->assertSchemaComponentVisible('offer_coupon_code', 'form')
        ->assertSchemaComponentVisible('cta_url', 'form');
});

test('a plain update hides the sections that belong to a dated offer', function (): void {
    $post = Post::factory()->create(['tenant_id' => $this->tenant->id]);

    Livewire::test(EditPost::class, ['record' => $post->getKey()])
        ->assertSchemaComponentHidden('starts_at', 'form')
        ->assertSchemaComponentHidden('offer_coupon_code', 'form')
        // No button chosen, so nowhere for it to go.
        ->assertSchemaComponentHidden('cta_url', 'form');
});
