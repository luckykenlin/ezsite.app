<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            // A reservation is a lead with three extra facts, not a second
            // inbox: the pipeline (honeypot, error bags, notification, emails)
            // is identical, the operator's mental model is one list of people
            // wanting attention, and a `reservations` table would re-buy the
            // RLS policy, the write guard and the status machinery for no
            // behavioural difference. All three are nullable because every
            // other capture surface never sends them.
            //
            // Wall-clock values on purpose — never converted to UTC. "7pm
            // Friday" means 7pm at the restaurant for both the visitor and the
            // operator; a timestamp column would route the value through the
            // server timezone and back through a location timezone that can be
            // edited after the fact. The only timezone-aware moment is the
            // "not in the past" validation, which happens at capture time.
            $table->date('reserved_date')->nullable();
            $table->time('reserved_time')->nullable();
            $table->unsignedSmallInteger('party_size')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropColumn(['reserved_date', 'reserved_time', 'party_size']);
        });
    }
};
