<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_members', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');

            $table->string('name', 80);
            // "Owner", "Senior nail tech" — free text, not an enum: titles are the
            // salon's own vocabulary.
            $table->string('role', 80)->nullable();
            $table->text('bio')->nullable();

            $table->foreignId('media_id')->nullable()->constrained('curator')->nullOnDelete();

            // A technician's own booking page (many keep a personal Booksy/Vagaro
            // profile), distinct from the salon's links in business_links.
            $table->string('booking_url')->nullable();
            // The handle without the "@" — the URL is derived, so it cannot drift.
            $table->string('instagram_handle', 60)->nullable();

            $table->unsignedInteger('sort')->default(0);

            $table->timestamps();

            // Leads with tenant_id: the RLS predicate index and the team block's
            // ordering in one.
            $table->index(['tenant_id', 'sort']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_members');
    }
};
