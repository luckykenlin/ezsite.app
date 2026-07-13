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
            $table->unique(['tenant_id', 'slug', 'parent_id']);
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
