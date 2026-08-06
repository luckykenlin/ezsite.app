<?php

declare(strict_types=1);

namespace App\Actions;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Fetch a public web page and reduce it to the text an AI turn can read.
 *
 * This runs inside a queue worker on behalf of whatever URL the operator (or
 * the model) typed, which makes it a server-side request forgery target: the
 * URL must never be allowed to reach this machine's own network. Every hop —
 * the original URL and each redirect, which are followed manually for exactly
 * this reason — is re-checked: http/https only, no credentials in the URL, and
 * the host must not be (or resolve to) a private, loopback, link-local, or
 * carrier-grade-NAT address.
 *
 * The guard resolves the hostname itself, then Guzzle resolves it again to
 * connect — a DNS rebinding window this deliberately does not close (it would
 * take pinning the resolved IP into curl). The tool this backs reads menus and
 * marketing pages; the guard's job is stopping `http://169.254.169.254/` and
 * friends, not nation-state DNS tricks.
 *
 * Throws RuntimeException with operator-readable reasons — the tool wrapper
 * turns them into tool-result strings the model can act on.
 */
final readonly class FetchWebPageText
{
    private const array BLOCKED_IPV4_CIDRS = [
        '0.0.0.0/8',
        '10.0.0.0/8',
        '100.64.0.0/10',
        '127.0.0.0/8',
        '169.254.0.0/16',
        '172.16.0.0/12',
        '192.168.0.0/16',
        '240.0.0.0/4',
    ];

    /**
     * @param  (Closure(string): list<string>)|null  $resolveHost  DNS seam for tests;
     *                                                             defaults to the
     *                                                             system resolver
     */
    public function __construct(private ?Closure $resolveHost = null)
    {
        //
    }

    public function handle(string $url): string
    {
        $redirects = config()->integer('chat.fetch.max_redirects');

        for ($hop = 0; $hop <= $redirects; $hop++) {
            $this->guard($url);

            try {
                // `stream` is what makes max_bytes below a real cap: without it
                // Guzzle buffers the entire body into memory before anything
                // here can truncate it, so a hostile URL answering with a
                // gigabyte OOMs the worker no matter what the limit says.
                // Headers still arrive eagerly, so the redirect and status
                // branches below are unaffected.
                $response = Http::timeout(config()->integer('chat.fetch.timeout'))
                    ->withOptions(['allow_redirects' => false, 'stream' => true])
                    ->get($url);
            } catch (ConnectionException $connectionException) {
                throw new RuntimeException("The page could not be fetched: {$connectionException->getMessage()}", $connectionException->getCode(), $connectionException);
            }

            if ($response->redirect()) {
                $location = $response->header('Location');

                throw_if($location === '', RuntimeException::class, 'The page redirected without saying where to.');

                // Relative Location headers resolve against the current URL.
                $url = $this->resolveRedirect($url, $location);

                continue;
            }

            throw_unless($response->successful(), RuntimeException::class, "The page answered with HTTP {$response->status()}.");

            return $this->extractText($this->readCapped($response));
        }

        throw new RuntimeException('The page redirected too many times.');
    }

    /**
     * At most `max_bytes` off the wire, and never a byte more.
     *
     * A loop rather than one `read()`, because a single read on a network
     * stream returns whatever has arrived, not what was asked for — reading
     * once would silently truncate a slow page to its first packet.
     *
     * The budget being defended is memory, so every length here is a BYTE
     * count: `mb_strlen($s, '8bit')` rather than a bare `strlen`, which pint's
     * `mb_str_functions` rule would rewrite into a character count and quietly
     * turn the cap back into the thing it replaced.
     *
     * The read runs three bytes past the cap so `mb_strcut` has something to
     * cut. It only backs off to a character boundary when it actually shortens
     * the string — asked for exactly the length it was given it is a no-op, and
     * a UTF-8 sequence split by the cap survives into the extracted text. Three
     * is the most a split character can leave behind.
     */
    private function readCapped(Response $response): string
    {
        $maxBytes = config()->integer('chat.fetch.max_bytes');
        $body = $response->toPsrResponse()->getBody();
        $limit = $maxBytes + 3;
        $buffer = '';

        while (mb_strlen($buffer, '8bit') < $limit && ! $body->eof()) {
            $chunk = $body->read($limit - mb_strlen($buffer, '8bit'));

            // A non-eof stream can still answer empty; without this the loop
            // spins forever on one.
            if ($chunk === '') {
                break;
            }

            $buffer .= $chunk;
        }

        return mb_strcut($buffer, 0, $maxBytes);
    }

    /**
     * Refuse any URL that could reach this machine's own network.
     */
    private function guard(string $url): void
    {
        $parts = parse_url($url);

        throw_if($parts === false || ! isset($parts['host']), RuntimeException::class, 'That is not a fetchable URL.');

        $scheme = mb_strtolower($parts['scheme'] ?? '');

        throw_unless(in_array($scheme, ['http', 'https'], true), RuntimeException::class, 'Only http and https URLs can be fetched.');

        throw_if(isset($parts['user']) || isset($parts['pass']), RuntimeException::class, 'URLs with embedded credentials cannot be fetched.');

        $host = mb_strtolower(mb_trim($parts['host'], '[]'));

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            $this->guardIp($host);

            return;
        }

        $addresses = ($this->resolveHost ?? function (string $name): array {
            $ips = gethostbynamel($name) ?: [];

            foreach (dns_get_record($name, DNS_AAAA) ?: [] as $record) {
                if (is_string($record['ipv6'] ?? null)) {
                    $ips[] = $record['ipv6'];
                }
            }

            return array_values(array_unique($ips));
        })($host);

        throw_if($addresses === [], RuntimeException::class, "The host {$host} could not be resolved.");

        foreach ($addresses as $address) {
            $this->guardIp($address);
        }
    }

    private function guardIp(string $ip): void
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            // Inspected through its ASCII hex form rather than the raw binary,
            // because the byte string is not text and must not go anywhere
            // near the mb_* string functions this codebase standardises on.
            $hex = bin2hex((string) inet_pton($ip));

            // ::1 loopback, fc00::/7 unique-local, fe80::/10 link-local.
            $blocked = $hex === '0'.str_repeat('0', 30).'1'
                || in_array(mb_substr($hex, 0, 2), ['fc', 'fd'], true)
                || mb_substr($hex, 0, 2) === 'fe' && in_array($hex[2], ['8', '9', 'a', 'b'], true);

            // A v4-mapped address (::ffff:a.b.c.d) is re-checked as its
            // embedded IPv4.
            if (! $blocked && str_starts_with($hex, '00000000000000000000ffff')) {
                $this->guardIp(long2ip((int) hexdec(mb_substr($hex, 24))));

                return;
            }

            throw_if($blocked, RuntimeException::class, 'That address is not on the public internet.');

            return;
        }

        $address = (int) ip2long($ip);

        foreach (self::BLOCKED_IPV4_CIDRS as $cidr) {
            [$subnet, $bits] = explode('/', $cidr);

            $mask = -1 << (32 - (int) $bits);

            throw_if(
                ($address & $mask) === (ip2long($subnet) & $mask),
                RuntimeException::class,
                'That address is not on the public internet.',
            );
        }
    }

    private function resolveRedirect(string $current, string $location): string
    {
        if (parse_url($location, PHP_URL_SCHEME) !== null) {
            return $location;
        }

        $parts = parse_url($current);
        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : '');

        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }

        $directory = mb_rtrim(dirname($parts['path'] ?? '/'), '/');

        return $origin.$directory.'/'.$location;
    }

    /**
     * The page as prompt-sized text: title first, scripts and styles removed,
     * tags stripped, whitespace collapsed.
     */
    private function extractText(string $html): string
    {
        preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $title);

        $body = preg_replace('/<(script|style|noscript|svg|template)\b[^>]*>.*?<\/\1>/is', ' ', $html) ?? $html;
        $body = preg_replace('/<(br|\/p|\/div|\/li|\/h[1-6]|\/tr)[^>]*>/i', "\n", $body) ?? $body;

        $text = html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5);
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\s*\n\s*/', "\n", $text) ?? $text;

        $text = mb_trim(($title !== [] ? 'Title: '.mb_trim(html_entity_decode($title[1], ENT_QUOTES | ENT_HTML5))."\n\n" : '').mb_trim($text));

        return mb_substr($text, 0, config()->integer('chat.fetch.max_chars'));
    }
}
