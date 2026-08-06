<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A saved version of a page's blocks, one row per Save.
     *
     * `PageEditor::persistBlocks()` is a destructive in-place update, so a bad
     * Save — an accidental delete, an assistant rewrite the operator approved too
     * quickly — had no route back. The `draft` column added alongside this covers
     * everything BEFORE a Save; this covers everything after.
     *
     * Blocks only, deliberately. Site chrome lives in `site_settings` and is
     * shared by every page, so rolling one page back must not drag the whole
     * site's header with it. Page settings (title, slug, SEO) are written
     * separately and are not what gets destroyed by a bad block edit.
     *
     * `json`, not `jsonb`, for the reason `pages.blocks` and `pages.draft` are:
     * jsonb does not preserve object key order, and the editor compares block
     * `data` with `!==`, which in PHP is key-order sensitive.
     */
    public function up(): void
    {
        Schema::create('page_revisions', function (Blueprint $table): void {
            $table->id();

            // Its own tenant_id with a direct FK, per the single-hop RLS
            // convention (.claude/docs/tenancy.md) — the policy is generated from
            // this FK, and the composite index below leads with it so it doubles
            // as the policy's predicate index.
            $table->string('tenant_id');

            // History belongs to the page and dies with it: a revision of a page
            // that no longer exists cannot be restored into anything.
            $table->foreignId('page_id')
                ->constrained(config()->string('filament-fabricator.table_name', 'pages'))
                ->cascadeOnDelete();

            // Attribution only — "saved by" in the history list. Nulled rather
            // than cascaded so removing a user does not punch holes in the
            // history, exactly as page_chat_messages does.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->json('blocks');

            // An operator-given name ("Launch version", "Before the rewrite").
            // Named versions are EXEMPT from pruning — the whole point of
            // naming one is that a busy session's thirty saves cannot silently
            // push it out of the window.
            $table->string('label', 60)->nullable();

            // No updated_at: a revision's BLOCKS are a fact about a moment and
            // are never edited. The label may be set later, but it is
            // presentation, not history.
            $table->timestamp('created_at')->nullable();

            // The only read pattern: one page's history, newest first.
            $table->index(['tenant_id', 'page_id', 'id']);

            $table->foreign('tenant_id')->references('id')->on('tenants')->onUpdate('cascade')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_revisions');
    }
};
