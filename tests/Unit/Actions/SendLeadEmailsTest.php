<?php

declare(strict_types=1);

use App\Actions\SendLeadEmails;
use App\Mail\EnquiryReceipt;
use App\Mail\NewEnquiry;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Support\Facades\Mail;

test('every operator of the site gets their own copy', function (): void {
    Mail::fake();

    $tenant = Tenant::factory()->create();
    $members = User::factory()->count(2)->memberOf($tenant)->create();
    User::factory()->create(); // belongs to no tenant
    User::factory()->memberOf(Tenant::factory()->create())->create(); // runs a different site

    $lead = $this->runInTenant($tenant, fn (): Lead => Lead::factory()->create(['tenant_id' => $tenant->id]));

    $this->runInTenant($tenant, fn () => resolve(SendLeadEmails::class)->handle($lead, 'http://acme.ezsite.test/admin/leads'));

    Mail::assertSent(NewEnquiry::class, 2);

    // One message per operator, not one message with both addresses in To: a
    // shared header would disclose their colleagues' addresses to each other.
    foreach ($members as $member) {
        Mail::assertSent(NewEnquiry::class, fn (NewEnquiry $mail): bool => $mail->hasTo($member->email)
            && count($mail->to) === 1);
    }
});

test('the enquirer gets a receipt addressed to them', function (): void {
    Mail::fake();

    $tenant = Tenant::factory()->create();
    $this->createTenantBusiness($tenant, ['name' => 'Golden Dragon']);

    $lead = $this->runInTenant($tenant, fn (): Lead => Lead::factory()->create([
        'tenant_id' => $tenant->id,
        'email' => 'mei@example.com',
    ]));

    $this->runInTenant($tenant, fn () => resolve(SendLeadEmails::class)->handle($lead, 'http://acme.ezsite.test/admin/leads'));

    Mail::assertSent(EnquiryReceipt::class, fn (EnquiryReceipt $mail): bool => $mail->hasTo('mei@example.com')
        && $mail->envelope()->subject === 'Thanks for contacting Golden Dragon'
        && $mail->envelope()->from instanceof Address);
});

test('an enquiry with no email address produces no receipt', function (): void {
    // The sticky mobile call bar captures a phone number and nothing else.
    Mail::fake();

    $tenant = Tenant::factory()->create();
    User::factory()->memberOf($tenant)->create();

    $lead = $this->runInTenant($tenant, fn (): Lead => Lead::factory()->create([
        'tenant_id' => $tenant->id,
        'email' => null,
    ]));

    $this->runInTenant($tenant, fn () => resolve(SendLeadEmails::class)->handle($lead, 'http://acme.ezsite.test/admin/leads'));

    Mail::assertSent(NewEnquiry::class, 1);
    Mail::assertNotSent(EnquiryReceipt::class);
});

test('a site whose owner has not joined yet still answers the enquirer', function (): void {
    // Demo sites and template sites mid-provisioning have no members attached.
    // The operator hears nothing, but the visitor must not be left in silence.
    Mail::fake();

    $tenant = Tenant::factory()->create();

    $lead = $this->runInTenant($tenant, fn (): Lead => Lead::factory()->create([
        'tenant_id' => $tenant->id,
        'email' => 'mei@example.com',
    ]));

    $this->runInTenant($tenant, fn () => resolve(SendLeadEmails::class)->handle($lead, 'http://acme.ezsite.test/admin/leads'));

    Mail::assertNotSent(NewEnquiry::class);
    Mail::assertSent(EnquiryReceipt::class, 1);
});
