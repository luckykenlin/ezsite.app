<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table): void {
            $table->id();
            // One row per tenant; the unique index leads with tenant_id so it
            // doubles as the RLS predicate index.
            $table->string('tenant_id')->unique();

            // Site chrome, stored in Fabricator's block-entry shape
            // ([{type, data}]) so it renders through the same defensive loop
            // as page bodies. Null = "use the default chrome".
            //
            // `json`, deliberately NOT `jsonb`, same as `pages.blocks` and
            // `pages.draft`: jsonb does not preserve object key order (it stores
            // keys sorted by length then bytewise) and the page editor compares
            // block `data` with `!==`, which in PHP IS key-order sensitive. Under
            // jsonb the round trip came back reordered, so merely SELECTING the
            // header in the inspector flagged chrome dirty, reloaded the canvas,
            // and a later Save rewrote settings nobody had edited — breaking the
            // "clicking around an unedited page must not flicker" invariant that
            // `selectBlock()` guards with `$before !== [$blocks, $chrome]`.
            // Nothing here needs jsonb's indexing or containment operators; these
            // columns are only ever read whole, by primary key.
            $table->json('header')->nullable();
            $table->json('footer')->nullable();

            // The site-wide lead-capture surfaces — the offer popup and the
            // sticky mobile call bar — as `{popup: {...}, call_bar: {...}}`.
            //
            // ONE column for both, unlike header/footer above: those are two
            // independently-rendered chrome slots holding block-entry arrays,
            // whereas these are settings objects for a single feature area
            // that is configured on one screen and read through one value
            // object (App\Site\SiteCapture). Null = both switched off.
            $table->json('capture')->nullable();

            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
