<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Curator's media table, adapted to this app's RLS invariants: the stock
 * stub ships tenant_id as a nullable unsigned bigint with no foreign key
 * (aimed at Filament panel tenancy), which here would mean NO generated RLS
 * policy at all. Media is tenant-owned, so it follows the single-hop rule:
 * its OWN non-nullable uuid tenant_id with a direct, indexed FK to tenants.
 *
 * Deliberately timestamped ahead of `pages` and `businesses`: both create their
 * media foreign keys (`pages.seo_image_media_id`, `businesses.logo_media_id`)
 * inline, so this table has to exist first.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curator', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id')->index();
            $table->string('disk');
            $table->string('directory')->nullable();
            $table->string('visibility')->default('public');
            $table->string('name');
            $table->string('path')->index();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('size')->nullable();
            $table->string('type');
            $table->string('ext');
            $table->string('alt')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->text('caption')->nullable();
            $table->text('pretty_name')->nullable();
            $table->text('exif')->nullable();
            $table->longText('curations')->nullable();

            // The shared-library row this media was adopted from, if any
            // (App\Actions\Library\AdoptLibraryPhoto). This is the per-tenant
            // adoption dedup key: RLS scopes the lookup, so asking "has this
            // tenant already taken that photo?" is one indexed read.
            //
            // nullOnDelete, never cascade: curating a bad photo out of the
            // shared library must not delete the media rows — and therefore
            // the live page images — of every tenant that already used it.
            $table->foreignId('library_photo_id')->nullable()->constrained('library_photos')->nullOnDelete();

            // Stock-photo provenance (App\StockPhotos): where an imported
            // photo came from and whose credit it carries, copied down from
            // the library row on adoption so rendering and attribution never
            // need a join. Two tenants adopting the same photograph each get
            // their own file on their own tenant disk (see AdoptLibraryPhoto
            // for why the bytes are copied rather than shared), but the
            // DOWNLOAD happened once, into `library_photos`.
            $table->string('source_provider')->nullable();
            $table->string('source_id')->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->string('photographer_name')->nullable();
            $table->string('photographer_url', 2048)->nullable();
            $table->index(['source_provider', 'source_id']);

            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curator');
    }
};
