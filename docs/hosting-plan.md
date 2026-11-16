# 生产托管方案（Hosting Plan）— Forge + DigitalOcean + Cloudflare + Caddy

> 状态：**方案已定，未实现**（2026-11-16）。本文件是实现时的唯一依据；实现完成后把状态改为"已实现"，并把与代码不一致的细节以代码为准更新回来。
> 关联：[.claude/docs/tenancy.md](../.claude/docs/tenancy.md)（RLS、租户识别）· [tenant-write-context.md](./tenant-write-context.md)

---

## 0. 一页结论

| 决策 | 选择 | 一句话理由 |
|---|---|---|
| 渲染方式 | **保持动态渲染（Laravel + Blade）**，不做静态构建 | 线索表单、UTM 归因、Posts、编辑器预览、签名链接都依赖动态请求；改静态等于重写这些功能 |
| 托管 | **Laravel Forge + DigitalOcean Droplet** | 不需要自动扩容（负载到了手动加一台）；比 Laravel Cloud 便宜 2–3 倍 |
| 数据库 | **DigitalOcean 托管 Postgres，直连，不用连接池** | 不用自己管备份和升级；RLS 要求会话级连接（见 §2） |
| 平台子域名 `*.ezsite.app` | **Cloudflare 代理**（证书 + CDN，免费） | 通配符记录在我们自己的 zone 下，不按主机名计费 |
| 商家自有域名 | **Caddy 边缘机 + On-Demand TLS**（Let's Encrypt） | 每个域名 $0；Cloudflare for SaaS 是 $0.10/个/月，根域名支持还要 Enterprise |
| 媒体文件 | **Cloudflare R2，通过 `media.ezsite.app` 提供** | 出网流量免费；应用服务器变成无状态，才能加第二台 |
| 不做的事 | 不上 Octane、不开持久连接、不用事务模式的连接池、不加 Redis | 前三项会破坏 RLS 隔离（§2）；Redis 按目前流量没有必要，少一个组件 |

**10,000 个网站的预估月成本：约 $240**（明细见 §9）。

### 被否决的方案（不要重新提）

| 方案 | 否决原因 |
|---|---|
| CloudFront SaaS Manager + S3 静态站（byChronos 方案） | 每个网站一个 Tenant，约 $0.10/个/月，平台子域名也收费；需要构建流水线、release 目录、传播等待、两套状态机；动态功能全部要另做 API。约 $1,500–2,500/月 |
| Laravel Cloud | 自定义域名 $0.25/个/月，上千个要谈 Enterprise；出网流量 $0.10/GB；MySQL 不支持 RLS，Serverless Postgres（Neon）的 pooler 是事务模式。约 $1,300–1,700/月 |
| Cloudflare for SaaS 管理商家域名 | Cloudflare 本来就不缓存我们的 HTML（每个响应都带 session cookie），所以它对商家域名实际只提供证书；Caddy 免费做到同样的事，而且支持根域名 |
| Caddy 直接装在 Forge 应用服务器上 | 和 Forge 管理的 nginx 冲突；多台服务器时证书存储和负载均衡都要另外解决 |

---

## 1. 目标架构

```
                      ┌────────────────── Cloudflare（ezsite.app zone）──────────────────┐
访客 ─ ezsite.app ────▶ 代理 + Origin CA 证书 ──────────────────────────────┐
访客 ─ *.ezsite.app ──▶ 代理（通配符）──────────────────────────────────────┤
访客 ─ media.ezsite.app ▶ R2 自定义域名（CDN 缓存，流量免费）                │
                      └───────────────────────────────────────────────────────┤──────┘
                                                                              │ 443（只放行 Cloudflare IP）
访客 ─ www.customer.com ─▶ Caddy 边缘机（保留 IP）                          ▼
访客 ─ customer.com ─────▶  On-Demand TLS ── 内网 :80 ─────────▶  应用服务器（Forge，nginx + php-fpm）
                              │                                   ├─ 队列 worker（Forge 守护进程）
                              └─ ask ─▶ 应用 /caddy/ask            ├─ Scheduler
                                                                  └─ OPcache
                                                                        │ 直连 25060，sslmode=require
                                                                        ▼
                                                              DigitalOcean 托管 Postgres
```

**Host 头一路透传，不改写**：Cloudflare 代理和 Caddy 的 `reverse_proxy` 默认都保留原始 Host，所以 `InitializeTenancyByDomainOrSubdomain` 和现在一样按域名识别租户，**租户识别逻辑一行不改**。

---

## 2. 不可违反的约束（RLS 与连接）

`vendor/stancl/tenancy/src/Bootstrappers/PostgresRLSBootstrapper.php:38` 用的是**会话级** `SET my.current_tenant = '...'`，不是 `SET LOCAL`；结束租户上下文时在第 43 行执行 `RESET`。所以 RLS 隔离成立的前提是：**一个请求从头到尾使用同一条物理连接，并且请求结束后这条连接不会带着租户变量被别的请求复用。**

由此得到以下硬性规定：

1. **禁止事务模式的连接池**（PgBouncer `pool_mode = transaction`、Neon `-pooler` 地址、DigitalOcean 连接池的 Transaction 模式）。后果：租户变量丢失导致 RLS 报错，或者**读到上一个请求的租户变量，跨租户串数据**。
   - 以后连接数不够、必须加连接池时，**只能用 session 模式**。
2. **不上 Octane，不开 `PDO::ATTR_PERSISTENT`**。连接会跨请求复用，全靠 `RESET` 清理；请求异常中断时可能清不掉。如果将来要上，前提是先补一个"请求中途抛异常后，下一个请求看不到上一个租户"的测试。
3. **数据库角色权限**：
   - 中心上下文的连接必须有 `BYPASSRLS`（跨租户聚合读依赖它，见 `tenancy.md`）。
   - 运行迁移的角色必须能 `CREATE ROLE`：`tenants:rls` 会**自动创建** `TENANCY_RLS_USERNAME` 角色（`CreateUserWithRLSPolicies.php:54`），并在每次 pgsql 迁移后重新同步策略（`TenancyServiceProvider::syncRlsPoliciesAfterMigrations()`）。
   - DigitalOcean 托管 Postgres **没有 superuser**。默认的 `doadmin` 据社区资料有 `BYPASSRLS` 和 `CREATEROLE`，但不是官方文档，**建库后必须先验证**（§7.1 第 2 步）。
4. **OPcache 可以开，而且应该开**：它只缓存编译后的字节码，不在请求之间保留任何应用状态，和 RLS 无关。

实现阶段第一个 PR 里，用 `record-rule` 把第 1、2 条记进 `.ai/rules`（glob：`config/database.php`、`config/tenancy.php`），让后来的人不会为了省连接数顺手改坏。

---

## 3. 实施阶段总览

| 阶段 | 内容 | 依赖 | 类型 |
|---|---|---|---|
| **P1** | 媒体文件迁到 R2（应用无状态化） | 无 | 代码 |
| **P2** | 商家自有域名的生命周期（状态、`/caddy/ask`、DNS 检查、商家自助页面） | 无 | 代码 |
| **P3** | 生产配置进代码仓库（部署脚本、Caddyfile、`.env.production.example`、TrustProxies） | P1、P2 定稿的环境变量 | 代码 |
| **P4** | 一次性搭建基础设施（DO、Forge、Cloudflare、R2、边缘机） | P1–P3 合并 | 运维 |
| **P5** | 监控、告警、备份演练 | P4 | 运维 |

P1 和 P2 互不依赖，可以并行。项目还没有生产数据（pre-production），**所有改动都按"直接改成目标状态"实现，不写数据迁移、不保留兼容分支**。

---

## 4. P1 — 媒体文件迁到 R2

### 4.1 现状（基于代码）

- Curator 的默认磁盘是 `public`（`config/curator.php:21`，`CURATOR_DEFAULT_DISK` 可覆盖）。
- `public` 是本机磁盘，列在 `tenancy.filesystem.disks` 里，有 `root_override`（`storage/tenant{id}/app/public`）和 `url_override`（`public-%tenant%`）。URL 形如 `https://<租户域名>/public-{tenantId}/<path>`，靠 `TenantCreated` 时创建的 `public/public-{tenant}` 符号链接由 nginx 直接提供（`TenancyServiceProvider::events()`）。
- 媒体写入入口：`StoreMedia`（读 `curator.default_disk`）、`StoreCuratorUpload`（用上传组件的磁盘名）。
- 读取入口：`Media::url`（Curator 的 `resolveUrl()` → `Storage::disk($disk)->url($path)`）、`MediaResolver`（区块渲染时批量解析 URL）、`Business::logoUrl`（`app/Models/Business.php:83` **写死了 `disk('public')`**）、`RenderShareCard`（读取封面图字节）。
- 共享图库：`library` 磁盘（`config/filesystems.php:68`），本机，**故意不做租户前缀**，URL 在中心域名下（`/library`）；`AdoptLibraryPhoto` 会把字节复制到租户的媒体磁盘。
- Curator 的 Glide 路由（缩略图）已经被 `tenantizeCuratorRoute()` 包上了租户中间件。

**问题**：文件在单台机器的本地磁盘上。加第二台应用服务器后，图片会时有时无；图片流量全部经过应用服务器。

### 4.2 目标

- 新增磁盘 **`media`**：`s3` 驱动指向 R2，`url` 为 `https://media.ezsite.app`。
- `media` 加入 `tenancy.filesystem.disks`，**不加** `root_override` / `url_override`。stancl 对没有 `root_override` 的非本地磁盘会把 `root` 设为 `<原 root>/tenant{id}`（`FilesystemTenancyBootstrapper.php:175-180`），所以对象 key 是 `tenant{id}/<path>`。Laravel 的 S3 适配器在 `url()` 里会带上这个 root 前缀，于是 URL 为 `https://media.ezsite.app/tenant{id}/<path>`。**实现时第一件事就是写测试确认 URL 里确实带了前缀**（见 4.4）。
- 新增磁盘 **`library-r2`**（或直接把 `library` 改成 s3 驱动）：root 为 `library`，URL 为 `https://media.ezsite.app/library/...`，**不加入** `tenancy.filesystem.disks`（保持不做租户前缀，理由同现在的注释）。
- 生产环境 `CURATOR_DEFAULT_DISK=media`。开发和测试环境继续用本地的 `public`（测试里用 `Storage::fake('media')` 覆盖 R2 路径）。

### 4.3 改动清单

| 文件 | 改动 |
|---|---|
| `config/filesystems.php` | 新增 `media` 磁盘：`driver: s3`，`key/secret/bucket/endpoint/url` 分别读 `MEDIA_ACCESS_KEY_ID`、`MEDIA_SECRET_ACCESS_KEY`、`MEDIA_BUCKET`、`MEDIA_ENDPOINT`（`https://<account_id>.r2.cloudflarestorage.com`）、`MEDIA_URL`（`https://media.ezsite.app`）；`region: auto`；`use_path_style_endpoint: true`；`visibility: public`（R2 忽略 ACL，公开读靠自定义域名，设置 `visibility` 只是为了 Curator 走公开 URL 分支）。`library` 磁盘的驱动由环境变量决定（`LIBRARY_DISK_DRIVER`），或拆成两个磁盘，二选一，实现时选更少分支的那个 |
| `config/tenancy.php` | `filesystem.disks` 加入 `'media'` |
| `config/curator.php` | 不改代码，生产环境靠 `CURATOR_DEFAULT_DISK=media` |
| `app/Models/Business.php:83` | `Storage::disk('public')` 改为读 `config('curator.default_disk')`，和 `StoreMedia` 保持一致 |
| `app/Actions/Channels/RenderShareCard.php` | 已经用 `$cover->disk`，确认在 s3 磁盘上 `get()` 正常即可 |
| `app/Actions/Library/AdoptLibraryPhoto.php` | 从图库磁盘 `get()` 再 `put()` 到租户媒体磁盘，跨磁盘复制本来就是这样写的，确认在 R2 → R2 下正常 |
| Curator Glide | **要核实**：Glide 的 source 能否是 s3 磁盘（Curator 的 `GlideBuilder`），以及 Glide 缓存写到哪里。缓存在每台服务器本机各存一份是可以接受的（只是重复，不影响正确性）。如果 Glide 不支持 s3 source，缩略图改用原图 URL 加 CSS 尺寸，因为 `OptimizeImage` 在写入时已经把图片压到 12MP / 8MB 以内 |
| `TenancyServiceProvider` 的符号链接 | `CreateStorageSymlinks` / `RemoveStorageSymlinks` 只对本地 `public` 磁盘有意义；生产环境用 `media` 后它们只是空操作，**保留**，本地开发还要用 |

### 4.4 测试

- `StoreMedia` 在租户上下文里写入 `media`（`Storage::fake('media')`）：文件落在 `tenant{id}/` 前缀下；`Media::url` 以 `https://media.ezsite.app/tenant{id}/` 开头。
- 两个租户（按项目约定用 dataset，一个测试一个租户）各自的 URL 前缀不同。
- `Business::logoUrl` 在 `CURATOR_DEFAULT_DISK=media` 时返回 R2 URL。
- 图库采用：图库磁盘 → 租户 `media` 磁盘，复制后的 URL 带租户前缀。

### 4.5 验收

- 本地把 `.env` 指向一个测试 R2 bucket：上传、编辑器预览、已发布页面、分享卡片、Business logo 的图片都来自 `media.ezsite.app`。
- 应用服务器 `storage/` 下不再出现新的租户媒体文件。

### 4.6 可选：静态资源（Vite 构建产物）也走 Cloudflare

商家自有域名的请求不经过 Cloudflare，所以 `/build/*` 的 CSS/JS 会直接从应用服务器下载。可以设置 `ASSET_URL=https://ezsite.app`，让所有站点都从中心域名加载构建产物（经 Cloudflare 缓存）。注意：Vite 输出的是 `<script type="module" crossorigin>`，**跨域加载需要 CORS**，所以 nginx 要给 `/build/` 加 `Access-Control-Allow-Origin: *`。这一项不阻塞上线，流量大了再做。

---

## 5. P2 — 商家自有域名

### 5.1 现状

- `domains` 表：`domain`（唯一）、`tenant_id`（`no-rls`）。`CreateTenant` 只创建不带点的子域名标签；`Domain::getUrl()` 用"有没有点"区分子域名和完整域名。
- 未知域名 → `TenantCouldNotBeIdentifiedException` → 重定向到中心站（`bootstrap/app.php:40`）。
- 目前**没有**添加自有域名的界面，也没有任何状态字段。

### 5.2 产品决策（已定）

- 商家添加 `customer.com` 时，**同时登记 `customer.com` 和 `www.customer.com` 两条记录**，一条设为主域名（`is_primary`），另一条 301 跳到主域名。默认主域名是 `www`。
- DNS 指引：
  - `www` → **CNAME** `custom.ezsite.app`
  - 根域名 → **A 记录** 指向边缘机的保留 IP（`EDGE_IP`）
  - 商家如果用 Cloudflare 管理 DNS，**必须关闭代理（灰色云朵）**，否则 Let's Encrypt 的 HTTP-01 验证会失败
- 每个租户最多一组自有域名（根域名 + www，共 2 条）。要换域名就先删再加。
- 删除域名就是**删除这一行**（不做"停用"状态）。行删掉后 `ask` 会拒绝续期，Caddy 里的旧证书到期自然失效；租户识别也找不到这个域名，会走现有的"重定向到中心站"逻辑。

### 5.3 数据结构

新增迁移，给 `domains` 加列（遵守 `.ai/rules/migrations.md`：枚举用 string 列加 PHP backed enum）：

| 列 | 类型 | 说明 |
|---|---|---|
| `status` | `string`，默认 `active` | `App\Enums\DomainStatus`：`Pending`（等商家配 DNS 或等证书）、`Active`（HTTPS 已验证可用）。平台子域名永远是 `Active` |
| `is_primary` | `boolean`，默认 `false` | 自有域名中的主域名；平台子域名不参与 |
| `verified_at` | `timestampTz`，可空 | 第一次检查通过的时间 |
| `last_checked_at` | `timestampTz`，可空 | 最近一次 DNS 或 HTTPS 检查的时间 |
| `last_check_error` | `string`，可空 | 给商家看的最近一次失败原因（例如"A 记录指向 1.2.3.4，应为 x.x.x.x"） |

`Domain` 是中心模型（`domains.tenant_id` 是 `no-rls`），**不加** `RequiresTenantContext`，在 docblock 里写明原因（同 `tenancy.md` 的约定）。

**注意 `Pending` 不阻止租户识别**：只要请求到达，说明 DNS 已经指向我们，正常提供网站即可。`Pending` 只用于界面展示和通知。

### 5.4 Actions（`app/Actions/Domains/`）

| Action | 职责 |
|---|---|
| `AddCustomDomain` | 输入租户和商家填写的主机名。规范化：小写、去掉协议/路径/端口/末尾的点、IDN 转 punycode、去掉开头的 `www.` 得到根域名。校验：合法主机名；不是 `ezsite.app` 及其子域名；不在 `tenancy.identification.central_domains` 里；两条记录都没被占用；租户还没有自有域名。在一个事务里创建两条 `Pending` 记录并设置 `is_primary`。用 `DB::transaction()` |
| `CheckCustomDomain` | 对一条记录：① `dns_get_record()` 查 A/CNAME，根域名的 A 记录必须包含 `EDGE_IP`，`www` 的 CNAME 必须是 `custom.ezsite.app`（或者解析出的 A 记录包含 `EDGE_IP`，兼容 CNAME 展平）；② DNS 正确后请求 `https://<domain>/up`（超时 10 秒），这次请求会触发 Caddy 签发证书，返回 200 且证书有效就标记 `Active`、写 `verified_at`；③ 每一步失败都把原因写进 `last_check_error`。只更新 `last_checked_at` 不算状态变化 |
| `RemoveCustomDomain` | 删除该租户的两条自有域名记录 |
| `IsDomainAllowedForCertificate` | `/caddy/ask` 用的纯判断：主机名在 `domains` 里，并且带点（是完整域名，不是平台子域名标签）。`Pending` 和 `Active` 都允许（证书就是在 `Pending` 期间第一次签发的） |

所有 Action 按 `.ai/rules/actions.md` 写，用 `php artisan make:action` 生成（注意生成器会加 `Action` 后缀，生成后要改名）。

### 5.5 定时检查

- Job `CheckPendingCustomDomains`：每 5 分钟检查一次所有 `Pending` 记录；超过 72 小时仍是 `Pending` 的改为每小时一次。
- Job `RecheckActiveCustomDomains`：每天检查一次所有 `Active` 记录。连续 3 天失败就改回 `Pending`，并通知商家（"你的域名不再指向我们"）。
- 两个 job 都是中心上下文的读写（`domains` 是 `no-rls`），不需要 `RunInTenant`；调度加 `->onOneServer()`（需要 cache 锁，`database` 缓存驱动支持）。

### 5.6 `/caddy/ask` 端点

- 路由放在 `routes/web.php` 的中心域名分组里，名称 `central.caddy.ask`，单 action 控制器 `App\Http\Controllers\Central\CaddyAskController`（遵守 `.ai/rules/controllers.md`）。
- `GET /caddy/ask?domain=<host>&token=<secret>`：
  - `token` 用 `hash_equals()` 和 `config('services.caddy.ask_token')`（环境变量 `CADDY_ASK_TOKEN`）比较，不一致返回 403。
  - `IsDomainAllowedForCertificate` 通过返回 `200`，否则返回 `404`。
  - 不经过 session（不挂 `web` 中间件组里的 session/CSRF；参考 `tenantizeCuratorRoute()` 对 Glide 路由的处理），响应体为空。
- **Caddy 怎么访问到它**：在边缘机的 `/etc/hosts` 里把 `ezsite.app` 指到应用服务器的**内网 IP**。这样 `ask` 请求的 Host 是 `ezsite.app`，能命中中心域名路由，流量也只走内网，不经过 Cloudflare。
- 测试：token 错误返回 403；已登记的完整域名返回 200；未登记的返回 404；平台子域名标签返回 404；`Pending` 状态返回 200。

### 5.7 商家后台页面（租户面板）

- 位置：租户面板的设置区，新建一个 Filament Page（例如 `CustomDomain`）。弹窗类的操作放进该页面对应的 `Actions/` 目录（遵守 `.ai/rules/filament.md`）。
- 内容：
  - 没有自有域名时：一个输入框和"添加"按钮 → `AddCustomDomain`。
  - 有自有域名时：两条记录各一行，显示状态徽章、需要配置的 DNS 记录（类型、主机、值，带复制按钮）、`last_check_error`、"立即检查"按钮（→ `CheckCustomDomain`，同一条记录 1 分钟内只能点一次）、"删除"按钮（确认弹窗 → `RemoveCustomDomain`）。
  - 固定提示文字："如果你在 Cloudflare 管理 DNS，请关闭代理（灰色云朵）。"
- 中心面板的 `TenantResource` 加只读列，显示自有域名和状态，方便客服排查。
- 状态变成 `Active` 时发邮件通知商家（复用现有的邮件身份，见 `BuildSiteMailIdentity`）。

### 5.8 主域名跳转

- 新中间件挂在 `routes/tenant.php` 的分组里，放在 `InitializeTenancyByDomainOrSubdomain` 之后：当前 Host 是租户的非主自有域名时，301 跳到主域名的同一路径。
- **平台子域名不跳转**（保持可访问，编辑器、签名预览链接都依赖它）。是否把平台子域名的公开页面也跳到自有主域名（对 SEO 更好），**留到上线后再决定**，这一期不做。

### 5.9 其他要确认的点

- `Domain::getUrl()`、`PublicUrl` 在有自有域名时应该返回主自有域名（sitemap、canonical、邮件里的链接都依赖它）。实现时 grep 所有调用点，统一处理。
- 签名预览链接已经是相对签名（`signed:relative`），换域名后仍然有效，不用改。
- 租户面板（Filament）在自有域名上也能用，session cookie 是 host-only 的，不会串。

---

## 6. P3 — 生产配置进代码仓库

目标：基础设施初始化完成后（§7），日常上线只需要 `git push`。

### 6.1 `bootstrap/app.php` — 信任代理

现在 `withMiddleware()` 是空的。所有请求都经过 Cloudflare 或 Caddy，必须信任代理头，否则 `request()->ip()`（线索记录、限流）和 `https` 判断都是错的：

```php
$middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR
    | Request::HEADER_X_FORWARDED_HOST
    | Request::HEADER_X_FORWARDED_PORT
    | Request::HEADER_X_FORWARDED_PROTO);
```

`at: '*'` 的前提是**防火墙只允许 Cloudflare 和边缘机访问应用服务器**（§7.4）。真实访客 IP：Cloudflare 会在 `X-Forwarded-For` 里带上，Caddy 默认也会设置 `X-Forwarded-For`。加一个测试：带 `X-Forwarded-Proto: https` 的请求，生成的 URL 是 https。

### 6.2 `deploy/forge-deploy.sh`（应用服务器）

Forge 的零停机部署脚本以 Forge 自动生成的为准（`$CREATE_RELEASE()`、`$ACTIVATE_RELEASE()`、`$RESTART_QUEUES()` 这些宏由 Forge 提供）。只把**中间的构建步骤**换成调用仓库里的脚本：

```bash
#!/usr/bin/env bash
# 在 Forge 的 release 目录里执行（Forge 已经 cd 到 $FORGE_RELEASE_DIRECTORY）。
set -euo pipefail

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist \
  --optimize-autoloader --classmap-authoritative

npm ci --ignore-scripts
npm run build

$FORGE_PHP artisan migrate --force   # 迁移后自动执行 tenants:rls 同步策略
$FORGE_PHP artisan optimize          # config / route / event / view 缓存
$FORGE_PHP artisan filament:optimize
```

Forge 界面里的部署脚本：

```bash
$CREATE_RELEASE()
cd $FORGE_RELEASE_DIRECTORY
bash deploy/forge-deploy.sh
$ACTIVATE_RELEASE()
$RESTART_QUEUES()
```

`$ACTIVATE_RELEASE()` 之后 Forge 会重载 php-fpm，这是 `opcache.validate_timestamps=0` 能生效的前提，**不能删**。

### 6.3 `deploy/Caddyfile`（边缘机）

```caddy
{
	email {$ACME_EMAIL}
	on_demand_tls {
		ask http://ezsite.app/caddy/ask?token={$CADDY_ASK_TOKEN}
	}
}

# 所有自有域名：首次 HTTPS 请求时签发证书，之后自动续期
https:// {
	tls {
		on_demand
	}
	reverse_proxy {$APP_PRIVATE_IP}:80 {
		header_up X-Forwarded-Proto https
	}
}

# HTTP 统一跳 HTTPS。ACME HTTP-01 验证请求由 Caddy 在路由之前处理，不受这个跳转影响
http:// {
	redir https://{host}{uri} 308
}
```

- `reverse_proxy` 默认保留原始 Host 头，不要加 `header_up Host`。
- 以后加应用服务器：`reverse_proxy {$APP_PRIVATE_IP} {$APP2_PRIVATE_IP}:80 { lb_policy round_robin  health_uri /up ... }`。
- **要核实**：`https://` 加 `http://` 两个全匹配站点块在 On-Demand 下的行为，以及 HTTP-01 验证不受 `redir` 影响。在测试边缘机上用一个测试域名跑通再上线。

### 6.4 `deploy/edge-deploy.sh`（边缘机，Forge 部署脚本调用）

```bash
#!/usr/bin/env bash
set -euo pipefail
sudo cp deploy/Caddyfile /etc/caddy/Caddyfile
sudo caddy validate --config /etc/caddy/Caddyfile --adapter caddyfile
sudo systemctl reload caddy
```

Caddy 用的环境变量（`ACME_EMAIL`、`CADDY_ASK_TOKEN`、`APP_PRIVATE_IP`）写在 `/etc/caddy/env`，由 systemd unit 的 `EnvironmentFile=` 加载，**不提交进仓库**。

### 6.5 `.env.production.example`

列出生产环境必须设置的变量（值留空或给示例），P4 第 3 步照着填：

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ezsite.app

DB_CONNECTION=pgsql
DB_HOST=            # DO 托管 Postgres 的私有主机名
DB_PORT=25060       # 直连端口；不要用连接池端口
DB_DATABASE=
DB_USERNAME=doadmin
DB_PASSWORD=
DB_SSLMODE=require

TENANCY_RLS_USERNAME=
TENANCY_RLS_PASSWORD=

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

CURATOR_DEFAULT_DISK=media
MEDIA_ACCESS_KEY_ID=
MEDIA_SECRET_ACCESS_KEY=
MEDIA_BUCKET=
MEDIA_ENDPOINT=https://<account_id>.r2.cloudflarestorage.com
MEDIA_URL=https://media.ezsite.app

CADDY_ASK_TOKEN=
EDGE_IP=            # 边缘机的保留 IP，显示在商家 DNS 指引里
CUSTOM_DOMAIN_CNAME_TARGET=custom.ezsite.app

# 邮件、AI、图库（Pexels）等其余变量见 .env.example，按需填写
```

`EDGE_IP` 和 `CUSTOM_DOMAIN_CNAME_TARGET` 通过 `config/services.php` 读取，代码里不要直接用 `env()`。

---

## 7. P4 — 一次性搭建基础设施

### 7.1 DigitalOcean

1. 建 VPC（所有资源放同一个区域，例如 NYC3）。
2. 建**托管 Postgres**（2 vCPU / 4 GB，版本和本地开发一致），放在同一个 VPC 里。用 `doadmin` 连上后先验证：
   ```sql
   SELECT rolname, rolsuper, rolbypassrls, rolcreaterole FROM pg_roles WHERE rolname = 'doadmin';
   ```
   `rolbypassrls` 和 `rolcreaterole` 都必须是 `t`。**任何一个是 `f` 就停下来**，换成在 Droplet 上自建 Postgres（用 Forge 的数据库服务器）。
3. 数据库的可信来源（Trusted Sources）只加应用服务器。**不创建连接池**。
4. 建两台 Droplet，都由 Forge 创建（Forge 连接 DigitalOcean 账号）：
   - `app-1`：8 vCPU / 16 GB，PHP 8.5，Node
   - `edge-1`：1 vCPU / 1–2 GB
5. 给 `edge-1` 分配一个**保留 IP**，这个值就是 `EDGE_IP`。**这个 IP 一旦公布给商家就不能变**，以后换机器只把保留 IP 挂到新机器上。

### 7.2 Forge — `app-1`

1. 新建站点 `ezsite.app`，设为 **默认站点**（nginx `default_server`，接收所有 Host）；Aliases 不用填。
2. 连接 GitHub 仓库，分支 `main`，开启 **零停机部署** 和 **Quick Deploy**。在 GitHub 上把 `main` 设为必须通过 `tests.yml` 和 `lint.yml` 才能合并，这样只有 CI 通过的代码会部署。
3. 环境变量照 `.env.production.example` 填。
4. 部署脚本照 §6.2。
5. SSL：安装 **Cloudflare Origin CA 证书**（在 Cloudflare 生成，覆盖 `ezsite.app` 和 `*.ezsite.app`，有效期 15 年），作为自定义证书装到这个站点上。
6. nginx：同时监听 80（只给内网的 Caddy 用，**不要**跳转 HTTPS）和 443（给 Cloudflare 用）。Forge 默认在装证书后会把 80 跳到 443，要在站点的 nginx 配置里去掉这个跳转。
7. PHP 设置：
   ```ini
   opcache.enable=1
   opcache.memory_consumption=256
   opcache.interned_strings_buffer=32
   opcache.max_accelerated_files=50000
   opcache.validate_timestamps=0
   ```
   **不开** `opcache.jit`，**不开** preload。
8. 守护进程：`php artisan queue:work --sleep=3 --tries=3 --max-time=3600`。
9. 开启 Scheduler。
10. 开启 Droplet 自动备份。

### 7.3 Forge — `edge-1`

1. 安装 Caddy（官方 apt 源）。暂时不需要编译插件。
2. 新建一个站点并连接同一个仓库，部署脚本只执行 `bash deploy/edge-deploy.sh`。
3. 写 `/etc/caddy/env`（§6.4），在 systemd unit 里加 `EnvironmentFile=/etc/caddy/env`。
4. `/etc/hosts` 加一行：`<app-1 内网 IP> ezsite.app`（§5.6）。
5. 开启 Droplet 自动备份。Caddy 的证书存在 `/var/lib/caddy`，机器坏了从备份恢复即可。原因：新机器重新签发几千张证书可能撞上 Let's Encrypt 每个账户每 3 小时 300 个新订单的限制。以后如果想让边缘机可以随手重建，再加 S3/R2 存储插件。

### 7.4 防火墙（DigitalOcean Cloud Firewall）

| 机器 | 入站规则 |
|---|---|
| `app-1` | 443：只允许 [Cloudflare IP 段](https://www.cloudflare.com/ips/)；80：只允许 VPC 内网；22：Forge 的 IP |
| `edge-1` | 80、443：全部；22：Forge 的 IP |

### 7.5 Cloudflare（`ezsite.app` zone）

| 记录 | 类型 | 值 | 代理 |
|---|---|---|---|
| `ezsite.app` | A | `app-1` 公网 IP | 开（橙色） |
| `*.ezsite.app` | A | `app-1` 公网 IP | 开（橙色） |
| `custom.ezsite.app` | A | `EDGE_IP` | **关（灰色）** |
| `media.ezsite.app` | — | R2 bucket 的自定义域名（在 R2 设置里绑定，自动创建记录） | 开 |

- SSL/TLS 模式：**Full (strict)**。
- 缓存：保持默认（不缓存 HTML）。`media.ezsite.app` 加一条缓存规则：边缘缓存 1 个月。媒体文件名都带 uuid，内容不会变。

### 7.6 R2

1. 建 bucket（例如 `ezsite-media`），**不开** `r2.dev` 公开地址，只通过 `media.ezsite.app` 访问。
2. 建 API token，权限只给这个 bucket 的对象读写，填到 `MEDIA_ACCESS_KEY_ID` 和 `MEDIA_SECRET_ACCESS_KEY`。
3. 数据库备份：Forge 的数据库备份对托管 Postgres 不适用，托管 Postgres 自带每日备份和 7 天时间点恢复，够用。另外每周用 `pg_dump` 导出一份到 R2 的独立 bucket（Scheduler 任务或 Forge 定时任务），防止 DigitalOcean 账号层面出问题。

### 7.7 上线前冒烟测试

- [ ] `https://ezsite.app` 能访问，注册一个测试租户
- [ ] `https://<测试租户>.ezsite.app` 能访问，编辑器能用，发布后页面正确
- [ ] 上传一张图，URL 是 `https://media.ezsite.app/tenant{id}/...`
- [ ] 用一个测试域名按 DNS 指引配置根域名和 www → 后台状态变成 `Active`，两个地址都是有效证书，非主域名 301 到主域名
- [ ] 提交线索表单，`leads` 表里记录的 IP 是真实 IP，不是 Cloudflare 或 Caddy 的 IP
- [ ] 直接用 IP 访问 `app-1:443`（不经过 Cloudflare）被防火墙拒绝
- [ ] `curl 'http://ezsite.app/caddy/ask?domain=random.com&token=wrong'` 从公网访问返回 403（token 错误）
- [ ] 两个测试租户互相看不到对方的页面和图片（RLS 加对象 key 前缀）

---

## 8. P5 — 监控与日常运维

### 8.1 告警（只告警，不巡检）

| 监控项 | 工具 | 阈值 |
|---|---|---|
| 应用错误、慢请求 | Laravel Nightwatch 或 Sentry（免费额度） | 新错误类型即通知 |
| 可用性 | 外部拨测（Better Stack / UptimeRobot 免费版） | 3 个地址：`ezsite.app`、一个平台子域名站点、一个自有域名站点 |
| 证书 | Caddy 日志；`/caddy/ask` 返回 404 的次数 | 每小时被拒绝超过 100 次就告警（通常是有人滥用） |
| 数据库 | DigitalOcean 自带告警 | CPU、磁盘、连接数超过 80% |
| Droplet | DigitalOcean 自带告警 | CPU 持续 5 分钟超过 80%，或磁盘超过 80% |

### 8.2 运维手册

| 场景 | 做法 |
|---|---|
| **负载高，加一台应用服务器** | Forge 开 `app-2`（同配置），连同一个仓库，复制 `.env`；**不要**开第二个 Scheduler；数据库可信来源加 `app-2`；在 `deploy/Caddyfile` 的 `reverse_proxy` 里加 `app-2` 内网 IP 并 push；Cloudflare 的 A 记录要么指向一个负载均衡器，要么在 Caddy 前加一个 `ezsite.app` 站点块（加第二台时再定，当前单台不需要） |
| **边缘机坏了** | 从 Droplet 备份恢复，或开新机器加恢复 `/var/lib/caddy`，然后把保留 IP 挂过去 |
| **商家说域名打不开** | 先看后台的 `last_check_error`；再查 `dig` 结果是否指向 `EDGE_IP` 或 `custom.ezsite.app`；再看 Caddy 日志里这个域名的签发错误 |
| **备份恢复演练** | 每季度一次：用 DigitalOcean 的时间点恢复建一个临时库，让一台临时 Droplet 连上去，确认能登录、能看到页面 |
| **系统更新** | Forge 默认开启 unattended-upgrades；PHP 小版本在 Forge 里升级 |

---

## 9. 成本（10,000 个网站，单台应用服务器）

假设：每站每月约 1,000 次访问（合计约 1,000 万次/月，平均 4 次请求/秒，高峰约 40 次/秒）；每站平均 200 MB 媒体；30% 绑定自有域名。

| 项目 | 月费（约） |
|---|---|
| Forge Growth | $19 |
| `app-1`（8 vCPU / 16 GB） | $96 |
| `edge-1`（1 vCPU / 1–2 GB）+ 保留 IP | $6–12 |
| DigitalOcean 托管 Postgres（2 vCPU / 4 GB） | $60 |
| Droplet 备份（20–30%） | $20–30 |
| R2 存储（约 2 TB，出网免费） | $30 |
| Cloudflare zone（Free；需要 WAF 就上 Pro） | $0–25 |
| 自有域名证书（Let's Encrypt） | $0 |
| **合计** | **约 $230–270** |

**不包括**：AI 调用费用（可能是最大的变量，要单独按用量核算）、事务邮件（约 $10 以内）。

价格是 2026-11 按公开价格估算的，正式预算前在 DigitalOcean、Forge、Cloudflare 官网核对。

---

## 10. 实现前要核实的点（清单）

| # | 问题 | 在哪个阶段核实 | 不成立怎么办 |
|---|---|---|---|
| 1 | `doadmin` 有 `BYPASSRLS` 和 `CREATEROLE` | P4 §7.1 | 改用 Forge 管理的自建 Postgres Droplet（4 vCPU / 8 GB，约 $48 加备份） |
| 2 | s3 磁盘加 stancl 的 root 前缀后，`url()` 带 `tenant{id}/` | P1 第一个测试 | 给 `media` 磁盘加 `url_override` 或自定义 URL 生成 |
| 3 | Curator 的 Glide 能以 s3 磁盘为 source | P1 | 缩略图直接用原图 URL（写入时已经压缩过） |
| 4 | Caddy On-Demand 加全匹配 `http://` 跳转不影响 HTTP-01 验证 | P3，用测试边缘机 | 去掉 `http://` 块，只依赖 Caddy 自带的 HTTP 跳转 |
| 5 | Forge 零停机部署脚本的宏名称 | P4 §7.2 | 以 Forge 生成的默认脚本为准，只替换中间的构建步骤 |
| 6 | Forge 装证书后关掉 80 → 443 跳转，不会在重新部署时被覆盖 | P4 §7.2 | 80 端口改成只给 Caddy 用的独立 nginx server 块 |
