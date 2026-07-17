<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config()->string('filament-fabricator.table_name', 'pages'), function (Blueprint $table): void {
            $table->dropUnique(['slug']);

            // NULLS NOT DISTINCT so root pages (parent_id IS NULL) still collide:
            // Postgres' default NULLS DISTINCT would exempt every NULL parent_id
            // from the constraint, silently allowing duplicate root slugs per tenant.
            $table->unique(['tenant_id', 'slug', 'parent_id'])->nullsNotDistinct();
        });
    }

    public function down(): void
    {
        Schema::table(config()->string('filament-fabricator.table_name', 'pages'), function (Blueprint $table): void {
            $table->dropUnique(['tenant_id', 'slug', 'parent_id']);
            $table->unique(['slug']);
        });
    }
};
