<?php

declare(strict_types=1);

use App\Enums\LeadSource;
use App\Mail\NewEnquiry;
use App\Mail\SiteMailIdentity;
use App\Models\Lead;
use App\Models\Tenant;

test('the operator can reach the enquirer by hitting reply', function (): void {
    // The detail that decides whether this email converts or merely informs:
    // Reply goes to the customer, not back to the site it came from.
    $lead = Lead::factory()->make([
        'name' => 'Mei Chen',
        'email' => 'mei@example.com',
        'phone' => '+1 555 0100',
        'message' => "Do you take walk-ins?\nWe would be four.",
    ]);

    $mailable = new NewEnquiry(
        $lead,
        new SiteMailIdentity('Golden Dragon', '#b91c1c'),
        'http://acme.ezsite.test/admin/leads',
    );

    $mailable->assertHasSubject('New enquiry from Mei Chen')
        ->assertFrom(config()->string('mail.from.address'), 'Golden Dragon')
        ->assertHasReplyTo('mei@example.com', 'Mei Chen')
        ->assertSeeInHtml('+1 555 0100')
        ->assertSeeInHtml('mei@example.com')
        ->assertSeeInHtml('Do you take walk-ins?')
        ->assertSeeInHtml('Contact form')
        ->assertSeeInHtml('http://acme.ezsite.test/admin/leads', escape: false)
        ->assertSeeInHtml('Reply to this email to answer Mei Chen directly.')
        // The plain-text alternative is what a spam filter compares against and
        // what a watch reads out; it must carry the same facts, not a stub.
        ->assertSeeInText('Phone: +1 555 0100')
        ->assertSeeInText('Do you take walk-ins?')
        ->assertSeeInText('Open the inbox: http://acme.ezsite.test/admin/leads');
});

test('an enquiry from a phone-only surface says so instead of inviting a reply', function (): void {
    $lead = Lead::factory()->make([
        'name' => null,
        'email' => null,
        'phone' => '+1 555 0100',
        'message' => null,
        'source' => LeadSource::Popup,
    ]);

    $mailable = new NewEnquiry($lead, new SiteMailIdentity('Golden Dragon'), 'http://acme.ezsite.test/admin/leads');

    expect($mailable->envelope()->replyTo)->toBeEmpty();

    $mailable->assertHasSubject('New enquiry from +1 555 0100')
        ->assertSeeInHtml('left no email address')
        ->assertSeeInHtml('Popup')
        ->assertDontSeeInHtml('Reply to this email');
});

test('the enquiry names the page that earned it', function (): void {
    // "Which page produced this" is the question that tells an operator where
    // their traffic converts, and it is one join away in the inbox but free here.
    $tenant = Tenant::factory()->create();
    $page = $this->createTenantPage($tenant, [], slug: '/contact');

    // Rendered INSIDE the tenant context, the way the job does it: the view
    // lazy-loads `$lead->page`, and RunInTenant purges the `tenant` connection
    // on the way out, so a lead carried back to central context cannot resolve
    // its own relations any more.
    $this->runInTenant($tenant, function () use ($tenant, $page): void {
        $lead = Lead::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Mei Chen',
            'page_id' => $page->id,
        ]);

        new NewEnquiry($lead, new SiteMailIdentity('Golden Dragon'), 'http://acme.ezsite.test/admin/leads')
            ->assertSeeInHtml('/contact')
            ->assertSeeInText('(/contact)');
    });
});
