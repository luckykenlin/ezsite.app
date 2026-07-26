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
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curator');
    }
};
