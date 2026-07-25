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
        Schema::table('pages', function (Blueprint $table): void {
            // Defaults to published so existing pages and manual creation stay
            // live; AI-generated drafts opt in to 'draft' and 404 publicly.
            $table->string('status')->default('published')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            $table->dropColumn('status');
        });
    }
};
