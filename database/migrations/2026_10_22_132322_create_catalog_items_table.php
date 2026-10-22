<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_items', function (Blueprint $table): void {
            $table->id();
            // Its own tenant_id even though the category already has one — the
            // single-hop rule (tenancy.md): a flat RLS predicate, not a subquery
            // through catalog_categories.
            $table->string('tenant_id');
            $table->foreignId('catalog_category_id')->constrained()->cascadeOnDelete();

            $table->string('name', 120);
            $table->text('description')->nullable();

            // Integer cents, never a float. Null means "ask for price", which is a
            // real answer for a custom nail-art set, not missing data.
            $table->unsignedInteger('price_cents')->nullable();
            // "from $65": the listed price is the floor (length, shape, art).
            $table->boolean('price_is_from')->default(false);
            // Nullable because a vertical without durations (a restaurant menu)
            // shares this table — see App\Enums\Vertical::usesDuration().
            $table->unsignedSmallInteger('duration_minutes')->nullable();

            // A list of {name, price_cents} — "French tip +$10". Inline rather than
            // a child table: an add-on has no identity of its own, is never
            // queried across items, and is edited as part of its service.
            $table->jsonb('add_ons')->default('[]');
            // Free-form labels ("vegan polish", "popular") — a list of strings.
            $table->jsonb('tags')->default('[]');

            // Same media reference every block uses; nulled, not dangling, when the
            // library entry is deleted.
            $table->foreignId('media_id')->nullable()->constrained('curator')->nullOnDelete();

            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort')->default(0);

            $table->timestamps();

            // The services page reads one category's items in operator order; leads
            // with tenant_id so it doubles as the RLS predicate index.
            $table->index(['tenant_id', 'catalog_category_id', 'sort']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_items');
    }
};
