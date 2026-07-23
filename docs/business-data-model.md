# 商家数据模型 — Brainstorm 存档

> 状态：**v1 已落地** —— `businesses` + `locations` 两张表、模型、工厂、RLS/隔离测试已建。三个开放问题已拍板（见下）。**营业时间已落地**（`locations.opening_hours` JSON 列 + `spatie/opening-hours`，见下方"营业时间"）。社交/口碑预留表、Filament 后台、block 绑定仍待做。
> 关联文档：[PLAN.md](../PLAN.md)（网站构建器第一阶段）、[.claude/docs/tenancy.md](../.claude/docs/tenancy.md)（多租户内部机制）。

## 背景与目标

在现有的 **单库 + Postgres RLS 多租户** 网站构建器之上，需要为"商家信息"建表（business profile、location 等）。

第一个版本（v1）只做**网站生成**，但数据模型要同时考虑到后续的 **Vista Social 类社交媒体运营业务**（多平台发帖、内容日历、审批流、AI 文案、统一收件箱、评论/口碑管理、数据分析、白标、批量排程、Link in Bio 等），避免以后大改。

## 已锁定的架构判断

### 判断 1：Tenant = 客户商家（1:1），Agency = central 面板本身

关键前提（用户确认）：**"我自己就是 Agency，不打算对外扩展给 agency 使用。"**

因此 "agency" 这一层不是一个 tenant，而是**用户操作的 central 面板**（super_admin 视角）。每个客户商家 = 一个 Tenant，理由：

- 每个客户网站有独立域名/子域名 → 现有 `InitializeTenancyByDomainOrSubdomain` 靠 domain 解析出 tenant → RLS 按 tenant 隔离每个网站的数据。**这套已经在跑，直接复用。**
- 若反过来把所有客户塞进一个 tenant，会丢掉按域名路由和 per-site RLS 隔离，等于重造轮子。

推论：

- **不需要新建 "agency" 概念**。
- **不用改 `Page` / `Domain`** —— 它们已是 tenant-scoped，一个 tenant 就一个商家，天然对应。
- 现有的 `TenantResource`（central）就是"客户花名册"。
- 将来 Vista Social 的跨客户看板 = 在 central 面板跨 tenant 聚合（super_admin 视角）。

### 判断 2：事实型数据单一真相源，叙事型文案留在 block（混合模式）

（对应"网站内容 vs 商家资料"关系，用户交由我方决定。）

- **事实型数据**（名称/地址/电话 NAP、营业时间、社交链接）→ 存 `businesses` / `locations` 表。
  网站的 Contact / Footer / Hours / Map 这类 block **引用**它（block config 存 `{"bind":"location","location_id":X}`），**不复制**。改一处，网站 + GBP + 评论回复署名全部同步 —— 正是 Vista Social 需要的。
- **叙事型文案**（Hero 标题、About 故事、卖点文案）→ 留在 Fabricator 的 blocks JSON 里，因为这是网站专属创意内容，也正是 PLAN.md 里 AI 聊天编辑器要改的东西。

这样既不破坏 PLAN.md "聊天只改内容和 token" 的范围，又让结构化事实 DRY 且可复用到社交业务。

## 用户对四个前置问题的回答

| 问题 | 回答 |
|---|---|
| 付费账户（Tenant）代表什么 | 我自己就是 Agency，不对外扩展给 agency 使用 |
| 一个商家是否多门店 | 是，一个商家多个 location |
| 网站内容与商家资料的关系 | 由我方决定 → 采用上面的"混合模式" |
| 现在要为哪些方向预留 schema 钩子 | 社交账号（多平台）、Google Business Profile、评论/口碑管理；**v1 只专注网站** |

## ERD 全景

```
tenants (已存在, central, 无RLS)         ← 客户商家的身份/注册表
   │ 1:1
   ▼
businesses ───1:N──▶ locations
   │                     │ opening_hours (JSON 列, spatie/opening-hours; 含 exceptions)
   │
   ├──1:N──▶ social_accounts   (预留, v1 不建/建空壳)
   └──1:N──▶ reviews           (预留)
                 └── 也可挂 location_id

pages / page_blocks (已存在, tenant-scoped) ──bind──▶ locations / businesses
```

实线 = v1 建；虚线/预留 = 现在定好形状、以后再填功能。

## v1 要建的表

### `businesses`（1:1 tenant，商家档案）

| 列 | 类型 | 说明 |
|---|---|---|
| id | bigint PK | |
| tenant_id | uuid, unique, FK→tenants, **RLS-scoped** | 见下方 RLS 说明（已从原计划的 no-rls 改为受 RLS 保护） |
| name / display_name | string | 对外展示名 |
| slug | string | 用 `#[Sluggable]` 自动生成 |
| category | string | **字符串 + 注册表**，不写死 enum（同 PLAN.md §4 对 block type 的约定；行业气质将来接 Style Preset） |
| tagline | string, null | |
| description | text, null | |
| logo_path | string, null | 需 `->visibility('public')` |
| brand_primary / secondary / accent | string, null | 品牌色（**待拍板**，见下方开放问题 1） |
| contact_email / contact_phone | string, null | 总部级联系方式（门店级放 locations） |
| website_url | string, null | 外部官网（如有） |
| timezone / locale / currency | string, null | 排程发帖、时间显示要用 |
| status | string | draft / active / archived |
| timestamps, softDeletes | | |

### `locations`（1 business : N，门店）

| 列 | 类型 | 说明 |
|---|---|---|
| id | bigint PK | |
| tenant_id | uuid, FK, **RLS-scoped** | 走 `Post` 那套，纯 RLS 隔离 |
| business_id | FK→businesses | |
| label | string | "Downtown"、"总店" |
| is_primary | bool | 主门店 |
| address_line1 / line2 / city / state / postal_code / country | string | |
| latitude / longitude | decimal, null | 地图 & GBP |
| phone / email | string, null | 门店级联系方式 |
| timezone | string, null | 营业时间按门店 |
| status | string | |
| timestamps | | |

### 营业时间：`locations.opening_hours`（JSON 列 + `spatie/opening-hours`）—— 已落地

**决定改用 `spatie/opening-hours` 值对象 + 单个 JSON 列，放弃原计划的 `location_hours` / `location_special_hours` 两张结构表。**

理由：`spatie/opening-hours` 的 `OpeningHours` 值对象天然把"规律营业时间 + 特殊日期（exceptions）"装在一起，且自带 `isOpenAt()` / `nextOpen()` / `currentOpenRange()` 等查询能力,无需自己写。原来"结构表才好接 GBP"的顾虑被包解决了 —— 它的 `asStructuredData()` / `createFromStructuredData()` 用的正是 schema.org `OpeningHoursSpecification`（GBP 消费的同一格式）。

实现：

- `locations.opening_hours` 是 `json` 列(nullable),存 **schema.org structured data**,由 `App\Casts\OpeningHours` 双向转换。
- 读出来是 `Spatie\OpeningHours\OpeningHours` 值对象,并把 location 自己的 `timezone` 列注入,所有查询按门店本地时区解析。
- **赋值只接受 `OpeningHours` 实例或 `null`**(用 `OpeningHours::create([...])` 构造),不收裸数组 —— 让 authoring 格式在调用点(工厂/表单/测试)就被校验,同时规避 `create()` 那个 sealed array-shape 在 phpstan level max 下的摩擦。
- 一天可**多段**(午市/晚市: `['09:00-12:00','13:00-17:00']`);特殊日期走 `exceptions`(节假日闭店 `['2026-12-25' => []]` 或临时时段)。
- 工厂状态:`alwaysOpen()`(7×24)、`withoutOpeningHours()`(null)。
- 文件:`app/Casts/OpeningHours.php`、`app/Models/Location.php`(cast + `opening_hours` 列)、`database/factories/LocationFactory.php`、`tests/Unit/Models/LocationTest.php`。
- RLS 不受影响:只是 `locations` 上的一个列,隔离仍由 `locations.tenant_id` 的 RLS 策略负责,无新表。

## 预留钩子（现在定形状，v1 不做功能）

### `social_accounts`

```
id, tenant_id(RLS), business_id, location_id(null, GBP按门店/其余按商家),
platform(string: facebook|instagram|tiktok|linkedin|x|pinterest|youtube|google_business|threads|bluesky),
platform_account_id, handle, access_token(encrypted), refresh_token(encrypted),
token_expires_at, scopes(json), status, connected_at, meta(json)
```

### `reviews`

```
id, tenant_id(RLS), business_id, location_id(null), source_platform(string),
external_review_id, author_name, rating(int), content(text),
reply_content(text null), reply_status(string), reviewed_at, replied_at, meta(json)
```

> Vista Social 的 posts / 内容日历 / 媒体库 / 审批流将来同样按 `tenant_id + business_id` 挂，形状与上面一致，现在不展开。

## RLS 放置（对齐 CLAUDE.md / tenancy.md 约定）

> **更新（后于本文档初稿）**：`businesses` 已改为**受 RLS 保护**（`tenant_id` 去掉了 `no-rls`），不再是 no-rls 花名册。`RlsPolicyTest` 断言 businesses 在策略集内，`tenancy.md` 的 Models 一节也把 Business 列为 "all RLS-only"。下面保留原始推理供追溯，实际以本更新为准。

- **`businesses` 走正常 RLS**（与 `locations` 一致）：`tenant_id` 直接 FK 到 `tenants`、单跳 RLS 策略。`Business` 模型 use `RequiresTenantContext` 守卫写入（见 [tenant-write-context.md](./tenant-write-context.md)）。
  - central 面板仍能**跨 tenant 列出/搜索所有客户商家**（agency 花名册）——靠的是 central 角色 BYPASSRLS **读**所有租户，而不是 no-rls；写回单个租户走 `RunInTenant`。
  - ~~原计划：`businesses.tenant_id` 加 `->comment('no-rls')`，理由是花名册要在 tenancy 解析前可查。~~ 已废弃：读花名册靠 BYPASSRLS 即可，no-rls 会让 businesses 零隔离，得不偿失。
- **`locations` / `social_accounts` / `reviews` 的 `tenant_id` 走正常 RLS**：跟 `Post` 一样，不加 global scope，隔离完全交给自动生成的 RLS 策略；写入由 `RequiresTenantContext` 守卫。子表各自带 `tenant_id` 列，RLS 才能逐表隔离。（营业时间已不是独立表，而是 `locations.opening_hours` JSON 列，隔离随 `locations` 走。）

## 开放问题（已拍板）

1. **品牌色放哪？** → **方案 a**：`brand_primary` / `brand_secondary` / `brand_accent` 存在 `businesses`，作为品牌身份源值，网站 design token 从中派生。
2. **`businesses` 字段颗粒度是否够 v1？** → **v1 不加**外部链接类字段（预约链接、菜单 URL、Yelp/大众点评 profile 等），保持精简，需要时再 migration。
3. **落地范围** → **只先建 `businesses` + `locations`**。`location_hours`(+`special_hours`)、`social_accounts` / `reviews` 全部暂缓，后面慢慢加。

## 实现要点（v1 已完成）

- `businesses.tenant_id`：`->unique()`，受正常 RLS 保护（1:1；花名册的跨租户读靠 central BYPASSRLS，不靠 no-rls）；`locations.tenant_id`：正常 RLS（照 `Post`）。两者的模型均 use `RequiresTenantContext` 守卫写入。
- 模型依赖 `nunomaduro/essentials` 全局 unguard，**不加 `#[Fillable]`**（`Post` 旧的也已移除）。
- `Business` slug 由 `#[Sluggable(from: 'name')]`（`nunomaduro/laravel-sluggable`）自动生成，Filament 表单无需手填。
- 文件：`app/Models/{Business,Location}.php`、`database/migrations/*_create_{businesses,locations}_table.php`、`database/factories/{Business,Location}Factory.php`、`tests/Tenancy/BusinessAndLocationTest.php`；`tests/Tenancy/PostgresRlsTest.php` 加了 `toContain('locations')` / `not->toContain('businesses')`。

## 下一步（未做）

社交/口碑预留表 → central `BusinessResource` 与 tenant location 管理（Filament，含营业时间编辑 UI）→ page block 的 `{"bind":...}` 引用机制与 brand_* 派生 design token。
