<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('email')->unique();

            // Which industry template this site was built from, recorded for
            // demo tenants AND for real signups — it is the only attribution
            // the funnel has, and "which template do people actually apply"
            // is the question the gallery gets rebuilt on.
            $table->string('template')->nullable();

            // Which design package renders this site — see App\Themes\Theme.
            // A string cast to the enum, not a DB enum, so a new theme needs
            // no migration; the default is the transitional theme that draws
            // every pre-theme site exactly as it looked.
            $table->string('theme')->default('default');

            // Which kind of business this site is for — see App\Enums\Vertical.
            // It decides the vocabulary ("Services", "Technicians"), which
            // themes are on offer and the schema.org type. Nullable with no
            // default: null is a real answer, "no vertical", which is what every
            // pre-pivot template site is.
            $table->string('vertical')->nullable();

            // The showcase tenants behind /templates. Indexed because the
            // central panel lists real tenants only, so every listing filters
            // on it.
            $table->boolean('is_demo')->default(false)->index();

            $table->timestamps();
            $table->json('data')->nullable();
        });
    }
};
