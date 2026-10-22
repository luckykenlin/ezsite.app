<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_links', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');

            $table->string('kind'); // App\Enums\LinkKind
            $table->string('url', 2048);
            $table->unsignedInteger('sort')->default(0);

            $table->timestamps();

            // One link per kind: a salon has one Instagram and one Booksy page, and
            // the "Book now" button must not have to choose between two. Leads with
            // tenant_id, so it doubles as the RLS predicate index.
            $table->unique(['tenant_id', 'kind']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_links');
    }
};
