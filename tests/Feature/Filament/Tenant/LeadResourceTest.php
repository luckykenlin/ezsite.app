<?php

declare(strict_types=1);

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Filament\Tenant\Resources\Leads\LeadResource;
use App\Filament\Tenant\Resources\Leads\Pages\ListLeads;
use App\Models\Lead;
use App\Models\Tenant;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

function tenantLead(array $attributes = []): Lead
{
    return Lead::query()->create([
        'tenant_id' => tenant('id'),
        'name' => 'Mei',
        'phone' => '+1 555 0100',
        'message' => 'Do you take walk-ins?',
        ...$attributes,
    ]);
}

it('lists the tenant enquiries newest first', function (): void {
    $older = tenantLead(['name' => 'Older', 'created_at' => now()->subDay()]);
    $newer = tenantLead(['name' => 'Newer']);

    Livewire::test(ListLeads::class)
        ->call('loadTable')
        ->assertCanSeeTableRecords([$newer, $older], inOrder: true)
        ->assertSee('Do you take walk-ins?')
        ->assertSee('+1 555 0100');
});

it('shows only enquiries of the current tenant', function (): void {
    $mine = tenantLead();
    $other = Tenant::factory()->create();
    $theirs = $this->runInTenant($other, fn (): Lead => Lead::factory()->create(['tenant_id' => $other->id]));

    Livewire::test(ListLeads::class)
        ->call('loadTable')
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs]);
});

it('filters by status', function (): void {
    $unread = tenantLead(['name' => 'Unread']);
    $archived = tenantLead(['name' => 'Filed', 'status' => LeadStatus::Archived]);

    Livewire::test(ListLeads::class)
        ->call('loadTable')
        ->filterTable('status', LeadStatus::New->value)
        ->assertCanSeeTableRecords([$unread])
        ->assertCanNotSeeTableRecords([$archived]);
});

it('shows which surface produced each enquiry, and filters by it', function (): void {
    // The question the inbox has to answer before an operator can decide
    // whether the popup is worth the interruption.
    $form = tenantLead(['name' => 'From the contact page', 'source' => LeadSource::ContactForm]);
    $inline = tenantLead(['name' => 'From a signup block', 'source' => LeadSource::InlineForm]);
    $popup = tenantLead(['name' => 'From the popup', 'source' => LeadSource::Popup]);

    Livewire::test(ListLeads::class)
        ->call('loadTable')
        ->assertCanSeeTableRecords([$form, $inline, $popup])
        ->assertSee('Contact form')
        ->assertSee('Inline form')
        ->assertSee('Popup')
        ->filterTable('source', LeadSource::Popup->value)
        ->assertCanSeeTableRecords([$popup])
        ->assertCanNotSeeTableRecords([$form, $inline]);
});

it('shows the requested table beside a reservation lead', function (): void {
    $reservation = tenantLead([
        'source' => LeadSource::Reservation,
        'reserved_date' => '2026-08-15',
        'reserved_time' => '19:00',
        'party_size' => 4,
    ]);
    $plain = tenantLead(['name' => 'Plain enquiry']);

    Livewire::test(ListLeads::class)
        ->call('loadTable')
        ->assertCanSeeTableRecords([$reservation, $plain])
        // The source badge and the one-line table request — the row is
        // actionable without opening it.
        ->assertSee('Reservation')
        ->assertSee('Sat 15 Aug, 19:00 · party of 4');
});

it('names an enquiry that never gave one', function (): void {
    // The low-friction surfaces ask for a reply channel and nothing else, so
    // the inbox must still have something human to show.
    $anonymous = tenantLead([
        'name' => null,
        'phone' => null,
        'email' => 'jordan@example.com',
        'source' => LeadSource::Popup,
    ]);

    Livewire::test(ListLeads::class)
        ->call('loadTable')
        ->assertCanSeeTableRecords([$anonymous])
        ->assertSee('jordan');
});

it('marks an enquiry as read', function (): void {
    $lead = tenantLead();

    Livewire::test(ListLeads::class)
        ->callAction(TestAction::make('markAsRead')->table($lead));

    $lead->refresh();

    expect($lead->status)->toBe(LeadStatus::Read)
        ->and($lead->read_at)->not->toBeNull();
});

it('keeps the original read timestamp when a bulk mark sweeps an already-read enquiry', function (): void {
    // The row action hides itself on a read lead, so bulk selection is the
    // only way an already-read enquiry gets marked again.
    $readAt = now()->subHour();
    $alreadyRead = tenantLead(['name' => 'Seen', 'status' => LeadStatus::Read, 'read_at' => $readAt]);
    $unread = tenantLead(['name' => 'Fresh']);

    Livewire::test(ListLeads::class)
        ->call('loadTable')
        ->selectTableRecords([$alreadyRead, $unread])
        ->callAction(TestAction::make('markAsRead')->table()->bulk());

    expect($alreadyRead->refresh()->read_at?->toDateTimeString())->toBe($readAt->toDateTimeString())
        ->and($unread->refresh()->status)->toBe(LeadStatus::Read)
        ->and($unread->read_at)->not->toBeNull();
});

it('archives an enquiry', function (): void {
    $lead = tenantLead();

    Livewire::test(ListLeads::class)
        ->callAction(TestAction::make('archive')->table($lead));

    expect($lead->refresh()->status)->toBe(LeadStatus::Archived);
});

// Bulk mark-as-read is covered by the read-timestamp test above; this is the
// archive half, and that it only touches the selected rows.
it('archives in bulk, leaving unselected enquiries alone', function (): void {
    $first = tenantLead(['name' => 'First']);
    $second = tenantLead(['name' => 'Second']);

    Livewire::test(ListLeads::class)
        ->call('loadTable')
        ->selectTableRecords([$first])
        ->callAction(TestAction::make('archive')->table()->bulk());

    expect($first->refresh()->status)->toBe(LeadStatus::Archived)
        ->and($second->refresh()->status)->toBe(LeadStatus::New);
});

it('reading an enquiry through the viewer marks it read', function (): void {
    $lead = tenantLead();

    Livewire::test(ListLeads::class)
        ->callAction(TestAction::make('view')->table($lead));

    expect($lead->refresh()->status)->toBe(LeadStatus::Read);
});

it('badges the unread count on the sidebar', function (): void {
    expect(LeadResource::getNavigationBadge())->toBeNull();

    tenantLead();
    tenantLead(['name' => 'Second']);
    tenantLead(['name' => 'Filed', 'status' => LeadStatus::Archived]);

    expect(LeadResource::getNavigationBadge())->toBe('2')
        ->and(LeadResource::getNavigationBadgeColor())->toBe('success');
});

it('cannot be created by hand', function (): void {
    expect(LeadResource::canCreate())->toBeFalse();
});
