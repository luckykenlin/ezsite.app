<?php

declare(strict_types=1);

use App\Jobs\SendLeadEmailsJob;
use App\Mail\EnquiryReceipt;
use App\Mail\NewEnquiry;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('the job rebuilds the tenant context the emails are written from', function (): void {
    // Dispatched from CENTRAL context on purpose: this is what a queue worker
    // sees. Without TenantAware's RunInTenant wrapper the RLS-scoped businesses
    // read finds nothing and the site loses its name in its own email — and the
    // lead lookup below would find no row at all.
    Mail::fake();

    $tenant = Tenant::factory()->create();
    $this->createTenantBusiness($tenant, ['name' => 'Golden Dragon']);
    $member = User::factory()->memberOf($tenant)->create();

    $lead = $this->runInTenant($tenant, fn (): Lead => Lead::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Mei Chen',
        'email' => 'mei@example.com',
    ]));

    dispatch_sync(new SendLeadEmailsJob($tenant->id, $lead->id, 'http://acme.ezsite.test/admin/leads'));

    Mail::assertSent(NewEnquiry::class, fn (NewEnquiry $mail): bool => $mail->hasTo($member->email)
        && $mail->envelope()->subject === 'New enquiry from Mei Chen'
        && $mail->envelope()->from?->name === 'Golden Dragon');

    Mail::assertSent(EnquiryReceipt::class, fn (EnquiryReceipt $mail): bool => $mail->hasTo('mei@example.com'));
});

test('an enquiry deleted before delivery is dropped quietly', function (): void {
    // A retry after a provider outage can land minutes late, and there is
    // nothing to report about a lead that no longer exists — the alternative is
    // a job that fails three times and lands in failed_jobs for no reason.
    Mail::fake();

    $tenant = Tenant::factory()->create();
    User::factory()->memberOf($tenant)->create();

    dispatch_sync(new SendLeadEmailsJob($tenant->id, 404, 'http://acme.ezsite.test/admin/leads'));

    Mail::assertNothingSent();
});
