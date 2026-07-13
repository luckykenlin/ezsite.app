# 商家数据模型 — Brainstorm 存档

> 状态：**头脑风暴阶段，尚未写代码**。本文档记录商家信息（Business / Location）建表的架构讨论与初步 schema 设计，下次继续时先过一遍对齐上下文。
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
businesses ───1:N──▶ locations ───1:N──▶ location_hours
   │                     │
   │                     └──1:N──▶ location_special_hours
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
| tenant_id | uuid, unique, FK→tenants, **`no-rls`** | 见下方 RLS 说明 |
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

### `location_hours`（规律营业时间，按 GBP 结构建模）

| 列 | 类型 | 说明 |
|---|---|---|
| id | bigint PK | |
| tenant_id | uuid, RLS-scoped | |
| location_id | FK | |
| day_of_week | tinyint 0–6 | |
| open_time / close_time | time | |
| is_closed | bool | 当天休息 |

- 一天允许**多行**（午市/晚市分段，餐馆常见）。
- 特殊日期（节假日）单独放 `location_special_hours`（date, open/close/is_closed）。
- 该结构能直接映射 GBP 的 `regularHours` / `specialHours`。
- 简化替代：营业时间存 location 上的一个 JSON 列。v1 够用，但接 GBP 时要转结构 —— 因此倾向一步到位用结构化表。

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

- **`businesses.tenant_id` 加 `->comment('no-rls')`**：理由与现有 `domains.tenant_id`、`tenant_user.tenant_id` 一致 —— central 面板要**跨 tenant 列出/搜索所有客户商家**（agency 花名册），必须在 tenancy 解析之前就能查。因为是 1:1，租户内读取自己的 business 用 `where tenant_id = 当前` 即可，不依赖 RLS。
- **`locations` / `location_hours` / `social_accounts` / `reviews` 的 `tenant_id` 走正常 RLS**：跟 `Post` 一样，不加 trait、不加 global scope，隔离完全交给自动生成的 RLS 策略。子表各自带 `tenant_id` 列，RLS 才能逐表隔离。

## 开放问题（待拍板 → 才动手建 migration/model/factory）

1. **品牌色放哪？**
   - 方案 a（倾向）：`businesses` 存"品牌色源值"，网站 design token 从中派生。理由：品牌色是商家身份，社交发帖 / 评论回复 / 白标都要用，不该只属于网站。
   - 方案 b：颜色完全归 PLAN.md 的 Design Token / Style Preset 系统，`businesses` 不存色，只存 logo。
2. **`businesses` 字段颗粒度是否够 v1？** 是否要加：预约链接、菜单 URL、Yelp/大众点评等外部 profile 链接？
3. **落地范围**：是否先只建 v1 的 `businesses` + `locations` + `location_hours`(+`special_hours`)，把 `social_accounts` / `reviews` 留到真正做社交模块时再建？

## 下一步

确认开放问题后，按 `php artisan make:model`（含 factory/seeder）动手，并在 `tests/Tenancy/*` 补 RLS/隔离测试（真实 Postgres）。

---
*本文档记录头脑风暴结论，尚未开始编码。*
