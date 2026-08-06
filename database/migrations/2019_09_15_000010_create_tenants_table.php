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

            // The showcase tenants behind /templates. Indexed because the
            // central panel lists real tenants only, so every listing filters
            // on it.
            $table->boolean('is_demo')->default(false)->index();

            $table->timestamps();
            $table->json('data')->nullable();
        });
    }
};
