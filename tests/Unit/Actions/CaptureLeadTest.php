<?php

declare(strict_types=1);

use App\Actions\CaptureLead;
use App\Enums\LeadStatus;
use App\Events\LeadCaptured;
use App\Models\Lead;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Event;

it('stores the enquiry against the current tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $location = $this->runInTenant($tenant, fn (): Location => Location::factory()->create(['tenant_id' => $tenant->id]));
    $page = $this->createTenantPage($tenant, []);

    $lead = $this->runInTenant($tenant, fn (): Lead => resolve(CaptureLead::class)->handle(
        ['name' => 'Mei', 'phone' => '+1 555 0100', 'message' => 'Do you take walk-ins?'],
        $location,
        $page,
        '203.0.113.7',
    ));

    $stored = Lead::query()->findOrFail($lead->getKey());

    expect($stored->tenant_id)->toBe($tenant->id)
        ->and($stored->name)->toBe('Mei')
        ->and($stored->phone)->toBe('+1 555 0100')
        ->and($stored->email)->toBeNull()
        ->and($stored->message)->toBe('Do you take walk-ins?')
        ->and($stored->location_id)->toBe($location->id)
        ->and($stored->page_id)->toBe($page->id)
        ->and($stored->source)->toBe('contact_form')
        ->and($stored->status)->toBe(LeadStatus::New)
        ->and($stored->ip_address)->toBe('203.0.113.7');
});

it('announces the capture rather than knowing how operators are told', function (): void {
    // The seam that keeps App\Actions off the admin panel: the action stores the
    // lead and fires the event, and App\Listeners\NotifyOperatorsOfLead owns the
    // Filament notification and the inbox URL. Pinned so a future "just send it
    // here" shortcut reintroducing the panel import fails loudly.
    Event::fake([LeadCaptured::class]);

    $tenant = Tenant::factory()->create();

    $lead = $this->runInTenant($tenant, fn (): Lead => resolve(CaptureLead::class)->handle(['name' => 'Mei']));

    Event::assertDispatched(
        LeadCaptured::class,
        fn (LeadCaptured $event): bool => $event->lead->is($lead),
    );
});

it('notifies every member of the tenant with a link to the inbox', function (): void {
    $tenant = Tenant::factory()->create();
    $members = User::factory()->count(2)->memberOf($tenant)->create();
    User::factory()->create(); // not a member of this tenant

    $this->runInTenant($tenant, fn (): Lead => resolve(CaptureLead::class)->handle(
        ['name' => 'Mei', 'phone' => '+1 555 0100'],
    ));

    $notifications = DatabaseNotification::query()->get();

    expect($notifications)->toHaveCount(2)
        ->and($notifications->pluck('notifiable_id')->sort()->values()->all())
        ->toBe($members->pluck('id')->sort()->values()->all());

    $body = json_encode($notifications->first()?->data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

    expect($body)->toContain('New enquiry from Mei')
        ->and($body)->toContain('+1 555 0100')
        ->and($body)->toContain('/admin/leads');
});

it('still stores the enquiry for a tenant with no panel members yet', function (): void {
    $tenant = Tenant::factory()->create();

    $lead = $this->runInTenant($tenant, fn (): Lead => resolve(CaptureLead::class)->handle(
        ['name' => 'Mei', 'email' => 'mei@example.com'],
    ));

    expect(Lead::query()->findOrFail($lead->getKey())->email)->toBe('mei@example.com')
        ->and(DatabaseNotification::query()->count())->toBe(0);
});
