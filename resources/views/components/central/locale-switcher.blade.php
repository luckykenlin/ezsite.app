{{--
    The language switcher, in the header of every marketing page.

    Each label is written in the language it selects — never translated. A
    visitor who landed on the wrong language cannot read the word for the right
    one, so "English" stays "English" on the Chinese page.

    Real links, not a form or a script: this has to be the same URL the
    hreflang tags advertise, so a crawler and a person follow the same address.
    Both come out of App\Site\LocaleUrls for exactly that reason.
--}}
@use('App\Enums\Locale')
@props(['urls', 'current'])
<div class="flex items-center gap-2" role="group" aria-label="{{ __('marketing.nav.language') }}">
    @foreach (Locale::cases() as $locale)
        @if ($locale === $current)
            <span aria-current="true" class="font-semibold text-primary">{{ $locale->nativeLabel() }}</span>
        @else
            <a href="{{ $urls[$locale->value] }}" hreflang="{{ $locale->htmlLang() }}" class="opacity-60 hover:text-primary hover:opacity-100">
                {{ $locale->nativeLabel() }}
            </a>
        @endif
    @endforeach
</div>
