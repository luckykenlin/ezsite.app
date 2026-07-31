<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table($this->tableName(), function (Blueprint $table): void {
            // The page editor's UNSAVED working state, so a refresh (or a crash,
            // or a session expiry) no longer throws it away. Everything the
            // component cannot rebuild from `blocks` and `site_settings` lives
            // here — see App\Actions\Pages\SavePageEditorDraft for the shape.
            //
            // `json`, deliberately NOT `jsonb`: jsonb normalises object key order,
            // and the editor compares block `data` with `!==`, which in PHP IS
            // key-order sensitive. Three load-bearing comparisons depend on it —
            // the chrome-dirty check in commitSelectedBlock(), the anti-flicker
            // guard in selectBlock(), and "did this turn change anything" in
            // pollChatTurn(). `pages.blocks` is `json` for the same reason.
            $table->json('draft')->nullable();

            // When the draft was last written, for the restore notice ("unsaved
            // changes from 12 minutes ago") and for observability. Deliberately
            // NOT used to invalidate the draft against `updated_at`: that column
            // moves when the page is merely renamed, so such a guard would
            // silently discard good work.
            $table->timestamp('draft_updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table($this->tableName(), function (Blueprint $table): void {
            $table->dropColumn(['draft', 'draft_updated_at']);
        });
    }

    private function tableName(): string
    {
        return config()->string('filament-fabricator.table_name', 'pages');
    }
};
