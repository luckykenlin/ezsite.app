<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The shared photo library: one cross-tenant, accumulating catalogue of
 * licensed stock photography that every tenant can draw from.
 *
 * Deliberately has NO `tenant_id` and therefore NO generated RLS policy —
 * this is a global resource, the same shape as `users`, not tenant-owned data.
 * It is listed in RlsPolicyTest's `$exempt` with that reason. Note this is NOT
 * the `no-rls` comment escape hatch (which is for tenant_id columns that must
 * be readable before tenancy resolves): there is simply no tenant column to
 * scope by.
 *
 * Tenant isolation is untouched: a tenant never reads these rows as media.
 * `App\Actions\Library\AdoptLibraryPhoto` copies a photo into the tenant's own
 * `curator` row, so block data keeps pointing at RLS-scoped media ids and
 * every downstream consumer (MediaResolver, SetBlockImage, CuratorPicker)
 * stays as it was.
 *
 * Timestamped ahead of `curator` for the same reason that migration is
 * timestamped ahead of `pages`: `curator.library_photo_id` constrains against
 * this table inline, so it has to exist first.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_photos', function (Blueprint $table): void {
            $table->id();

            // Provenance and credit, mirroring the columns on `curator`: the
            // (provider, source id) pair is the dedup key, and unlike the
            // per-tenant index on curator it is UNIQUE and GLOBAL — the whole
            // point of this table is that the second tenant to want a
            // photograph spends no download and no rate-limit token.
            $table->string('provider');
            $table->string('source_id')->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->string('photographer_name')->nullable();
            $table->string('photographer_url', 2048)->nullable();
            $table->unique(['provider', 'source_id']);

            // The origin bytes, on the shared (never tenant-suffixed) `library`
            // disk. They are downloaded once and kept forever; adoption copies
            // from here rather than re-fetching.
            $table->string('disk');
            $table->string('path')->unique();
            $table->string('name');
            $table->string('ext');
            $table->string('type');
            $table->unsignedInteger('size')->nullable();

            // Never null in practice: FindOrImportLibraryPhoto is the single
            // door into this table and always records the optimized image's
            // dimensions — and AdoptLibraryPhoto's OptimizedImage handoff
            // depends on them being present.
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->string('orientation')->nullable()->index();

            $table->string('alt')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();

            // What the AI and the panel filters actually search on.
            // `keywords` is a denormalised blob (alt + title + description +
            // category + tags) matched with a multi-term ILIKE, which is
            // sub-millisecond at this table's scale. When it stops being, the
            // upgrade is a generated tsvector column plus a GIN index — not a
            // pg_trgm extension we do not need yet.
            $table->string('category')->nullable()->index();
            $table->jsonb('tags')->nullable();
            $table->text('keywords')->nullable();
            $table->string('search_query')->nullable();

            // Colour metadata, extracted once on import
            // (App\Actions\Library\ExtractPhotoPalette). `is_dark` is not
            // decoration: it is what lets a caller ask for a photo that can
            // carry overlaid text, i.e. one usable as a full-bleed hero.
            $table->jsonb('palette')->nullable();
            $table->string('dominant_color', 7)->nullable()->index();
            $table->boolean('is_dark')->default(false);

            // Ascending usage is the default search order: reuse must not make
            // every generated site look the same, so the least-used photo is
            // offered first.
            $table->unsignedInteger('usage_count')->default(0);

            // The curation gate. Imports publish immediately (an unusable
            // library would break the draft pipeline); the central panel
            // unpublishes a bad photo instead of deleting it, so it is not
            // re-imported on the next matching search.
            $table->timestamp('published_at')->nullable()->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_photos');
    }
};
