{{--
    The central marketing site's section shell — a deliberate sibling of
    x-site.section, not a reuse of it. The tenant shell's tones are daisyUI
    semantic slots driven by SectionTone/SectionSpacing enums and a
    MotionStyle-controlled reveal marker; none of that machinery exists in
    central.css, whose whole palette is ink/paper/mist. Three tones and a
    handful of paddings cover every marketing page.
--}}
@props(['tone' => 'light', 'spacing' => 'normal'])
@php
    $toneClasses = match ($tone) {
        'dark' => 'bg-ink text-paper',
        'muted' => 'bg-mist text-ink',
        default => 'bg-paper text-ink',
    };

    $spacingClasses = match ($spacing) {
        'tall' => 'py-28 md:py-40',
        'tight' => 'py-14 md:py-20',
        'flush' => '',
        default => 'py-20 md:py-28',
    };
@endphp
<section {{ $attributes->class([$toneClasses, $spacingClasses]) }}>{{ $slot }}</section>
