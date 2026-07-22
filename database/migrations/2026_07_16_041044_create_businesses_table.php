<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table): void {
            $table->id();
            // Tenant-owned like posts/pages/locations: isolated by RLS, not app code.
            // The unique() index doubles as the index for the RLS-injected
            // `WHERE tenant_id = current_setting(...)` predicate, so no separate ->index() is needed.
            $table->string('tenant_id')->unique();

            $table->string('name');
            $table->string('slug');
            $table->string('category')->nullable();
            $table->string('tagline')->nullable();
            $table->text('description')->nullable();
            $table->string('logo_path')->nullable();

            $table->string('brand_primary')->nullable();
            $table->string('brand_secondary')->nullable();
            $table->string('brand_accent')->nullable();

            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('website_url')->nullable();

            $table->string('timezone')->nullable();
            $table->string('locale')->nullable();
            $table->string('currency')->nullable();

            $table->string('status')->default('draft');

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'slug']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
