<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gallery_images', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');

            // Cascade, not null: the image IS the row. A gallery entry whose photo
            // is gone has nothing left to show.
            $table->foreignId('media_id')->constrained('curator')->cascadeOnDelete();
            $table->string('caption', 160)->nullable();

            // Optional link to a service category, so the work wall can filter
            // "Acrylics" vs "Nail art". Nulled when the category goes — the photo
            // is still the salon's work.
            $table->foreignId('catalog_category_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedInteger('sort')->default(0);

            $table->timestamps();

            $table->index(['tenant_id', 'sort']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_images');
    }
};
