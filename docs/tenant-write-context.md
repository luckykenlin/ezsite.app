# 带外租户写入地基（Tenant Write Context）— 技术方案

> 状态：**设计定稿，待实现**。这是 REVIEW.md「🔴 最大风险：带外写入丢失 RLS 上下文」和「§4 Move 1 Action mutation 层」的前置地基。任何 AI / 队列 / MCP / webhook / cron 写租户数据之前必须先落地本方案。
> 关联：[REVIEW.md](../REVIEW.md) · [.claude/docs/tenancy.md](../.claude/docs/tenancy.md) · [reviews-module.md](./reviews-module.md)（第一个消费者）

## 1. 问题（基于真实代码，不是假设）

隔离机制回顾（`vendor/stancl/tenancy/src/Bootstrappers/PostgresRLSBootstrapper.php`）：

- `bootstrap()`：初始化租户时，**切换到 RLS 用户**（`tenancy.rls.user`，即 `TENANCY_RLS_USERNAME`）并 `SET my.current_tenant = '<tenant key>'`。
- `revert()`：`RESET my.current_tenant` 并切回 central 连接。
- RLS 策略按 `tenant_id = current_setting('my.current_tenant')` 过滤。`RlsIsolationTest.php:126` 证明：会话变量一旦被 RESET，写入直接抛 `QueryException`（**fail-loud**）——**前提是当前连接是 RLS 用户**。

危险点在 **central 上下文**：

1. **central 连接依赖 BYPASSRLS**。`RlsIsolationTest.php:83`「central connection sees rows across all tenants」能过，是因为 central 角色（测试用 `postgres`，`CLAUDE.md` 要求 SUPERUSER/BYPASSRLS）**绕过 RLS**。→ **跨租户聚合读（运营驾驶舱）在架构上就靠这个绕过**，无法通过给 central 降权来消除写风险。
2. 因此**在 central 上下文写 RLS 表 = 完全不设防**：`my.current_tenant` 没设、RLS 被绕过，`INSERT ... tenant_id = <任意值>` 会**静默成功**，写错租户没有任何报错。
3. `QueueTenancyBootstrapper` 已开启（`config/tenancy.php:202`），**在租户上下文里 dispatch 的 job 会自动带上下文**——这条路径是安全的。真正裸奔的是**从 central 上下文发起的写**：
   - `tenants:*` 之类 **cron** 批量遍历所有客户；
   - super_admin 在 **central 面板**替某个客户操作（回评、发帖）；
   - **webhook**（评论平台回调、Stripe）——进来时零租户上下文；
   - **MCP / AI 工具调用**（REVIEW §4 已把 Action 定为 AI 工具层）。
4. 当前测试**只覆盖读隔离**：`grep` 全套无任何「central 上下文写 RLS 表被拒」的断言。写路径是盲区。

**结论**：既然 central 必须 BYPASSRLS 才能跨租户读，写安全就只能靠"约定"，那就必须把约定**编译成代码强制**——三道闸：唯一写入通道 + 运行时守卫 + arch 测试。

## 2. 设计目标

- **唯一 sanctioned 写入通道**：所有租户作用域写入只经一个入口，自动 `initialize → 写 → end`，`try/finally` 保证异常也 revert。
- **Fail-loud 而非 fail-silent**：忘了走通道的写，直接抛异常，绝不静默写错租户。
- **可嵌套**：central 面板已在租户 X 上下文里再切到 Y，结束后必须还原到 X（不是无脑 end 到 central）。
- **一处适配全场景**：controller / job / command / MCP / webhook 通吃。
- **不违反既有约定**：不加 `$fillable`、不加读作用域（隔离仍是 RLS），模型 `final`，Action `final readonly`。

## 3. 方案：三道闸

### 闸 1 — `RunInTenant`：唯一 sanctioned 写入通道

`tenancy()->run()` 本身已支持"初始化→跑→还原上一个上下文"（含嵌套还原）。我们**包一层**加上「初始化后断言 RLS 会话变量确实生效」——防止 bootstrapper 配置漂移导致的假初始化，把安全从"信任框架"提升到"运行时验证"。

`app/Actions/RunInTenant.php`

```php
<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;

/**
 * The single sanctioned entry point for any tenant-scoped write that does not
 * already run inside an HTTP request bound to a tenant domain.
 *
 * Because the central DB role bypasses RLS (required for cross-tenant reads in
 * the agency cockpit), a write performed in central context is UNPROTECTED and
 * would silently land under the wrong tenant. This wrapper forces the RLS user
 * + `my.current_tenant` session var to be active for the duration of $callback,
 * asserts it actually took effect, and always reverts — even on exception.
 *
 * @see \App\Concerns\RequiresTenantContext runtime guard (belt-and-suspenders)
 */
final readonly class RunInTenant
{
    /**
     * @template TReturn
     * @param  \Closure(): TReturn  $callback
     * @return TReturn
     */
    public function handle(Tenant|string $tenant, \Closure $callback): mixed
    {
        $tenant = $tenant instanceof Tenant
            ? $tenant
            : Tenant::query()->findOrFail($tenant);

        return tenancy()->run($tenant, function () use ($tenant, $callback): mixed {
            $this->assertRlsContextMatches($tenant);

            return $callback();
        });
    }

    /**
     * Verify the RLS session variable is actually set to this tenant on the
     * live connection. Turns a mis-bootstrapped context into a loud failure
     * before any write happens, instead of a silent cross-tenant write.
     */
    private function assertRlsContextMatches(TenantContract $tenant): void
    {
        $active = DB::scalar("SELECT current_setting('".config('tenancy.rls.session_variable_name')."', true)");

        if ((string) $active !== (string) $tenant->getTenantKey()) {
            throw new RuntimeException(
                'RLS context assertion failed: expected tenant '.$tenant->getTenantKey().", got '".($active ?? 'null')."'.",
            );
        }
    }
}
```

用法（AI / cron / webhook 一律如此）：

```php
app(RunInTenant::class)->handle($tenant, function () use ($payload): void {
    app(ReplyToReview::class)->handle($review, $payload); // 里面正常 Eloquent 写，RLS 已生效
});
```

### 闸 2 — `RequiresTenantContext`：运行时守卫（防呆，最强的一道）

即使有人忘了走 `RunInTenant`，这道闸把"静默写错租户"变成"当场抛异常"。挂在**所有 RLS 作用域模型**上（`Post` / `Page` / `Location` / 未来的 `reviews` / `social_accounts`）——**不挂 no-rls 模型**（`Business`：它靠手动 `tenant_id` 隔离，且合法地在 central 面板被跨租户写，见 tenancy.md L4）。

`app/Concerns/RequiresTenantContext.php`

```php
<?php

declare(strict_types=1);

namespace App\Concerns;

use RuntimeException;

/**
 * Guard for RLS-scoped tenant models: refuse to create/update/delete unless
 * tenancy is initialized (i.e. we're on the RLS connection with
 * `my.current_tenant` set), so a forgotten RunInTenant fails loud instead of
 * writing under the BYPASSRLS central connection with no tenant scope.
 *
 * Reads are intentionally NOT guarded — the central cockpit legitimately reads
 * across tenants on the BYPASSRLS connection.
 */
trait RequiresTenantContext
{
    public static function bootRequiresTenantContext(): void
    {
        foreach (['creating', 'updating', 'deleting', 'restoring'] as $event) {
            static::{$event}(function (self $model): void {
                if (! tenant()) {
                    throw new RuntimeException(sprintf(
                        '%s is RLS-scoped and can only be written inside a tenant context. '
                        .'Wrap the write in RunInTenant (see docs/tenant-write-context.md).',
                        $model::class,
                    ));
                }
            });
        }
    }
}
```

> `tenant()` 在 central 上下文返回 `null`，在 `RunInTenant`/HTTP 租户请求里返回当前 `Tenant`。软删级联（`Business::restoring` 恢复 locations）已在租户上下文内，守卫自然通过。

### 闸 3 — `TenantAware` job 基类：给 central 发起的异步写

从租户上下文 dispatch 的 job 靠 `QueueTenancyBootstrapper` 自动带上下文；但 **cron / central 发起的 job 没有**。统一用一个基类，两种场景都正确、且幂等：

`app/Jobs/TenantAware.php`

```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\RunInTenant;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Base for jobs that write tenant-scoped data. Carries the tenant key in the
 * payload and re-establishes the RLS context on the worker via RunInTenant,
 * regardless of whether it was dispatched from tenant or central context.
 */
abstract class TenantAware implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly string $tenantId) {}

    final public function handle(): void
    {
        app(RunInTenant::class)->handle($this->tenantId, fn () => $this->handleInTenant());
    }

    abstract protected function handleInTenant(): void;
}
```

> 注意：因为这里主动 `RunInTenant`，从租户上下文 dispatch 时会经历 QueueTenancyBootstrapper 再叠一层 `run()`——是幂等安全的（同一 tenant 重复初始化）。若想省掉重复，可在 central-only 的调度里 dispatch，并让 job 始终自带 `tenantId`（推荐，语义最清晰）。

## 4. 与 Filament / 现有写入的关系

- **人类经 Filament 面板写**：走的是 HTTP + tenant 域，`InitializeTenancyByDomainOrSubdomain` 已把上下文建好，守卫自然通过——**无需改现有 tenant 面板 resource**。
- **REVIEW §4 Move 1 的 `UpdatePageBlock` / `UpdateDesignToken`**：这些 Action 的 `handle()` 内部**不自己** initialize；由调用方决定上下文——HTTP 调用方已在租户里，AI/MCP 调用方用 `RunInTenant` 包起来。Action 保持"纯写逻辑"，上下文是横切关注点。
- **central 面板替客户操作**（运营驾驶舱回评/发帖）：super_admin 在 central 上下文，必须 `RunInTenant($client, ...)` 包住那次写。守卫强制你这么做。

## 5. 生产角色与配置加固（ops）

- **central 角色必须 BYPASSRLS**（跨租户读依赖它），**RLS 角色（`TENANCY_RLS_USERNAME`）必须非 BYPASSRLS、非 SUPERUSER**——否则闸 1 的会话变量隔离形同虚设。加一条测试锁定（见 §6）。
- 承接 REVIEW L24：生产 `DB_SSLMODE=require`，`.env.example` 显式列出。
- 承接 REVIEW M6：`stancl/tenancy` 先钉死 reviewed commit 再动本地基（`RunInTenant` 直接依赖 `tenancy()->run()` 语义）。

## 6. 测试（补上写路径盲区）

新增 `tests/Feature/Tenancy/TenantWriteContextTest.php`（真实 Postgres，沿用 `InteractsWithTenancy` 约定）：

1. **守卫 fail-loud**：central 上下文（未 initialize）直接 `Post::create([...])` → 抛 `RuntimeException`（闸 2）。
2. **RunInTenant 落到正确租户**：`RunInTenant($A, fn => Post::create(...))` 后，在 $B 上下文不可见、$A 上下文可见（隔离 + 归属）。
3. **异常也 revert**：`RunInTenant($A, fn => throw ...)` 后 `tenant()` 为 null（`try/finally` 生效），且后续 central 读不串味。
4. **嵌套还原**：在 $A 上下文里 `RunInTenant($B, ...)`，结束后 `tenant()` 仍是 $A。
5. **会话变量断言**：手动 `RESET my.current_tenant` 后调 `RunInTenant` 内的 `assert` 路径（或验证 bootstrap 后 `current_setting` 命中）。
6. **TenantAware job**：从 central dispatch 一个测试 job，断言写入落在目标租户、且对其他租户不可见。
7. **角色权限锁定**（arch/feature）：断言 `TENANCY_RLS_USERNAME` 对应角色 `rolbypassrls = false`（查 `pg_roles`），防止有人误配 BYPASSRLS 把隔离掏空。

新增 `tests/Arch/`（承接 REVIEW L21）：

8. 每个 RLS 作用域模型（排除 no-rls 的 `Business`）**必须 use `RequiresTenantContext`**（arch 断言 trait 存在）。
9. `App\Actions` 全部 `final readonly`（补 L21）。

## 7. 落地顺序

1. 钉死 `stancl/tenancy` commit（REVIEW M6）。
2. 建 `RequiresTenantContext` trait + 挂到 `Post`/`Page`/`Location`，跑现有租户测试确认不回归（它们都在 initialize 内写，应全绿）。
3. 建 `RunInTenant` + 写路径测试 1–5。
4. 建 `TenantAware` + 测试 6。
5. 加角色权限锁定测试 7 + arch 测试 8–9。
6. 之后 REVIEW §4 Move 1 的 mutation Action、以及 reviews 模块的 cron/webhook 写，全部建立在这三道闸之上。

**一句话**：central 必须绕过 RLS 才能做运营驾驶舱，所以写安全只能靠纪律——本方案把纪律变成"唯一通道 + 运行时守卫 + arch 锁定"三道编译期/运行期强制,让"忘记"变成"当场报错"而不是"静默写错客户"。
