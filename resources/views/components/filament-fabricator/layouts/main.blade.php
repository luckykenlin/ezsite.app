@props(['page'])
<x-filament-fabricator::layouts.base :page="$page" :title="$page->title">
    <x-filament-fabricator::page-blocks :blocks="resolve(\App\Filament\Fabricator\SiteChrome::class)->headerBlocks()" />

    <x-filament-fabricator::page-blocks :blocks="$page->blocks" />

    <x-filament-fabricator::page-blocks :blocks="resolve(\App\Filament\Fabricator\SiteChrome::class)->footerBlocks()" />
</x-filament-fabricator::layouts.base>
