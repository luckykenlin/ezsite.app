<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            // Indexed: RLS injects `WHERE tenant_id = current_setting(...)` into every
            // query, so an unindexed tenant_id means a sequential scan on the shared DB.
            $table->string('tenant_id')->index();

            // Both nullable and both here for the same reason: an update belongs to
            // the site, but a closure or an offer usually belongs to ONE branch, and
            // the eventual Google Business Profile connector posts per LOCATION, not
            // per business.
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title');
            // Filled on `creating` by #[Sluggable] on the model, so the ten raw
            // `Post::query()->create(['tenant_id', 'title'])` call sites in the
            // tenancy tests keep working without passing one.
            $table->string('slug');

            // The one line every surface starts from: the card on the home page, the
            // meta description, the share text, and (later) the Google post summary.
            $table->string('excerpt', 300)->nullable();

            // PLAIN TEXT, never HTML — rendered with `{{ }}` + nl2br(e()). These pages
            // are built from tenant-authored data on a shared domain, so unescaped
            // output is stored XSS; the same reasoning is documented on PageBlocks\Prose.
            $table->text('body')->nullable();

            // Defaults are load-bearing, not tidiness. Ten call sites across
            // RlsIsolationTest, TenantWriteContextTest and tests/Fixtures create a Post
            // with nothing but tenant_id and title; a NOT NULL column with no default
            // would break all of them.
            $table->string('kind')->default('update');   // App\Enums\PostKind
            $table->string('status')->default('draft');  // App\Enums\PostStatus

            $table->timestampTz('published_at')->nullable();

            // The window. Required for Offer AND Event — Google wants the `event{}`
            // object for both — and what an expired update is computed from, since
            // there is no cron to stamp a status.
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();

            $table->string('cta_action')->nullable();          // App\Enums\PostCtaAction
            $table->string('cta_url', 2048)->nullable();       // guarded by App\Site\UrlScheme

            $table->string('offer_coupon_code')->nullable();
            $table->text('offer_terms')->nullable();

            // Media as a reference, the currency every block already uses: an integer
            // id resolved to a URL at render time by App\Site\MediaResolver. Nulled
            // rather than left dangling when the media entry goes.
            $table->foreignId('cover_media_id')->nullable()->constrained('curator')->nullOnDelete();
            // The generated 1200x630 OpenGraph rendition. Its own column because it is
            // derived from the cover and regenerated when the cover changes, and
            // because a share scraper needs a JPEG where the site wants WebP.
            $table->foreignId('share_card_media_id')->nullable()->constrained('curator')->nullOnDelete();

            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->boolean('is_indexable')->default(true);

            // A byline the operator types ("Mei, owner"), deliberately NOT a users FK:
            // the person who signs an update is rarely the account that saved it, and a
            // salon that adds a second staff login should not retitle its history.
            $table->string('author_name')->nullable();

            // Which curated prompt produced this update, so the 96 hand-written ideas
            // can be pruned on evidence rather than taste. Null for anything the
            // operator started from scratch.
            $table->string('idea_key')->nullable();

            $table->timestamps();

            // Leads with tenant_id so it doubles as the RLS predicate index, which is
            // what RlsIndexTest checks for. No NULLS NOT DISTINCT dance here: unlike
            // pages there is no nullable parent in the key.
            $table->unique(['tenant_id', 'slug']);
            // The feed, the home-page block and the nav all ask the same question:
            // this tenant's published updates, newest first.
            $table->index(['tenant_id', 'status', 'published_at']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
