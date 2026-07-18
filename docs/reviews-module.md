# 评论 / 口碑模块（Reviews）— 模块设计 + 修订版 v1 排序

> 状态：**设计定稿，待实现**。这是"从建站工具升级为华人获客操作系统"落地的第一个 growth-OS 模块，也是 `business-data-model.md` 里预留的 `reviews` 表的填肉方案。
> 依赖：[tenant-write-context.md](./tenant-write-context.md)（cron/webhook 写必须走三道闸）必须**先落地**。
> 关联：[PLAN.md](../PLAN.md)（建站，保持不变）· [business-data-model.md](./business-data-model.md)（reviews 预留表）· [REVIEW.md](../REVIEW.md)

## 0. 为什么是评论,为什么现在

回到业务判断:建站是最商品化、最易被客户自助替代的一层;评论管理相反——**它是本地 SEO / 地图 3-pack 排名地基,是 AI 搜索推荐的核心信号,是老板愿意长期付月费的服务,而且 AI 双语回复能立刻演示价值**。技术上它是「拉外部数据 → AI 生成草稿 → 人审 → 回推平台」的闭环,正好把 [tenant-write-context.md](./tenant-write-context.md) 那套带外写入地基练出来,后续所有 growth-OS 模块(社媒发帖、AI 接线、campaign)都复用同一套。

**红线(合规,来自业务蓝图)**:AI **绝不自动发布**回复。差评尤其必须人审。所有回复默认 `draft`,经 super_admin 在面板批准后才推平台。

## 1. 修订版 v1 排序(建站不动)

建站计划(PLAN.md 的 Token+Variant + 聊天编辑器)**原样保留、并行推进**。本次只是在它之前插入一段共享地基,并在其后接上第一个 growth-OS 模块:

| 阶段 | 内容 | 依赖 |
|---|---|---|
| **0. 数据完整性快修** | REVIEW §5.1:M1 slug 唯一、M2/L1 索引、删空 Helpers、L5 时间戳、M6 钉死 tenancy | 无,现在做最便宜 |
| **1. 带外写入地基** | `RunInTenant` + `RequiresTenantContext` + `TenantAware` + 写路径测试 | 阶段 0 |
| **2a. 建站(照旧)** | PLAN.md 全部范围,不改 | 阶段 1(mutation Action 建在地基上) |
| **2b. 评论模块 MVP** | 本文档 §2–§6 | 阶段 1 |
| **3. 运营驾驶舱** | central 跨租户评论队列(见 §5),后续接社媒/线索 | 2b |

2a 和 2b 可并行。关键点:**阶段 1 是两者共同的前置**——建站的 AI mutation 和评论的 cron/webhook 写,都不能在 central 上下文裸写 RLS 表。

## 2. 数据模型

沿用 `business-data-model.md` 的 `reviews` 预留形状,补齐 MVP 所需列。新增一张 `review_requests`(催评,是"评论新鲜度增长引擎"的来源,业务蓝图明确要)。

### `reviews`(RLS 作用域,挂 `RequiresTenantContext`)

| 列 | 类型 | 说明 |
|---|---|---|
| id | bigint PK | |
| tenant_id | uuid, FK, **RLS-scoped** | 照 `Post`,纯 RLS |
| business_id | FK→businesses | |
| location_id | FK→locations, null | 门店级评论 |
| source_platform | string(注册表,不写死 enum) | `google` \| `yelp` \| `facebook` \| `manual` … 同 PLAN §4 约定 |
| external_review_id | string, null | 平台侧 id,拉取去重 |
| author_name | string | |
| rating | smallint | 1–5 |
| content | text, null | |
| reviewed_at | timestamp | 平台上的发表时间 |
| reply_content | text, null | 我方回复正文 |
| reply_status | string | `none` \| `draft` \| `approved` \| `published` \| `failed` |
| reply_language | string, null | `zh` \| `en`,双语回复用 |
| replied_at | timestamp, null | |
| meta | json, null | 平台原始 payload、头像 url 等 |
| timestamps | | |

约束/索引:
- `unique(source_platform, external_review_id)` **部分索引 `WHERE external_review_id IS NOT NULL`**(拉取幂等去重;`manual` 录入的 null 不受约束——避开 REVIEW M1 同款 `NULLS DISTINCT` 陷阱,显式用部分唯一)。
- `index(tenant_id)`(RLS 热路径,承接 REVIEW M2 的教训——**建表就带索引**)。
- `index(['tenant_id', 'reply_status'])`(驾驶舱"待回队列"查询)。

### `review_requests`(催评,RLS 作用域)

| 列 | 类型 | 说明 |
|---|---|---|
| id | bigint PK | |
| tenant_id | uuid, **RLS** | |
| business_id / location_id | FK | |
| channel | string | `sms` \| `email` |
| recipient | string | 手机号/邮箱(合规:见 §6) |
| status | string | `queued` \| `sent` \| `clicked` \| `converted` \| `failed` |
| token | string, unique | 短链追踪(归因用) |
| sent_at / clicked_at | timestamp, null | |
| timestamps | | |

> 发送本身复用未来的 campaign/通知层;MVP 先落表 + 生成带 token 的 GBP 评价短链,发送可先手动/占位。

## 3. 待你拍板的决定(影响实现,先定)

1. **MVP 先接哪个平台?**
   - **推荐:Google(GBP)优先** —— 本地生意 90% 的评论权重在 Google,且 Google Business Profile API 支持**读评论 + API 回复**(闭环完整)。需 GBP API 项目审批(有申请门槛,提前排)。
   - **Yelp:只读、且受限** —— Yelp Fusion API 只返回 3 条节选、**不支持 API 回复**。MVP 建议 Yelp 走"只读展示 + 引导到 Yelp 后台人工回",或直接延后。
   - **`manual` 通道必做** —— 手工/CSV 录入,让模块在任何平台 API 就绪前就能对客户产生价值,也是 demo 抓手。
   → **建议 MVP = Google API + manual,Yelp 延后。** 你拍板。
2. **回复语言策略**:默认按评论语言镜像(中文评论中文回、英文英文回),还是双语都回?(业务蓝图强调"真双语本地化",建议:镜像 + 可人工切换。)
3. **催评 `review_requests` 是否进 MVP**,还是先只做"读取 + AI 回复",催评放 2b.5?(建议:先读取+回复,催评紧随。)

## 4. 组件(全部建在带外写入地基上)

```
拉取(cron, central 发起)   →  TenantAware job:SyncReviews(tenantId)
                                └─ RunInTenant 内:GBP API 拉 → upsert reviews(external_review_id 去重)
生成草稿(AI)                →  Action:DraftReviewReply(Review): laravel/ai 出双语草稿 → reply_status=draft
                                └─ 由 super_admin 手动触发,或拉取后自动生成草稿(仍不发布)
人审 & 批准                  →  Filament(见 §5):编辑 reply_content → approve → reply_status=approved
发布                        →  Action:PublishReviewReply(Review): 推平台 → published/failed
                                └─ 从 central 面板触发时必须 RunInTenant($client, ...) 包住
webhook(平台有推送时)       →  控制器零上下文进来 → 解析出 tenant → RunInTenant 内 upsert
```

关键实现点:
- `SyncReviews` **继承 `TenantAware`**(§tenant-write-context 闸 3);调度器 `routes/console.php` 里 `foreach(Tenant::all())` dispatch——**这是典型的 central 发起写,没有地基就会写错租户**。
- `reviews` 模型 **use `RequiresTenantContext`**(闸 2)。
- `DraftReviewReply` / `PublishReviewReply` 是 `final readonly` Action,`handle()` 只写逻辑不管上下文(上下文由调用方 `RunInTenant` 提供),与 REVIEW §4 Move 1 的 `UpdatePageBlock` 同构——**AI/MCP 调这些 Action 时也走同一 `RunInTenant`**,人类编辑与 AI 编辑共享同一条校验过的写路径。
- AI 只输出**结构化草稿文本**填进 `reply_content`,永不直接触达平台或渲染成 HTML(呼应 REVIEW 的 XSS 铁律:值进模板一律 `{{ }}`)。

## 5. Filament:先埋运营驾驶舱的种子

评论天生适合做**跨租户聚合队列**——这是业务蓝图"一个运营托管 30–50 客"的入口 UI,也是 REVIEW 之外我建议现在就动的第 3 点。

- **central 面板 `ReviewsQueue` 页**(super_admin):跨所有租户列出 `reply_status IN (draft, none)` 且 `rating <= 3` 的评论,按时间/评分排序,一屏处理所有客户的待回评。
  - **跨租户读**靠 central BYPASSRLS 连接(与现有"central sees all"一致);
  - **每条回复的写**点击时 `RunInTenant($review->tenant_id, fn => PublishReviewReply(...))` ——读跨租户、写回单租户,正是地基要支撑的模式。
- **tenant 面板 `ReviewResource`**(客户自己或代客):只看本租户,RLS 自动隔离,复用现有 tenant panel。

> 这一步先只做评论队列;结构定好后,社媒审批、线索收件箱按同样的"跨租户读 + 单租户写"模式往驾驶舱里加。

## 6. 合规(业务蓝图红线,必须人来兜底)

- **AI 回复永不自动发布**:`reply_status` 默认 `draft`;`rating <= 3` 的差评强制人审后才可 `approved`(可加 Filament 校验:低分回复必须有人工编辑痕迹)。
- **催评合规**:`review_requests` 走 SMS/email 时受 TCPA/CAN-SPAM 约束——必须有 opt-in 来源、退订通道、A2P 10DLC 注册(承接 tenant-write-context §5 的 ops 清单)。MVP 若不确定合规,催评先只生成短链、发送留人工。
- **平台 ToS**:GBP/Yelp 禁止诱导性/虚假评论;催评只能"请真实顾客留真实评价",不得筛选只请好评(Yelp 明令禁止 review gating)。这条写进给客户的服务说明。

## 7. 测试

- **RLS 隔离(读)**:reviews 出现在 RLS 策略集(`RlsPolicyTest` 加 `toContain('reviews')`);两租户各自只见自己的评论(镜像 `RlsIsolationTest` locations 那三段)。
- **带外写(核心)**:`SyncReviews` job 从 central dispatch,断言评论 upsert 落到正确租户、对其他租户不可见;不走 `RunInTenant` 直接 `Review::create` → 抛 `RequiresTenantContext` 异常。
- **去重幂等**:同 `(source_platform, external_review_id)` 拉两次只有一行;两个 `manual`(external_review_id null)可共存。
- **回复状态机**:`none→draft→approved→published`;差评未经人审不可 `published`(负路径断言,补 REVIEW M4 的空白)。
- **驾驶舱**:central `ReviewsQueue` 能跨租户看到待回评(livewire 测试,super_admin acting-as)。
- **shape 测试**:`ReviewTest` 镜像其他模型的 `to array` 约定(补 REVIEW M5 同款要求)。

## 8. 一句话

评论模块 = 你第一个真正抗 AI、能收月费的护城河服务,也是运营驾驶舱和带外写入地基的第一个真实消费者。建站照旧推进;这个模块并行落地后,社媒 / 接线 / campaign 都能复用同一套「TenantAware 拉取 → AI 草稿 → 人审 → RunInTenant 回写 → 跨租户驾驶舱」骨架。

## 9. 下次继续(handoff · 2026-07-18 收尾)

**今天做了什么**:讨论了 AI 时代广告公司的服务战略 → 落到本仓库,确认「建站是商品层、growth-OS(评论/社媒/接线)才是护城河」→ 产出两份实现级设计文档:本文件 + [tenant-write-context.md](./tenant-write-context.md)。**尚未写任何代码。**

**关键架构结论(别忘)**:运营驾驶舱要跨租户读 → central 角色必须 BYPASSRLS → **写安全无法靠角色权限,只能靠"唯一通道 + 运行时守卫 + arch 锁定"三道闸强制**(见 tenant-write-context.md)。

**明天第一步(二选一)**:
1. **先拍板 §3 的三个决定**(平台=Google+manual? / 回复语言=镜像? / 催评是否进 MVP?),然后我按需要调整本文档;或
2. **直接开工不依赖外部决定的部分** —— tenant-write-context.md §7 的落地顺序 1–5(钉死 tenancy commit → `RequiresTenantContext` → `RunInTenant` → `TenantAware` → 写路径测试)。这块完全自洽,可立即实现。

**建议路径**:先做 2(地基),同时你想 §3。地基是建站 mutation 层和评论模块共同的前置,先落地不浪费。

**REVIEW.md 快修(阶段 0,顺手)**:M1 slug 唯一 + M2/L1 索引 + 删空 Helpers + L5 时间戳 + M6 钉死 tenancy —— 现在改最便宜。
