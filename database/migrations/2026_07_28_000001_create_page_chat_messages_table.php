<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The editor chat transcript, one row per message.
     *
     * Deliberately NOT laravel/ai's own `agent_conversations` tables: those
     * carry no tenant_id, so they would need an RLS exemption, and a transcript
     * full of a tenant's business copy is exactly the kind of row that must not
     * be reachable across tenants. Owning the table also lets a thread hang off
     * a PAGE (the SDK keys conversations by user only), which is the scope the
     * editor actually needs. The agent reads these rows back through the SDK's
     * `Conversational` contract.
     */
    public function up(): void
    {
        Schema::create('page_chat_messages', function (Blueprint $table): void {
            $table->id();

            // Its own tenant_id with a direct FK, per the single-hop RLS
            // convention (.claude/docs/tenancy.md) — the policy is generated
            // from this FK, and the composite index below leads with it so it
            // doubles as the policy's predicate index.
            $table->string('tenant_id');

            // The thread is the page: opening a page's editor resumes that
            // page's conversation. Deleting the page takes its chat with it —
            // the transcript is only meaningful next to the page it edits.
            $table->foreignId('page_id')
                ->constrained(config()->string('filament-fabricator.table_name', 'pages'))
                ->cascadeOnDelete();

            // Attribution only: teammates editing the same page share one
            // thread, so this is never used to scope reads. Nulled rather than
            // cascaded so removing a user doesn't punch holes in the history.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('role');
            $table->text('content');

            // What the assistant actually changed on that turn, for the "N
            // blocks changed" affordance in the UI. Null for user messages.
            $table->unsignedSmallInteger('changed_blocks')->nullable();

            $table->timestamps();

            // The only read pattern: one page's thread in chronological order.
            $table->index(['tenant_id', 'page_id', 'id']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_chat_messages');
    }
};
