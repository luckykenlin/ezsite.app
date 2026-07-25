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
        Schema::create('site_settings', function (Blueprint $table): void {
            $table->id();
            // One row per tenant; the unique index leads with tenant_id so it
            // doubles as the RLS predicate index.
            $table->string('tenant_id')->unique();

            // Site chrome, stored in Fabricator's block-entry shape
            // ([{type, data}]) so it renders through the same defensive loop
            // as page bodies. Null = "use the default chrome".
            $table->jsonb('header')->nullable();
            $table->jsonb('footer')->nullable();

            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
