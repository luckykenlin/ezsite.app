<?php

declare(strict_types=1);

use App\Actions\FetchWebPageText;
use GuzzleHttp\Psr7\FnStream;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

function fetcher(array $resolvesTo = ['93.184.216.34']): FetchWebPageText
{
    // The DNS seam: tests decide what a hostname resolves to, so no test ever
    // performs a real lookup.
    return new FetchWebPageText(fn (string $host): array => $resolvesTo);
}

it('fetches a page and returns its text with the title first', function (): void {
    Http::fake(['https://example.com/menu' => Http::response(
        '<html><head><title>La Cocina &amp; Co</title><style>p{color:red}</style></head>'
        .'<body><script>alert(1)</script><h1>Menu</h1><p>Tacos &mdash; $12</p><p>Mole</p></body></html>',
    )]);

    $text = fetcher()->handle('https://example.com/menu');

    expect($text)->toStartWith("Title: La Cocina & Co\n")
        ->toContain("Menu\nTacos — $12\nMole")
        // Script and style BODIES are removed, not just their tags.
        ->not->toContain('alert(1)')
        ->not->toContain('color:red');
});

it('caps the returned text', function (): void {
    config()->set('chat.fetch.max_chars', 40);

    Http::fake(['https://example.com/*' => Http::response('<p>'.str_repeat('word ', 200).'</p>')]);

    expect(mb_strlen(fetcher()->handle('https://example.com/long')))->toBeLessThanOrEqual(40);
});

/*
 * The two halves of the byte cap. It is a memory bound, not a formatting one:
 * the body is read off a streamed response so an enormous page is never fully
 * buffered, and the point where the read stops has to be a character boundary,
 * or the extractor is handed a string ending in half a UTF-8 sequence.
 */
it('stops reading the body at the byte cap', function (): void {
    config()->set('chat.fetch.max_bytes', 64);
    config()->set('chat.fetch.max_chars', 12000);

    Http::fake(['https://example.com/*' => Http::response('<p>'.str_repeat('a', 5000).'</p>')]);

    // Everything past the cap never entered the buffer, so the extracted text
    // cannot be longer than it. '8bit' counts bytes, which is what is bounded.
    expect(mb_strlen(fetcher()->handle('https://example.com/huge'), '8bit'))->toBeLessThanOrEqual(64);
});

/*
 * The read loop's only other way out. A PSR-7 stream may answer an empty read
 * while still reporting itself as not-at-EOF; a loop that trusted `eof()` alone
 * would spin on one forever, hanging the worker on a page that simply stopped
 * sending. Unreachable through Http::fake's ordinary in-memory bodies, which is
 * exactly why the branch is easy to delete as dead code — it is not.
 */
it('stops reading a stream that answers empty without reaching the end', function (): void {
    Http::fake(['https://example.com/*' => Http::response(FnStream::decorate(
        Utils::streamFor('<p>never delivered</p>'),
        ['read' => fn (): string => '', 'eof' => fn (): bool => false],
    ))]);

    expect(fetcher()->handle('https://example.com/stalled'))->toBeEmpty();
});

it('cuts the body back to a character boundary', function (): void {
    // 30 three-byte characters: a 64-byte cap lands inside the 22nd, which
    // mb_strcut drops rather than emitting a truncated sequence.
    config()->set('chat.fetch.max_bytes', 64);
    config()->set('chat.fetch.max_chars', 12000);

    Http::fake(['https://example.com/*' => Http::response(str_repeat('漢', 30))]);

    $text = fetcher()->handle('https://example.com/cjk');

    expect(mb_check_encoding($text, 'UTF-8'))->toBeTrue()
        ->and($text)->toBe(str_repeat('漢', 21));
});

it('follows a redirect, re-guarding every hop', function (): void {
    Http::fake([
        'https://example.com/old' => Http::response(null, 301, ['Location' => '/new']),
        'https://example.com/new' => Http::response('<p>Moved here</p>'),
    ]);

    expect(fetcher()->handle('https://example.com/old'))->toContain('Moved here');
});

it('resolves a directory-relative redirect against the current URL', function (): void {
    Http::fake([
        'https://example.com/menu/today' => Http::response(null, 302, ['Location' => 'tomorrow']),
        'https://example.com/menu/tomorrow' => Http::response('<p>Specials</p>'),
    ]);

    expect(fetcher()->handle('https://example.com/menu/today'))->toContain('Specials');
});

it('refuses a redirect that lands on a private address', function (): void {
    Http::fake([
        'https://example.com/*' => Http::response(null, 302, ['Location' => 'http://169.254.169.254/latest/meta-data/']),
    ]);

    fetcher()->handle('https://example.com/innocent');
})->throws(RuntimeException::class, 'not on the public internet');

it('gives up after too many redirects', function (): void {
    config()->set('chat.fetch.max_redirects', 2);

    Http::fake(['https://example.com/*' => Http::response(null, 302, ['Location' => 'https://example.com/loop'])]);

    fetcher()->handle('https://example.com/loop');
})->throws(RuntimeException::class, 'redirected too many times');

it('refuses a redirect with no destination', function (): void {
    Http::fake(['https://example.com/*' => Http::response(null, 302)]);

    fetcher()->handle('https://example.com/lost');
})->throws(RuntimeException::class, 'without saying where');

it('reports a failing page as an error, not as content', function (): void {
    Http::fake(['https://example.com/*' => Http::response('gone', 404)]);

    fetcher()->handle('https://example.com/missing');
})->throws(RuntimeException::class, 'HTTP 404');

it('reports a connection failure in operator terms', function (): void {
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    fetcher()->handle('https://example.com/dead');
})->throws(RuntimeException::class, 'could not be fetched');

/*
 * The SSRF guard itself. This action fetches whatever URL the operator (or the
 * model) supplies from inside a queue worker, so every one of these must be
 * refused before a single byte moves — Http::fake() with no routes makes any
 * escaped request fail loudly.
 */
it('refuses URLs that could reach the internal network', function (string $url, array $resolvesTo): void {
    Http::fake();

    fetcher($resolvesTo)->handle($url);
})->throws(RuntimeException::class)->with([
    'no host' => ['not a url', ['93.184.216.34']],
    'ftp scheme' => ['ftp://example.com/file', ['93.184.216.34']],
    'file scheme' => ['file:///etc/passwd', ['93.184.216.34']],
    'embedded credentials' => ['https://user:pass@example.com/', ['93.184.216.34']],
    'loopback literal' => ['http://127.0.0.1/admin', []],
    'loopback range' => ['http://127.8.8.8/', []],
    'private 10/8' => ['http://10.0.0.5/', []],
    'private 172.16/12' => ['http://172.31.255.1/', []],
    'private 192.168/16' => ['http://192.168.1.1/router', []],
    'link-local metadata' => ['http://169.254.169.254/latest/meta-data/', []],
    'carrier-grade NAT' => ['http://100.64.0.1/', []],
    'this-network' => ['http://0.0.0.0/', []],
    'reserved 240/4' => ['http://240.1.2.3/', []],
    'ipv6 loopback' => ['http://[::1]/', []],
    'ipv6 unique-local' => ['http://[fc00::1]/', []],
    'ipv6 fd range' => ['http://[fd12:3456::1]/', []],
    'ipv6 link-local' => ['http://[fe80::1]/', []],
    'ipv6 v4-mapped private' => ['http://[::ffff:192.168.0.1]/', []],
    'hostname resolving to loopback' => ['https://rebind.example/', ['127.0.0.1']],
    'hostname resolving to private' => ['https://internal.example/', ['10.1.2.3']],
    'hostname with one private A record' => ['https://mixed.example/', ['93.184.216.34', '192.168.0.10']],
    'unresolvable hostname' => ['https://nowhere.example/', []],
]);

it('allows public IPv6 hosts through the guard', function (): void {
    Http::fake(['*' => Http::response('<p>ok</p>')]);

    expect(fetcher(['2606:2800:220:1:248:1893:25c8:1946'])->handle('https://example.com/'))->toContain('ok');
});

it('allows public IP-literal hosts, including v4-mapped ones', function (string $url): void {
    Http::fake(['*' => Http::response('<p>ok</p>')]);

    expect(fetcher()->handle($url))->toContain('ok');
})->with([
    'plain v4 literal' => ['http://93.184.216.34/menu'],
    // The v4-mapped form re-checks as its embedded IPv4 and passes.
    'v4-mapped public' => ['http://[::ffff:93.184.216.34]/menu'],
]);

/*
 * The default resolver — the one production uses — must also land on the
 * guard. localhost resolves locally (hosts file), so this performs no real
 * network lookup and still exercises the un-stubbed path.
 */
it('resolves hostnames itself when no resolver is injected', function (): void {
    Http::fake();

    new FetchWebPageText()->handle('http://localhost/secret');
})->throws(RuntimeException::class);
