<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table): void {
            $table->id();

            // Its own tenant_id with a direct FK, per the single-hop RLS
            // convention (.claude/docs/tenancy.md) — the policy is generated
            // from this FK. Its predicate index is the composite at the bottom
            // of this table, which leads with tenant_id and so serves the RLS
            // `WHERE tenant_id = current_setting(...)` on its own.
            $table->string('tenant_id');

            // Which location the enquiry came in for, and which page it was
            // submitted from — both nulled rather than deleted with their
            // parent: a lead outlives the page that captured it.
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('page_id')
                ->nullable()
                ->constrained(config()->string('filament-fabricator.table_name', 'pages'))
                ->nullOnDelete();

            // Nullable: the low-friction surfaces (popup, inline signup) trade
            // a name for a higher completion rate and ask only for a reply
            // channel. The real invariant — at least one of email/phone — is
            // enforced in the request, not here, because either column alone
            // is legitimately null.
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('message')->nullable();

            // Which capture surface produced it — see App\Enums\LeadSource.
            $table->string('source')->default('contact_form');

            // First-touch attribution, captured on the landing request and
            // carried in the session until the visitor actually submits (see
            // App\Http\Middleware\RememberLeadAttribution). Without it the
            // operator can count leads but never tell which ad or which
            // surface earned them.
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_term')->nullable();
            $table->string('utm_content')->nullable();
            $table->string('referrer')->nullable();
            $table->string('landing_path')->nullable();

            $table->string('status')->default('new')->index();
            $table->timestamp('read_at')->nullable();

            // Kept for spam triage only.
            $table->string('ip_address')->nullable();

            $table->timestamps();

            // Exactly the inbox query: RLS injects `WHERE tenant_id = …` and
            // LeadsTable sorts newest-first, so this serves both halves from one
            // index. With tenant_id indexed alone Postgres has to sort the
            // tenant's entire lead history on every page of the list.
            $table->index(['tenant_id', 'created_at']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
