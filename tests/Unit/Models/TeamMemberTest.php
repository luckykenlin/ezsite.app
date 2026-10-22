<?php

declare(strict_types=1);

use App\Models\Media;
use App\Models\TeamMember;
use App\Models\Tenant;

test('tenant relation returns the owning tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $member = $this->runInTenant($tenant, fn (): TeamMember => TeamMember::factory()->create(['tenant_id' => $tenant->id]));
    $member = TeamMember::query()->findOrFail($member->getKey());

    expect($member->tenant->is($tenant))->toBeTrue();
});

test('media relation returns the portrait', function (): void {
    $tenant = Tenant::factory()->create();
    [$memberId, $mediaId] = $this->runInTenant($tenant, function () use ($tenant): array {
        $media = Media::factory()->create(['tenant_id' => $tenant->id]);
        $member = TeamMember::factory()->create(['tenant_id' => $tenant->id, 'media_id' => $media->id]);

        return [$member->id, $media->id];
    });

    expect(TeamMember::query()->findOrFail($memberId)->media->getKey())->toBe($mediaId);
});

test('deleting the portrait keeps the team member', function (): void {
    $tenant = Tenant::factory()->create();
    $memberId = $this->runInTenant($tenant, function () use ($tenant): int {
        $media = Media::factory()->create(['tenant_id' => $tenant->id]);
        $member = TeamMember::factory()->withBookingUrl()->create(['tenant_id' => $tenant->id, 'media_id' => $media->id]);

        $media->delete();

        return $member->id;
    });

    $member = TeamMember::query()->findOrFail($memberId);

    expect($member->media_id)->toBeNull()
        ->and($member->booking_url)->toStartWith('https://booksy.com/');
});

test('the ordered scope sorts by sort, then by id', function (): void {
    $tenant = Tenant::factory()->create();
    $expected = $this->runInTenant($tenant, function () use ($tenant): array {
        $last = TeamMember::factory()->create(['tenant_id' => $tenant->id, 'sort' => 2]);
        $tiedFirst = TeamMember::factory()->create(['tenant_id' => $tenant->id, 'sort' => 0]);
        $tiedSecond = TeamMember::factory()->create(['tenant_id' => $tenant->id, 'sort' => 0]);

        return [$tiedFirst->id, $tiedSecond->id, $last->id];
    });

    expect(TeamMember::query()->ordered()->pluck('id')->all())->toBe($expected);
});

test('to array', function (): void {
    $tenant = Tenant::factory()->create();
    $member = $this->runInTenant($tenant, fn (): TeamMember => TeamMember::factory()->create(['tenant_id' => $tenant->id]));
    $member = TeamMember::query()->findOrFail($member->getKey());

    expect(array_keys($member->toArray()))
        ->toBe([
            'id',
            'tenant_id',
            'name',
            'role',
            'bio',
            'media_id',
            'booking_url',
            'instagram_handle',
            'sort',
            'created_at',
            'updated_at',
        ]);
});
