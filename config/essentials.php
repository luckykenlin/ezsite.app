<?php

declare(strict_types=1);

use NunoMaduro\Essentials\Configurables\ImmutableDates;
use NunoMaduro\Essentials\Configurables\Unguard;

return [
    Unguard::class => true,
    ImmutableDates::class => true,
];
