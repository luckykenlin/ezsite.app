<?php

declare(strict_types=1);

use App\Enums\Locale;
use App\Site\LocaleNegotiator;
use Illuminate\Http\Request;

/**
 * Built directly rather than through an HTTP request, because Symfony's
 * Request::create() injects a default `Accept-Language: en-us,en;q=0.5` when the
 * server params do not carry one — so a test client cannot express "a visitor
 * whose browser said nothing", which is the case the default exists for.
 */
function requestWithLanguages(?string $acceptLanguage, ?string $cookie = null): Request
{
    $request = Request::create('http://ezsite.test/');

    $request->headers->remove('Accept-Language');

    if ($acceptLanguage !== null) {
        $request->headers->set('Accept-Language', $acceptLanguage);
    }

    if ($cookie !== null) {
        $request->cookies->set(Locale::COOKIE, $cookie);
    }

    return $request;
}

it('reads the language out of the browser preference list', function (?string $header, Locale $expected): void {
    expect(resolve(LocaleNegotiator::class)->handle(requestWithLanguages($header)))->toBe($expected);
})->with([
    // getLanguages() normalises `zh-CN` to `zh_CN`, so matching the base subtag
    // is what makes the very common Chinese browser land on Chinese at all.
    'a Chinese browser listing English second' => ['zh-CN,zh;q=0.9,en;q=0.8', Locale::Chinese],
    'a Chinese browser sending only a region' => ['zh-CN', Locale::Chinese],
    // Traditional readers get Simplified. A known trade, and better than
    // English — a third case would make it a data change, not a rewrite.
    'a Traditional Chinese browser' => ['zh-Hant', Locale::Chinese],
    'an American browser' => ['en-US,en;q=0.9', Locale::English],
    'quality order, not header order' => ['en;q=0.2,zh;q=0.9', Locale::Chinese],
    // Neither published: the default, rather than whichever case happens to be
    // declared first.
    'a language we do not publish' => ['fr-FR,fr;q=0.9', Locale::Chinese],
    'a browser that said nothing at all' => [null, Locale::Chinese],
]);

it('lets a returning visitor overrule their own browser', function (): void {
    // The cookie is the visitor's explicit choice from the switcher; the header
    // is only what their software guessed for them.
    $request = requestWithLanguages('zh-CN,zh;q=0.9', cookie: 'en');

    expect(resolve(LocaleNegotiator::class)->handle($request))->toBe(Locale::English);
});

it('ignores a cookie naming a language it does not publish', function (): void {
    // Cookies are visitor-controlled input: an old value, or a forged one, must
    // fall through to negotiation rather than reach Locale::from() and throw.
    $request = requestWithLanguages('en-US,en;q=0.9', cookie: 'de');

    expect(resolve(LocaleNegotiator::class)->handle($request))->toBe(Locale::English);
});
