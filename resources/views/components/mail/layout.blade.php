{{--
    The shell every outgoing tenant email renders inside.

    Hand-written table markup with inline styles, NOT Tailwind and not Laravel's
    markdown mail components: email clients strip <style> blocks (Gmail's web
    client keeps them, its mobile apps do not), so a class-based layout collapses
    to unstyled text on exactly the device operators read enquiries on. The
    <table> wrapper is what centres a fixed-width card in Outlook, which ignores
    `margin: auto` on a block element.

    The tenant's brand hex arrives as $accent and is guaranteed to be a hex
    colour by App\Mail\SiteMailIdentity::accent() — do not pass a raw column
    value here.
--}}
@props([
    'siteName',
    'accent' => \App\Design\ColorPalette::DEFAULT_BRAND,
    'preheader' => null,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="color-scheme" content="light" />
    <title>{{ $siteName }}</title>
</head>

<body
    style="
        margin: 0;
        padding: 0;
        width: 100%;
        background-color: #f4f4f5;
        -webkit-font-smoothing: antialiased;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        color: #18181b;
    "
>
    @if (filled($preheader))
        {{-- The line inboxes show beside the subject; hidden in the body itself. --}}
        <div
            style="
                display: none;
                max-height: 0;
                max-width: 0;
                overflow: hidden;
                opacity: 0;
                font-size: 1px;
                line-height: 1px;
                color: #f4f4f5;
            "
        >
            {{ $preheader }}
        </div>
    @endif

    <table
        role="presentation"
        width="100%"
        cellpadding="0"
        cellspacing="0"
        border="0"
        style="background-color: #f4f4f5"
    >
        <tr>
            <td align="center" style="padding: 24px 12px">
                <table
                    role="presentation"
                    width="100%"
                    cellpadding="0"
                    cellspacing="0"
                    border="0"
                    style="max-width: 560px; background-color: #ffffff; border: 1px solid #e4e4e7; border-radius: 12px"
                >
                    <tr>
                        <td style="height: 4px; line-height: 4px; font-size: 0; background-color: {{ $accent }}; border-radius: 12px 12px 0 0;">
                            &nbsp;
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 28px 32px 32px">
                            <p
                                style="
                                    margin: 0 0 20px;
                                    font-size: 12px;
                                    font-weight: 600;
                                    letter-spacing: 0.08em;
                                    text-transform: uppercase;
                                    color: #71717a;
                                "
                            >
                                {{ $siteName }}
                            </p>

                            {{ $slot }}
                        </td>
                    </tr>
                </table>

                <p style="margin: 16px 0 0; font-size: 12px; line-height: 18px; color: #a1a1aa">
                    Sent by {{ $siteName }} via {{ config('app.name') }}
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
