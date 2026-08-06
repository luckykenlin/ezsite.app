<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config()->string('filament-fabricator.table_name', 'pages');

        Schema::create($tableName, function (Blueprint $table) use ($tableName): void {
            $table->id();
            $table->string('tenant_id');
            $table->string('title')->index();
            $table->string('slug');
            $table->string('layout')->index();
            $table->json('blocks');
            $table->string('status')->default('published')->index();
            $table->foreignId('parent_id')->nullable()->constrained($tableName)->cascadeOnDelete()->cascadeOnUpdate();

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

            // The page editor's UNSAVED working state, so a refresh (or a crash,
            // or a session expiry) no longer throws it away. Everything the
            // component cannot rebuild from `blocks` and `site_settings` lives
            // here — see App\Actions\Pages\SavePageEditorDraft for the shape.
            //
            // `json`, deliberately NOT `jsonb`: jsonb normalises object key order,
            // and the editor compares block `data` with `!==`, which in PHP IS
            // key-order sensitive. Three load-bearing comparisons depend on it —
            // the chrome-dirty check in commitSelectedBlock(), the anti-flicker
            // guard in selectBlock(), and "did this turn change anything" in
            // pollChatTurn(). `pages.blocks` is `json` for the same reason.
            $table->json('draft')->nullable();

            // When the draft was last written, for the restore notice ("unsaved
            // changes from 12 minutes ago") and for observability. Deliberately
            // NOT used to invalidate the draft against `updated_at`: that column
            // moves when the page is merely renamed, so such a guard would
            // silently discard good work.
            $table->timestamp('draft_updated_at')->nullable();

            $table->timestamps();

            // NULLS NOT DISTINCT so root pages (parent_id IS NULL) still collide:
            // Postgres' default NULLS DISTINCT would exempt every NULL parent_id
            // from the constraint, silently allowing duplicate root slugs per tenant.
            $table->unique(['tenant_id', 'slug', 'parent_id'])->nullsNotDistinct();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config()->string('filament-fabricator.table_name', 'pages'));
    }
};
