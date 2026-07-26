<?php

declare(strict_types=1);

namespace App\Filament\Tenant\Resources\PageResource\Actions;

use App\Models\Page as PageModel;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

/**
 * The fields that decide where a page lives: its slug, its parent, and the
 * resulting public URL. Shared verbatim by the "New page" and "Page settings"
 * modals — the only difference is whether the edited page excludes itself
 * from the parent options and from the slug's uniqueness check.
 */
final readonly class PageIdentityFields
{
    /**
     * @param  PageModel|null  $current  the page being edited, excluded from its own
     *                                   parent options and uniqueness check; null when creating
     */
    public function __construct(private ?PageModel $current = null) {}

    /**
     * The final public path a slug + parent combination resolves to — shown
     * live under the slug field so nested URLs are visible before saving.
     */
    public static function previewPath(mixed $parentId, mixed $slug): string
    {
        $prefix = '';

        if (is_numeric($parentId)) {
            $parent = PageModel::query()->find((int) $parentId);

            if ($parent !== null) {
                $prefix = mb_rtrim($parent->getUrl(), '/');
            }
        }

        $slug = is_string($slug) && mb_trim($slug) !== '' ? mb_trim($slug) : '/';

        return ($prefix.Str::start($slug, '/')) ?: '/';
    }

    /**
     * No leading/trailing slash (except the root slug "/") and unique within
     * the chosen parent.
     */
    public function slug(): TextInput
    {
        return TextInput::make('slug')
            ->required()
            ->live(onBlur: true)
            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                if ($value !== '/' && is_string($value) && (str_starts_with($value, '/') || str_ends_with($value, '/'))) {
                    $fail('The slug cannot start or end with a slash.');
                }
            })
            ->unique(
                table: PageModel::class,
                column: 'slug',
                ignorable: $this->current,
                modifyRuleUsing: function (Unique $rule, Get $get): Unique {
                    $parent = $get('parent_id');

                    return $rule->where('parent_id', is_numeric($parent) ? (int) $parent : null);
                },
            )
            ->helperText(fn (Get $get): string => 'URL: '.self::previewPath($get('parent_id'), $get('slug')));
    }

    public function parent(): Select
    {
        return Select::make('parent_id')
            ->label('Parent page')
            ->options(fn (): array => $this->parentOptions())
            ->live()
            ->placeholder('None');
    }

    /**
     * @return array<int|string, string>
     */
    private function parentOptions(): array
    {
        return PageModel::query()
            ->when(
                $this->current instanceof PageModel,
                fn (Builder $query): Builder => $query->whereKeyNot($this->current?->id),
            )
            ->orderBy('title')
            ->pluck('title', 'id')
            ->map(static fn (mixed $title): string => is_string($title) ? $title : '')
            ->all();
    }
}
