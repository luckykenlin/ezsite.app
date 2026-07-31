<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * `jsonb` → `json` for the site chrome entries.
     *
     * jsonb does not preserve object key order — it stores keys sorted by length
     * then bytewise — and the page editor compares block `data` with `!==`, which
     * in PHP IS key-order sensitive. The round trip therefore came back reordered
     * and every comparison against form-ordered state reported a difference:
     *
     *   stored  {"cta_url":…,"variant":…,"cta_label":…}
     *   form    {"variant":…,"cta_label":…,"cta_url":…}
     *   ==  true      (same content)
     *   === false     (different order)  ← what the editor asked
     *
     * The visible symptom: after saving site chrome once, merely SELECTING the
     * header in the inspector flagged chrome dirty and reloaded the canvas, and a
     * subsequent Save rewrote settings nobody had edited. It also broke the
     * documented "clicking around an unedited page must not flicker" invariant,
     * since `selectBlock()` guards the reload on `$before !== [$blocks, $chrome]`.
     *
     * `pages.blocks` and `pages.draft` are already `json` for this reason; this
     * brings the last stored block shape into line. Nothing here needs jsonb's
     * indexing or containment operators — these columns are only ever read whole,
     * by primary key.
     */
    public function up(): void
    {
        $this->cast('json');
    }

    public function down(): void
    {
        $this->cast('jsonb');
    }

    private function cast(string $type): void
    {
        foreach (['header', 'footer'] as $column) {
            DB::statement(sprintf(
                'ALTER TABLE site_settings ALTER COLUMN %s TYPE %s USING %s::text::%s',
                $column,
                $type,
                $column,
                $type,
            ));
        }
    }
};
