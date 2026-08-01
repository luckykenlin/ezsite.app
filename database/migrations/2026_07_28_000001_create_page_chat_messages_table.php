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

            // Whether this assistant turn is an apology for a turn that never
            // finished (provider failure, dead worker). Persisted because the
            // retry affordance renders from the TRANSCRIPT — the cache entry
            // carrying the same fact dies with the poll, but the apology
            // bubble survives a reload and must still offer the retry.
            $table->boolean('failed')->default(false);

            // The editor's on-screen blocks at the moment this turn's edits
            // were APPLIED (key-stripped, persisted shape) — what "Revert this
            // edit" restores. Captured editor-side rather than from the job
            // payload, because the operator may have edited mid-turn and a
            // revert must return to what they were actually looking at. Null
            // for user turns and for answers that changed nothing.
            $table->json('blocks_before')->nullable();

            // The turn's tool-call summary lines (ChatActivity), so the agent's
            // conversation memory carries WHAT it changed, not only what it
            // said about it — its prose routinely under-describes its edits,
            // and a model that cannot recall removing a block re-adds it.
            // Null for user turns and tool-less answers.
            $table->json('activity')->nullable();

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
