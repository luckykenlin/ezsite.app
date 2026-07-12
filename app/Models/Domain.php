<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DomainFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Config;
use Stancl\Tenancy\Database\Models\Domain as BaseDomain;

final class Domain extends BaseDomain
{
    /** @use HasFactory<DomainFactory> */
    use HasFactory;

    public function getUrl(): string
    {
        $appUrl = uri(Config::string('app.url'));

        // Tenants created via CreateTenant store a bare subdomain label (no dot);
        // custom domains are stored fully-qualified, so a dot means "use as-is".
        $host = str_contains($this->domain, '.')
            ? $this->domain
            : sprintf('%s.%s', $this->domain, $appUrl->host());

        return sprintf('%s://%s/', $appUrl->scheme() ?? 'http', $host);
    }
}
