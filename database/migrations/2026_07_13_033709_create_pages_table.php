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
