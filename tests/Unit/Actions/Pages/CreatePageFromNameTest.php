<?php

declare(strict_types=1);

use App\Actions\Pages\CreatePageFromName;
use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\Tenant;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Inserts a page straight through the query builder, bypassing the model
 * events — the stand-in for another operator winning the race between the
 * slug lookup and the insert.
 */
function raceForSlug(Tenant $tenant, string $slug): void
{
    DB::table('pages')->insert([
        'tenant_id' => $tenant->id,
        'title' => 'Raced',
        'slug' => $slug,
        'layout' => 'main',
        'blocks' => '[]',
        'status' => PageStatus::Draft->value,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('creates a draft from nothing but a name', function (): void {
    $tenant = Tenant::factory()->create();

    $id = $this->runInTenant($tenant, fn (): int => (int) resolve(CreatePageFromName::class)
        ->handle('  Our Services  ')
        ->getKey());

    $page = Page::query()->findOrFail($id);

    expect($page->title)->toBe('Our Services')
        ->and($page->slug)->toBe('our-services')
        ->and($page->layout)->toBe('main')
        ->and($page->parent_id)->toBeNull()
        ->and($page->blocks)->toBeEmpty()
        ->and($page->status)->toBe(PageStatus::Draft)
        ->and($page->tenant_id)->toBe($tenant->id);
});

it('stores a starting seo description when given one', function (): void {
    $tenant = Tenant::factory()->create();

    $id = $this->runInTenant($tenant, fn (): int => (int) resolve(CreatePageFromName::class)
        ->handle('About', [], 'The story behind us.')
        ->getKey());

    expect(Page::query()->findOrFail($id)->seo_description)->toBe('The story behind us.');
});

it('suffixes the slug when the name is already taken', function (): void {
    $tenant = Tenant::factory()->create();

    $slug = $this->runInTenant($tenant, function (): string {
        $action = resolve(CreatePageFromName::class);
        $action->handle('Contact');

        return $action->handle('Contact')->slug;
    });

    expect($slug)->toBe('contact-2');
});

it('re-derives the slug when another operator wins the race', function (): void {
    $tenant = Tenant::factory()->create();

    $slug = $this->runInTenant($tenant, function () use ($tenant): string {
        $raced = false;

        Page::creating(function (Page $page) use (&$raced, $tenant): void {
            if ($raced || $page->slug !== 'contact') {
                return;
            }

            $raced = true;
            raceForSlug($tenant, 'contact');
        });

        return resolve(CreatePageFromName::class)->handle('Contact')->slug;
    });

    expect($slug)->toBe('contact-2');
});

it('gives up rather than spinning when every attempt loses the race', function (): void {
    $tenant = Tenant::factory()->create();

    $this->runInTenant($tenant, function () use ($tenant): void {
        Page::creating(function (Page $page) use ($tenant): void {
            raceForSlug($tenant, $page->slug);
        });

        resolve(CreatePageFromName::class)->handle('Contact');
    });
})->throws(UniqueConstraintViolationException::class);
