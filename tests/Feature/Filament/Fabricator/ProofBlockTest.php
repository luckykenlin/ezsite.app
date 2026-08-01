<?php

declare(strict_types=1);

use App\Models\Tenant;
use Illuminate\Testing\TestResponse;

/**
 * View logic for the three "proof" blocks — steps, team and logos.
 *
 * Grouped because what needs guarding in each is the same KIND of rule: what the
 * view generates rather than stores (step numbers, initials) and what it refuses
 * to render at all (an incomplete item). Cross-block behaviour that every type
 * shares stays in BlockRenderTest; stats has no logic of its own beyond the
 * required pair, which is covered there.
 */
function renderProofBlock(string $type, array $data, string $subdomain = 'acme'): TestResponse
{
    $tenant = Tenant::factory()->withDomain($subdomain)->create();
    test()->createTenantBusiness($tenant, ['name' => 'Corner Cafe', 'logo_path' => null], 0);
    test()->createTenantPage($tenant, [['type' => $type, 'data' => $data]]);

    return test()->get(sprintf('http://%s.%s/', $subdomain, test()->centralDomain()))->assertOk();
}

/*
 * The numbers come from the item's POSITION, never from stored content. An
 * operator who types "1." into a title has a page that lies the moment they
 * delete step one — and deleting a step mid-list is precisely what this block
 * gets edited for.
 */
it('numbers steps from their position, so deleting one renumbers the rest', function (): void {
    $response = renderProofBlock('steps', [
        'steps' => [
            ['title' => 'Second step, now first'],
            ['title' => 'Third step, now second'],
        ],
    ]);

    $response->assertSeeHtmlInOrder(['>1<', 'Second step, now first', '>2<', 'Third step, now second'])
        // aria-hidden, because the <ol> already conveys the ordinal — a screen
        // reader would otherwise hear "one one". Asserted separately from the
        // number itself: the two sit in one multi-line tag, and pairing them
        // would couple this test to Blade's attribute formatting.
        ->assertSeeHtml('aria-hidden="true"');
});

it('skips a step with no title', function (): void {
    $response = renderProofBlock('steps', [
        'steps' => [['title' => 'Real step'], ['description' => 'Orphaned description']],
    ]);

    $response->assertSee('Real step')->assertDontSee('Orphaned description');

    // The surviving step is still numbered 1, and there is no second <li>.
    expect(mb_substr_count($response->getContent(), '<li class="flex gap-5">'))->toBe(1);
});

/*
 * Initials rather than a broken image frame: a team block is worth adding before
 * anyone has uploaded headshots, and an empty <img> is worse than no <img>.
 */
it('falls back to an initial when a team member has no photo', function (): void {
    renderProofBlock('team', ['members' => [['name' => 'dana reed', 'role' => 'Head baker']]])
        // Upper-cased from a lower-case name, via mb_* so non-ASCII names work.
        ->assertSeeHtml('>D<')
        ->assertSee('Head baker')
        ->assertDontSeeHtml('<img');
});

it('renders a photo instead of initials when one resolves', function (): void {
    renderProofBlock('team', [
        'members' => [['name' => 'Dana Reed', 'avatar_url' => 'https://example.com/dana.jpg']],
    ])
        ->assertSee('https://example.com/dana.jpg')
        // Decorative: the name below is the accessible label, so an alt here
        // would be announced twice.
        ->assertSeeHtml('alt=""');
});

it('skips a team member with no name', function (): void {
    $response = renderProofBlock('team', [
        'members' => [['name' => 'Dana Reed'], ['role' => 'Nobody in particular']],
    ]);

    $response->assertSee('Dana Reed')->assertDontSee('Nobody in particular');
});

/*
 * A trust row with a hole in it does the opposite of its job, so an entry missing
 * either half is dropped. `name` counts as a half: a logo is a picture of a word,
 * so without it a screen reader gets nothing at all.
 */
it('drops a logo that has no image or no name', function (): void {
    $response = renderProofBlock('logos', [
        'logos' => [
            ['url' => 'https://example.com/acme.png', 'name' => 'Acme Corp'],
            ['name' => 'No image here'],
            ['url' => 'https://example.com/nameless.png'],
        ],
    ]);

    $response->assertSee('Acme Corp')->assertDontSee('No image here');

    expect(mb_substr_count($response->getContent(), '<img'))->toBe(1);
});

it('wraps a logo in a link only when it has somewhere to go', function (): void {
    $withLink = renderProofBlock('logos', [
        'logos' => [[
            'url' => 'https://example.com/acme.png',
            'name' => 'Acme Corp',
            'link_url' => 'https://acme.test',
        ]],
    ]);

    $withLink->assertSeeHtml('href="https://acme.test"')
        // Colour on hover only for a link: a static logo has nothing to react to.
        ->assertSeeHtml('hover:grayscale-0');
});

it('leaves an unlinked logo static, with no hover treatment', function (): void {
    renderProofBlock('logos', [
        'logos' => [['url' => 'https://example.com/acme.png', 'name' => 'Acme Corp']],
    ])
        ->assertSeeHtml('grayscale')
        ->assertDontSeeHtml('hover:grayscale-0')
        ->assertDontSeeHtml('<a href="https://example.com');
});

/*
 * The scheme guard is central (BlockRegistry::denyExecutableUrls) rather than
 * per-view, but these two blocks introduced new `*_url` keys, so this confirms the
 * convention actually catches them.
 */
it('strips an executable scheme from the new blocks url fields', function (): void {
    renderProofBlock('logos', [
        'logos' => [[
            'url' => 'https://example.com/acme.png',
            'name' => 'Acme Corp',
            'link_url' => 'javascript:alert(1)',
        ]],
    ])->assertDontSeeHtml('javascript:');
});
