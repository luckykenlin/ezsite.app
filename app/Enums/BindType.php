<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The kind of factual, tenant-owned record a page block may bind to.
 *
 * A block that binds *references* live business/location data (NAP, hours,
 * social links) instead of copying it into the block's `data`, per the hybrid
 * content model (docs/business-data-model.md). Narrative copy stays in the
 * block's own `data`; factual data is resolved from the bound record.
 *
 * The contract, registry, and renderer treat `bind` as a first-class capability;
 * live resolution happens at render time through
 * {@see \App\Filament\Fabricator\BindResolver} and
 * {@see \App\Filament\Fabricator\BlockRegistry::bindAttributes()}.
 */
enum BindType: string
{
    case Location = 'location';
    case Business = 'business';
}
