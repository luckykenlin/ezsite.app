<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The shape specified in docs/reviews-module.md §2, built now because the review
 * ASK is the one thing in the whole updates plan that earns a local business
 * customers with no content from the owner and no API from anybody.
 *
 * Review quantity, velocity and rating sit inside Google's own prominence factor.
 * Google Posts, by contrast, sit at #148 of ~187 in Whitespark's 2026 study. So
 * this three-day table is the highest-leverage thing here, and it is deliberately
 * NOT waiting for the rest of that module or for any API approval.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_requests', function (Blueprint $table): void {
            $table->id();
            // Its own tenant_id with a direct FK: the single-hop rule, so the
            // auto-generated RLS policy is a flat predicate rather than a
            // correlated subquery through locations.
            $table->string('tenant_id')->index();
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            // Which branch the review is for. Google reviews are per-LOCATION, which
            // is why a link belongs to one.
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();

            // A registry string, not a db enum — the same convention business
            // category and block type follow (see docs/reviews-module.md).
            // `qr` and `link` are what exists today; `sms` and `email` arrive with a
            // sending layer, and that drags TCPA / CAN-SPAM / A2P 10DLC in with it.
            $table->string('channel')->default('link');

            // Nullable because MVP does not send: a counter QR has no recipient, and
            // storing phone numbers we are not messaging would be collecting personal
            // data for no purpose.
            $table->string('recipient')->nullable();

            $table->string('status')->default('queued');

            // The short link's own id. Unique across the installation rather than per
            // tenant, because /r/{token} is resolved before anything else and a
            // collision would hand one tenant's customer to another's profile.
            $table->string('token')->unique();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamps();

            // The cockpit query: this tenant's asks by state.
            $table->index(['tenant_id', 'status']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_requests');
    }
};
