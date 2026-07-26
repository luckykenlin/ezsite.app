<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            // The library-backed logo; legacy logo_path stays as the render
            // fallback (see Business::logoUrl()). Nulled when the media
            // entry is deleted, which drops the logo back to the fallback.
            $table->foreignId('logo_media_id')->nullable()->constrained('curator')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('logo_media_id');
        });
    }
};
