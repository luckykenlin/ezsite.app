<?php

declare(strict_types=1);

use App\Ai\Agents\SiteDraftAgent;
use App\Jobs\GenerateSiteDraftJob;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

it('generates the draft on the worker and notifies the triggering user', function (): void {
    SiteDraftAgent::fake([[
        'preset' => 'fresh-modern',
        'rationale' => 'Modern fits.',
        'pages' => [[
            'title' => 'Home',
            'slug' => '/',
            'blocks' => [
                ['type' => 'hero', 'data' => ['heading' => 'Hi']],
                ['type' => 'features', 'data' => ['heading' => 'Why']],
                ['type' => 'cta', 'data' => ['heading' => 'Go', 'cta_label' => 'Now', 'cta_url' => '/x']],
            ],
        ]],
    ]]);

    $tenant = Tenant::factory()->create();
    $this->createTenantBusiness($tenant, [], 1);
    $user = User::factory()->memberOf($tenant)->create();

    new GenerateSiteDraftJob($tenant->id, $user->id)->handle();

    $notification = DB::table('notifications')->sole();

    expect($notification->notifiable_id)->toBe($user->id)
        ->and($notification->data)->toContain('Site draft ready');
});

it('notifies about a rejected draft instead of failing the job', function (): void {
    Log::spy();

    // Survives with too few blocks -> SiteDraftInvalid, on the first attempt
    // AND on the automatic retry.
    $thinDraft = [
        'preset' => 'fresh-modern',
        'rationale' => 'Too thin.',
        'pages' => [[
            'title' => 'Home',
            'slug' => '/',
            'blocks' => [['type' => 'hero', 'data' => ['heading' => 'Hi']]],
        ]],
    ];

    SiteDraftAgent::fake([$thinDraft, $thinDraft]);

    $tenant = Tenant::factory()->create();
    $this->createTenantBusiness($tenant, [], 1);
    $user = User::factory()->memberOf($tenant)->create();

    new GenerateSiteDraftJob($tenant->id, $user->id)->handle();

    $notification = DB::table('notifications')->sole();

    expect($notification->data)->toContain('Site draft failed');

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message): bool => $message === 'site_draft.rejected')
        ->once();
});
