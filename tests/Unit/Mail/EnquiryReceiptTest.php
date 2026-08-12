<?php

declare(strict_types=1);

use App\Mail\EnquiryReceipt;
use App\Mail\SiteMailIdentity;
use App\Models\Lead;

test('the visitor gets their own message back, and a way to chase it', function (): void {
    $lead = Lead::factory()->make([
        'name' => 'Mei Chen',
        'email' => 'mei@example.com',
        'message' => 'Do you take walk-ins?',
    ]);

    new EnquiryReceipt($lead, new SiteMailIdentity('Golden Dragon', '#b91c1c', 'hello@golden.test', '+1 555 0199'))
        ->assertHasSubject('Thanks for contacting Golden Dragon')
        ->assertFrom(config()->string('mail.from.address'), 'Golden Dragon')
        // Their reply has to reach the business, not our sending domain.
        ->assertHasReplyTo('hello@golden.test', 'Golden Dragon')
        ->assertSeeInHtml('Hi Mei Chen')
        ->assertSeeInHtml('Do you take walk-ins?')
        ->assertSeeInHtml('+1 555 0199')
        ->assertSeeInText('If it is urgent, call us on +1 555 0199.');
});

test('a reservation receipt repeats the table that was asked for', function (): void {
    // The one fact the visitor wants to see confirmed back at them — a typo'd
    // date is cheaper to catch here than at the door on Saturday night.
    $lead = Lead::factory()->reservation()->make([
        'name' => 'Mei Chen',
        'email' => 'mei@example.com',
        'message' => null,
        'reserved_date' => '2026-08-15',
        'reserved_time' => '19:00',
        'party_size' => 4,
    ]);

    new EnquiryReceipt($lead, new SiteMailIdentity('Golden Dragon'))
        ->assertSeeInHtml('You asked for')
        ->assertSeeInHtml('Sat 15 Aug, 19:00 · party of 4')
        ->assertSeeInText('You asked for Sat 15 Aug, 19:00 · party of 4');
});

test('a site with no profile filled in still sends a coherent receipt', function (): void {
    // The state a tenant is in before anyone opens the Business profile page:
    // no phone to offer and nowhere to reply to. The message must still stand.
    $lead = Lead::factory()->make(['name' => 'Mei Chen', 'email' => 'mei@example.com', 'message' => null]);

    $mailable = new EnquiryReceipt($lead, new SiteMailIdentity('Golden Dragon'));

    expect($mailable->envelope()->replyTo)->toBeEmpty();

    $mailable->assertSeeInHtml('Hi Mei Chen')
        ->assertDontSeeInHtml('What you sent us')
        ->assertDontSeeInHtml('If it is urgent');
});
