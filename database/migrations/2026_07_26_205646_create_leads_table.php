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
            // from this FK, and the index doubles as its predicate index.
            $table->string('tenant_id')->index();

            // Which location the enquiry came in for, and which page it was
            // submitted from — both nulled rather than deleted with their
            // parent: a lead outlives the page that captured it.
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('page_id')
                ->nullable()
                ->constrained(config()->string('filament-fabricator.table_name', 'pages'))
                ->nullOnDelete();

            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('message')->nullable();

            // Where it came from, so a future channel (phone widget, chat, an
            // imported list) doesn't need a schema change.
            $table->string('source')->default('contact_form');

            $table->string('status')->default('new')->index();
            $table->timestamp('read_at')->nullable();

            // Kept for spam triage only.
            $table->string('ip_address')->nullable();

            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
