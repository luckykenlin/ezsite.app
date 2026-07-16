<?php

declare(strict_types=1);

arch('all app code declares strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('no debugging statements are left behind')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'var_export', 'print_r'])
    ->not->toBeUsed();

arch('models are final classes')
    ->expect('App\Models')
    ->toBeClasses()
    ->toBeFinal();

arch('actions are final and expose a single handle entrypoint')
    ->expect('App\Actions')
    ->toBeClasses()
    ->toBeFinal()
    ->toHaveMethod('handle');
