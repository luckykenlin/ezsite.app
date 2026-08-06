<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\Jobs\TenantAware;
use App\Models\Post;

/**
 * Test fixture: a concrete TenantAware job that writes an RLS-scoped Post,
 * used to prove central-dispatched jobs re-establish the tenant context.
 */
final class CreatePostForTenant extends TenantAware
{
    public function __construct(string $tenantId, private readonly string $title)
    {
        parent::__construct($tenantId);
    }

    protected function handleInTenant(): void
    {
        Post::query()->create(['tenant_id' => $this->tenantId, 'title' => $this->title]);
    }
}
