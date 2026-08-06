<?php

declare(strict_types=1);

/*
 * The two shapes a Domain can take (see Domain::getUrl): a bare subdomain
 * label resolved against a central domain, or a fully-qualified custom domain.
 * Each row is [registered domain, is custom domain].
 */
dataset('tenant_domains', [
    'subdomain' => ['acme', false],
    'custom domain' => ['acme-inc.test', true],
]);
