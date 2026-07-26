<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table($this->tableName(), function (Blueprint $table): void {
            // Per-page search/share overrides. All nullable: an empty column
            // means "derive it" (page title, business tagline, business logo)
            // — see App\Actions\BuildPageSeoData for the fallback chains.
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();

            // The share (OpenGraph) image as a media-library reference, the
            // same currency block data uses. Nulled rather than left dangling
            // when the media entry is deleted.
            $table->foreignId('seo_image_media_id')
                ->nullable()
                ->constrained('curator')
                ->nullOnDelete();

            $table->boolean('is_indexable')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table($this->tableName(), function (Blueprint $table): void {
            $table->dropConstrainedForeignId('seo_image_media_id');
            $table->dropColumn(['seo_title', 'seo_description', 'is_indexable']);
        });
    }

    private function tableName(): string
    {
        return config()->string('filament-fabricator.table_name', 'pages');
    }
};
