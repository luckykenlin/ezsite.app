<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PhotoCategory;
use App\StockPhotos\PhotoOrientation;
use Database\Factories\LibraryPhotoFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * One photograph in the shared, cross-tenant photo library.
 *
 * A CENTRAL model, so deliberately no {@see \App\Tenancy\RequiresTenantContext}
 * and no `tenant_id`: the whole point is that every tenant draws from one
 * accumulating catalogue. It is the only model here that is written from
 * central context (the `library:import` command runs with no tenant at all).
 *
 * The isolation boundary is crossed exactly once, in
 * {@see \App\Actions\Library\AdoptLibraryPhoto}: a tenant that wants a photo
 * gets its OWN {@see Media} row (RLS-scoped, its own file), pointing back here
 * through `curator.library_photo_id`. So nothing downstream of a block's
 * `image_id` ever sees a library photo — there is still one media id space.
 *
 * @property int $id
 * @property string $provider
 * @property string|null $source_id
 * @property string|null $source_url
 * @property string|null $photographer_name
 * @property string|null $photographer_url
 * @property string $disk
 * @property string $path
 * @property string $name
 * @property string $ext
 * @property string $type
 * @property int|null $size
 * @property int $width
 * @property int $height
 * @property PhotoOrientation|null $orientation
 * @property string|null $alt
 * @property string|null $title
 * @property string|null $description
 * @property PhotoCategory|null $category
 * @property array<int, string>|null $tags
 * @property string|null $keywords
 * @property string|null $search_query
 * @property array<int, string>|null $palette
 * @property string|null $dominant_color
 * @property bool $is_dark
 * @property int $usage_count
 * @property Carbon|null $published_at
 *
 * @method static LibraryPhotoFactory factory($count = null, $state = [])
 */
final class LibraryPhoto extends Model
{
    /** @use HasFactory<LibraryPhotoFactory> */
    use HasFactory;

    /**
     * Every tenant media row adopted from this photo, across all tenants.
     * Readable only from central context — RLS narrows it to the current
     * tenant's single adoption inside a tenant context, which is exactly what
     * {@see \App\Actions\Library\AdoptLibraryPhoto} wants.
     *
     * @return HasMany<Media, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(Media::class, 'library_photo_id');
    }

    /**
     * The origin file's public URL. Used ONLY by the browse-and-adopt panels,
     * to preview a photo before any tenant owns a copy of it; rendered pages
     * always go through the adopting tenant's own media row.
     */
    public function previewUrl(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    /**
     * `keywords` is a DERIVED column, rebuilt on every save rather than written
     * by whoever creates the row. That way a curator who fixes a photo's
     * description in the central panel also fixes what it can be found by — the
     * alternative is a catalogue whose search index quietly disagrees with what
     * it displays.
     *
     * The category value is folded in so that filtering by category and
     * free-text searching for "interior" agree with each other. The original
     * search query is too: what someone was looking for when this photo
     * answered is often the best description anyone will ever write for it.
     */
    protected static function booted(): void
    {
        self::saving(function (self $photo): void {
            $photo->keywords = mb_strtolower(mb_trim(implode(' ', array_filter([
                $photo->search_query,
                $photo->alt,
                $photo->title,
                $photo->description,
                $photo->category?->value,
                implode(' ', $photo->tags ?? []),
            ]))));
        });
    }

    /**
     * Curated-in photos only. Everything that searches the library goes
     * through this — an unpublished photo stays in the catalogue (so it is not
     * re-imported by the next matching search) but is never offered again.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->whereNotNull('published_at');
    }

    /**
     * Loose keyword match over the denormalised `keywords` blob: ANY term hits.
     * OR rather than AND because photo search is a "surprise me with something
     * close" query, not a filter — "sunlit cafe terrace" must not return
     * nothing just because no photo is all three.
     *
     * …but OR alone decides only WHETHER a photo is a candidate, never how good
     * a one it is, and the caller's least-used-first rule
     * ({@see \App\Actions\Library\FindLibraryPhotos}) then actively prefers the
     * worst of them: a spa photo whose keywords happen to contain "interior"
     * matches "wood fired pizza shop interior" on that single weak word, and
     * because it is newly imported with `usage_count` 0 it sorts ahead of every
     * photo that matched three terms. That is not hypothetical — it put massage
     * tables in a pizzeria's gallery on the demo sites, and it would do the same
     * to a tenant the day the library holds anything from a neighbouring trade.
     *
     * So the scope ranks as well as filters: how many terms a photo hits comes
     * first, and the caller's ordering becomes the tiebreak among equally
     * relevant photos — which is where spreading demand across the catalogue was
     * always the point.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function matching(Builder $query, string $terms): void
    {
        $words = array_values(array_filter(
            preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($terms)) ?: [],
            fn (string $word): bool => mb_strlen($word) > 2,
        ));

        if ($words === []) {
            return;
        }

        $query->where(function (Builder $query) use ($words): void {
            foreach ($words as $word) {
                $query->orWhere('keywords', 'ilike', '%'.$word.'%');
            }
        });

        // A whole-word hit outscores a substring one, because `%dry%` also
        // matches "hairdryer" and `%art%` matches "started" — and on a query
        // like "dry pedicure nail salon" that accidental hit is worth exactly
        // as much as the real ones. Terms are already `[\p{L}\p{N}]+` (the
        // split above drops everything else), so they need no regex escaping.
        $score = [];
        $bindings = [];

        foreach ($words as $word) {
            $score[] = '(case when keywords ~* ? then 2 when keywords ilike ? then 1 else 0 end)';
            $bindings[] = '\m'.$word.'\M';
            $bindings[] = '%'.$word.'%';
        }

        $query->orderByRaw(implode(' + ', $score).' desc', $bindings);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'orientation' => PhotoOrientation::class,
            'category' => PhotoCategory::class,
            'tags' => 'array',
            'palette' => 'array',
            'is_dark' => 'boolean',
            'published_at' => 'datetime',
        ];
    }
}
