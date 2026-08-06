{{--
    What stands in for a template screenshot that has not been captured yet:
    the template's own two brand hexes as a gradient — at least the right
    colours for the site behind the link. Inline style because the hexes come
    from the template definitions (nothing user-authored reaches the
    attribute) and the compiled stylesheet cannot carry arbitrary values.
--}}
@props(['definition', 'label', 'labelClass' => 'site-h3'])
<div
    {{ $attributes->class(['flex w-full items-end']) }}
    style="background-image: linear-gradient(135deg, {{ $definition->brandPrimary }}, {{ $definition->brandAccent }});"
>
    <span class="{{ $labelClass }} font-heading text-white drop-shadow">{{ $label }}</span>
</div>
