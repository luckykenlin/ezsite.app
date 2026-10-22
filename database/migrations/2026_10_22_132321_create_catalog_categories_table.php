<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');

            // "Manicures", "Acrylics", "Add-ons" — a heading on the services page,
            // so short enough to sit on one line of a menu.
            $table->string('name', 80);
            $table->text('description')->nullable();
            $table->unsignedInteger('sort')->default(0);

            $table->timestamps();

            // Two "Manicures" headings on one menu is always a mistake. Leads with
            // tenant_id, so it doubles as the RLS predicate index.
            $table->unique(['tenant_id', 'name']);
            // The menu reads this tenant's categories in operator order.
            $table->index(['tenant_id', 'sort']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_categories');
    }
};
