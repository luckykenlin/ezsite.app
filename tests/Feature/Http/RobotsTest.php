<?php

declare(strict_types=1);

use App\Models\Tenant;

/*
 * RobotsController. Split out of SitemapTest, where it used to live: one subject
 * under test per file, named after it (see the pest-testing skill) — a robots
 * regression should not surface as "SitemapTest failed".
 */
it('advertises the tenant sitemap in robots.txt and keeps the panel out', function (): void {
    Tenant::factory()->withDomain('acme')->create();

    $this->get(sprintf('http://acme.%s/robots.txt', $this->centralDomain()))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('User-agent: *')
        ->assertSee('Disallow: /admin')
        ->assertSee('Disallow: /_editor')
        ->assertSee(sprintf('Sitemap: http://acme.%s/sitemap.xml', $this->centralDomain()));
});
