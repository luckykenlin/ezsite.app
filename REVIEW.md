# ezsite.app 架构评审报告

> 评审日期：2026-07-17 · 分支：`develop` · 评审基线 commit：`3da4652`
> 方法：7 维度并行深审（架构 / 租户安全 / 数据模型 / Filament / 代码质量 / 测试 / 配置工具链）→ 38 条发现逐条**对抗式验证**（读真实代码试图证伪）→ 前瞻性就绪度评估。**37 条证实、1 条被推翻**。所有发现均已核对到具体 `file:line`。
>
> 本文件为自包含存档，明天可独立阅读，无需回看对话。

---

## 0. 项目背景（对齐上下文）

- **产品定位**：多租户 AI 建站平台（Wix「内容层自由度拉满」+ Base44「能力扩展走受控管线」的混合，**不是** Lovable/Bolt 那种任意代码生成路线——见 `PLAN.md`）。
- **技术栈**：PHP 8.5 / Laravel 13 / Filament v5 / Livewire v4 / stancl/tenancy v4 / Pest v5 / Postgres。
- **租户隔离**：单库 + Postgres RLS（**非** 每租户独立库）。`DatabaseTenancyBootstrapper` 故意禁用，改用 `PostgresRLSBootstrapper`。RLS 策略由 `tenants:rls` 自动生成、迁移后自动重同步，**从不手写**。
- **规模**：`app/` ~1680 行、`tests/` ~1203 行、11 个迁移。代码库年轻但工程纪律好。
- **关键约定**（评审时视为 ground truth，不作为问题）：
  - 租户模型（如 `Post`）**无** `BelongsToTenant` trait、**无** 全局作用域——隔离完全靠 RLS。
  - 模型全局 `unguard`（nunomaduro/essentials）——**禁止**加 `$fillable` / `#[Fillable]`。
  - 排除某列 RLS 用 `->comment('no-rls')`（如 `domains.tenant_id`、`tenant_user.tenant_id`）。
  - `routes/web.php` 中控域专用；`routes/tenant.php` 经 `TenancyServiceProvider::mapRoutes()` 挂载。
  - `User::canAccessPanel()`：central panel 要 `is_super_admin`；tenant panel 要 `is_super_admin` 或 `tenant_user` 成员。普通 `User::factory()` 零面板权限。

### ⚠️ 两点前提纠正
1. `composer.json` 的 `laravel/pao` **不是** `laravel/pail` 的拼写错误——它是 Taylor 官方的 "agent-optimized output for PHP testing tools"（`--format agent` 靠它），`laravel/pail` 也另外单独存在。
2. 初审怀疑的「businesses 无 RLS = 跨租户越权大洞」**验证后被推翻/降级**：这是 `RlsPolicyTest`/`RlsIsolationTest` 里**故意编码并测试锁定**的设计（businesses 作事实源、手动按 tenant_id 隔离），不是 bug。真正的问题只是**没在 `tenancy.md` 里像 domains/tenant_user 那样写明理由**（见 L4）。

---

## 1. 总体结论

**地基扎实，上层未起。** 当前**没有一条 critical 或 high 级真实缺陷**。工程纪律优秀：`declare(strict_types=1)` 全覆盖、模型/Action 一律 `final`（Action 还 `final readonly`）、返回类型与 array-shape PHPDoc 一致、Pint(strict)+Rector+Larastan(max)+100% 行覆盖 & 类型覆盖门禁齐备。

要对齐 PLAN.md 愿景，分两层看：

| 层 | 就绪度 |
|---|---|
| **租户隔离 + 数据模型底座** | ✅ 真正就绪 —— RLS-only 自洽、有测试锁定、Page/Fabricator SSR 渲染链路已通 |
| **Token+Variant 构建器 + AI 编辑层** | ❌ 基本 0% —— `grep design_token\|variant\|revision\|preset` 全仓零命中；`laravel/ai` 装了但 `app/` 无任何引用；`PageBlocks/` 只有一个 `Heading` stub |

**就绪度评级：`gaps`（有缺口，可开工但需先打地基）。**

---

## 2. 分级发现清单（37 条已验证）

严重度经验证后校准。标注：🟡 medium · 🟢 low · [验证结论]。

### 2.1 数据完整性 —— 优先修（现在改最便宜）

**🟡 M1 · pages 根页 slug 唯一性形同虚设** [CONFIRMED]
`database/migrations/2026_07_13_033710_fix_slug_unique_constraint_on_pages_table.php:15`
复合唯一 `(tenant_id, slug, parent_id)`，但 `parent_id` 对顶层页恒为 NULL，Postgres 默认 `NULLS DISTINCT` → 同租户两个同 slug 根页**不冲突**，唯一性在最常见场景失效。Fabricator 按 slug 路由 → 重复根 slug 得到未定义路由目标。
- 缓解：UI 主路径有 Fabricator 表单校验（`->unique(ignoreRecord, modifyRuleUsing where parent_id)`，RLS 下租户内校验）兜底；DB 缺口只在绕过表单的路径（Action/AI/factory/seeder）暴露。
- 修复：`->nullsNotDistinct()`（Laravel 13 IndexDefinition，PG-only），或改为 `(tenant_id, slug) WHERE parent_id IS NULL` 部分唯一索引 + 保留子页复合唯一。加测试：同租户两个同 slug 根页应抛 QueryException。

**🟡 M2 · RLS 扫描表缺 tenant_id 索引** [PARTIALLY_TRUE，性能非正确性，降至 medium]
`database/migrations/2026_07_12_042228_create_posts_table.php:15`、`2026_07_16_041044_create_locations_table.php:18`
RLS 给每条查询注入 `WHERE tenant_id = current_setting(...)`。`posts.tenant_id` / `locations.tenant_id` 无索引（posts 仅 posts_pkey）→ 每次读写全表顺序扫描。单库多租户下这是核心热路径。（`pages`/`businesses` 因 tenant_id 是复合唯一索引最左列已覆盖。）
- 修复：`posts` 加 `$table->index('tenant_id')`；`locations` 加 `$table->index(['tenant_id','business_id'])`（一并覆盖 L1）。建议加 arch 测试：非 no-rls 的 tenant_id 列必须有索引。

**🟢 L1 · locations.business_id FK 无索引** [CONFIRMED]
`2026_07_16_041044_create_locations_table.php:19` — `foreignId()->constrained()` 只建 FK 约束，PG 不自动索引引用列。拖慢 `Business::locations()` 与 `Business::booted()` 软删级联。表基数低，实际影响小。→ 加 `index('business_id')` 或复合 `(tenant_id, business_id)`。

**🟢 L2 · businesses 复合 unique(tenant_id, slug) 冗余且掩盖 1:1 约束** [CONFIRMED]
`2026_07_16_041044_create_businesses_table.php:19,45` — 第 19 行 `tenant_id` 已单列 UNIQUE（强制每租户 1 business），第 45 行 `unique([tenant_id,slug])` 完全冗余。而模型 `#[Sluggable(scope:'tenant_id')]` 又暗示 1:多，`Tenant` 无 business 关系记录意图 → **基数意图不明确**。→ 拍板：1:1 则删冗余索引 + 改 `hasOne`；1:多 则删 `tenant_id` 单列 unique 保留复合。

**🟢 L3 · Business restore 过度恢复独立软删的 locations** [CONFIRMED]
`app/Models/Business.php:75` — `restoring` 钩子 `locations()->onlyTrashed()->restore()` 恢复**所有**已软删 location。业务删除前就独立软删的 location 会被错误复活（未按 `deleted_at` 匹配区分级联删与独立删）。→ 按父 `deleted_at` 匹配 restore；加测试覆盖「独立删→级联删→恢复」序列。

**🟢 L5 · businesses/locations 迁移同时间戳** [CONFIRMED]
两迁移都是 `2026_07_16_041044`。locations 有 FK 到 businesses，靠字母序 `create_businesses_table < create_locations_table` 偶然保证 FK 顺序。未来重命名或插入第三个同戳迁移可能破坏 `migrate:fresh`。→ 给 locations 一个严格更晚的时间戳。

**🟢 L6 · 无「每 business 唯一主 location」约束** [CONFIRMED]
`2026_07_16_041044_create_locations_table.php:23` — `is_primary` 纯 boolean 默认 false，无约束、无 app 层强制。可能出现零个或多个主 location。→ 若是真不变式：`unique(business_id) WHERE is_primary = true` 部分唯一索引；否则文档标注 primary 为 advisory。

### 2.2 架构与重构 —— 在 AI 层落地前做

**🟡 M3 · 写入逻辑散落在 Filament 页面、未走 Action 层** [PARTIALLY_TRUE，前瞻性一致性风险]
`app/Filament/Tenant/Resources/Posts/Pages/ListPosts.php:19`、`.../PageResource/Pages/CreatePage.php:13`
PLAN §1/§68 把 Action 定为 AI/MCP 工具调用层，但 `app/Actions/` 只有 `CreateTenant`。`tenant_id` 盖章用了两种姿势：`ListPosts` 走 `CreateAction::mutateFormDataUsing`、`CreatePage` 走 `mutateFormDataBeforeCreate`——都不经 Action。`ListTenants` 正确委托 `CreateTenant`，证明模式却没推广。当前无实际重复（AI 写层还不存在），是**风险**：人类编辑与 AI 编辑若不共享同一校验过的 Action，会把校验写两遍、AI 那条没有。
> 注：两种姿势部分是框架强制的——`PostResource` 是普通 resource 用 modal `CreateAction`；`PageResource` 必须继承第三方 Fabricator 的 `CreatePage`（其钩子是 `mutateFormDataBeforeCreate`）。
- 修复：把内容/token 写入收敛成 Action（见第 4 节 Move 1）。`tenant_id` 盖章下沉成模型 `creating` 钩子（写时默认，**非**读作用域，不违反 RLS-only，也不需 `$fillable`）。

**🟢 L7（= M3 的一部分）· tenant_id 盖章两处复制、无共享抽象** [PARTIALLY_TRUE]
`cross-cutting` — `$data['tenant_id'] = tenant('id')` 在 ListPosts / CreatePage 逐字复制。Business/Location 尚无 create path，新 resource 会再抄一遍。
> 验证发现：自动生成的 RLS 策略是 permissive FOR-ALL、无显式 WITH CHECK，PG 对 INSERT 回退用 USING 表达式 → 在 RLS role + 已初始化 tenancy 下，NULL/错误 tenant_id 的 insert **会被拒**（fail-loud）。残余风险仅限带外写入者（seeder/job/AI Action 用 BYPASSRLS role）。→ 共享 `creating` 观察者/trait。

**🟢 L8 · Token+Variant / chat-editor 抽象全部未搭** [PARTIALLY_TRUE，"未开始"预期内]
`app/Filament/Fabricator/PageBlocks/Heading.php` — 唯一一个 PageBlock（单 `content` TextInput，无 variant）。无 design_token 模型/表、无 page_revisions、无 block/variant 注册表、无 `UpdatePageBlock`/`UpdateDesignToken` Action、无修改指令 schema/校验器。`Layouts/Main.php` 为空。见第 4 节。

**🟢 L4 · 隔离模型不一致：businesses 无 RLS、子 locations 有 RLS（意图未文档化）** [PARTIALLY_TRUE，故意但未记录]
`2026_07_16_041044_create_businesses_table.php:19` — `tenant_id ->comment('no-rls')->unique()`。**是故意设计**（`RlsPolicyTest.php:29` 断言 businesses 不在策略集、`RlsIsolationTest.php:108` 专门测试「businesses 不 RLS 隔离、手动按 tenant_id」），但 `no-rls` 本用于「tenancy 解析前需查询」的列（domains/tenant_user），businesses 无此需求。"手动 scope" 目前是 aspirational——`app/` 里无任何 BelongsToTenant/全局作用域强制它，仅靠 `unique(tenant_id)` + `Location::business()` 关系。当前无跨租户读路径（无 BusinessResource/Action）。→ **在 `tenancy.md` 写明理由**，或若无 pre-tenancy 查询需求则移除 `no-rls` 让它像 locations 一样受保护。

**🟢 L9 · generateUniqueSubdomain check-then-insert 竞态** [CONFIRMED]
`app/Actions/CreateTenant.php:36` — 循环 `Domain::where('domain',...)->exists()` 后再 create。两个同名并发调用可都通过 exists() 检查，败者 insert 撞 `domains.domain` 唯一索引抛未处理 QueryException（而非降级到下一后缀）。当前规模低危。→ 捕获 unique 冲突重试，让唯一索引成为真源。

**🟢 L10 · OpeningHours cast 拒绝数组、阻断 Filament/AI 编辑** [PARTIALLY_TRUE，有意边界、当前无消费者]
`app/Casts/OpeningHours.php:50` — `set()` 非 `OpeningHoursValue` 实例即抛异常。Filament 表单态与 AI 输出都是数组 → 建 Location 编辑器时必炸。是文档化的有意验证边界（docblock L21-23），当前 `Location.php:69` 唯一消费者、无 Location resource。→ 建编辑器时加 `dehydrateStateUsing`/表单 mutator，或让 `set()` 接受结构化数组入口。

**🟢 L11 · Helpers.php 被强制自动加载但为空** [CONFIRMED]
`app/Helpers.php` 仅含注释、无函数，却在 `composer.json:59-60 autoload.files`，每次请求 require。→ 删文件 + 删 autoload 条目（项目偏好 Action 而非全局函数）。

### 2.3 Filament 层

**🟢 L12 · 两个 PanelProvider 重复大段配置** [CONFIRMED]
`CentralPanelProvider.php:49` / `TenantPanelProvider.php:62` — 9 项中间件栈 + `authMiddleware([Authenticate])` + widgets + pages + `->login()->profile()->spa()` 逐字重复。差异仅 tenancy 中间件（2 项）、颜色、discover 路径、少数附加项。→ 抽 `BasePanelProvider`。

**🟢 L13 · 两 panel 都绑 /admin、tenant panel 无域约束** [CONFIRMED]
`TenantPanelProvider.php:35` 无 `->domains()`；`CentralPanelProvider.php:34` 域约束到 central_domains。中控域上两路由都能匹配，正确性靠 `bootstrap/providers.php` 中 central 先注册（first-match-wins）。重排 provider 或给 tenant panel 加 central 域会静默把中控 /admin 路由到 tenant panel。→ 显式化：给 tenant panel 独立路径，或至少加注释锁定注册顺序意图。

**🟢 L14 · created_at/updated_at 列在 3 张表逐字复制** [CONFIRMED]
`UsersTable.php:28-35` / `TenantsTable.php:35-42` / `PostsTable.php:22-29` 字节一致（Filament 脚手架产物）。→ 抽 `TimestampColumns::make(): array` 静态助手。

**🟢 L15 · 'Email address' label 硬编码 5 处** [CONFIRMED]
`UserForm.php:20`、`TenantsTable.php:27`、`ManageUsers.php:34`、`TenantForm.php:20`、`UsersTable.php:26`。`FilamentServiceProvider` 已全局 `translateLabel()`，这些覆盖反而绕开单一来源。→ 删覆盖 + 加一条翻译条目。

**🟢 L16 · PageResource 用旧版目录布局** [CONFIRMED]
`app/Filament/Tenant/Resources/PageResource.php:10` 扁平布局（vs Posts/Tenants/Users 的 v5 兄弟布局）。由继承 Fabricator 基类强制。→ 加类注释说明是有意偏离。

**🟢 L17 · 自定义批量删除静默跳过自己、无 self-skip 提示** [PARTIALLY_TRUE]
`UsersTable.php:47` — 覆盖的 `DeleteBulkAction` 排除当前用户删其余。验证发现「无成功通知」premise 错误（"Deleted" toast 仍会发）；真实残余：删自己被静默跳过、仍显示通用 "Deleted"，无 self-skip 警告。纯 UX 小瑕疵。→ 当选中含本人时补 warning 通知。

### 2.4 测试 —— 门禁强，盲区在负路径

100% 行/类型覆盖门禁保证每行被执行（无完全未覆盖文件），但：

**🟡 M4 · 全套零验证/负路径断言** [CONFIRMED]
`cross-cutting` — `grep assertHasFormErrors|assertHasErrors tests/` **0 命中**。所有 Filament 测试只断言 happy path `assertHasNoFormErrors`（10 处）。`UserForm` name/email/password、`TenantForm` name/email、`PostForm` title 的 `required`/`email` 规则被删也会绿灯（声明式规则不增未覆盖行）。CLAUDE.md 自带的验证测试片段从没被用。→ 按文档模式补：`fillForm(null/invalid) -> call('create') -> assertHasFormErrors([...])`，至少覆盖 required + email 格式 + create 时 password required。

**🟡 M5 · Post/Page 缺 unit/shape 测试** [CONFIRMED]
`tests/Unit/Models/` — 另 5 模型（Business/Domain/Location/Tenant/User）都有 `to array` shape 测试锁列暴露；`Post`/`Page` 无 unit 测试文件。`Page` 风险最高（继承第三方 `Z3d0X\FilamentFabricator\Models\Page`，仓库记录过 toArray 关系泄漏 gotcha）。→ 加 `PostTest`/`PageTest` 镜像 shape 约定（按 gotcha 用 `Model::query()->findOrFail()` 重取）+ tenant() 关系断言。

**🟢 L18 · RLS 隔离从不测跨租户 UPDATE/DELETE** [PARTIALLY_TRUE，传递性已覆盖]
`RlsIsolationTest.php:14` — 只测 SELECT 可见性隔离 + 一次 session 变量重置后 INSERT 失败。验证：RLS 策略是单一 `USING` 子句、无独立 UPDATE/DELETE 子句，UPDATE/DELETE 只能影响 SELECT 可见的行 → 0-rows-affected 由现有 SELECT 隔离测试 + 单策略设计**传递性保证**。属防御性补充，非高危缺口。→ 可选补显式 UPDATE/DELETE 负例。

**🟢 L19 · CreateTenant 无直接单测、空 slug 分支未覆盖** [PARTIALLY_TRUE]
`app/Actions/CreateTenant.php:32` — 仅经 ListTenants 间接测。所有测试用 'Acme Inc'（slug 真值），`Str::slug($name) ?: Str::random(8)` 的 fallback 从未执行（同行两分支，过行覆盖门禁）。碰撞 '-2' 后缀路径已在 `TenantResourceTest.php:57` 测过。→ 加直接单测覆盖空 slug → 随机子域分支。

**🟢 L20 · pages (tenant_id,slug,parent_id) 唯一约束负例未测** [PARTIALLY_TRUE，正例已覆盖]
`2026_07_13_033710:15` — 正向意图 IS 已覆盖（`RlsIsolationTest.php:41,46` 两租户各建 slug '/'）。仅缺负例：同租户内重复 (tenant_id,slug,parent_id) 应抛 QueryException 无测试。→ 补窄负例。

**🟢 L21 · arch 测试套件偏薄** [CONFIRMED]
`tests/Arch/ConventionsTest.php:1` — 仅 4 规则（strict types / 无 debug 助手 / models final / actions final+handle）。缺：Action `toBeReadonly()`、`App\Casts`/`App\Providers` finality、models extend Model、env() 禁令。→ 扩展 arch 规则 + 可考虑 `arch()->preset()->laravel()`。

**🟢 L22 · 未用工厂状态 + 少数未取分支** [CONFIRMED]
`LocationFactory.php:60` — `alwaysOpen()` 定义但零调用点（死助手）。另：`OpeningHours::get()` 的 null-timezone 分支（`:38`，工厂总设 timezone）、`Domain::getUrl()` 的 `?? 'http'` 回退（`:27`，app.url 总有 scheme）过行覆盖但从未取到。→ 删 `alwaysOpen()` 或用于 isOpenAt 断言；加无 timezone 的 opening_hours cast 测试。

### 2.5 依赖与配置

**🟡 M6 · 隔离命脉依赖跟 unpinned dev-master** [CONFIRMED]
`composer.json:27` — `stancl/tenancy: dev-master` + `minimum-stability: dev`（`:142`）。全应用最安全攸关的依赖（RLS 靠它），`dev-master` 无 semver 上限——任何 `composer update` 拉上游任意提交进隔离层。`composer.lock` 保证当下可复现（现钉在 `76e5f96...`），下次更新无保护。`prefer-stable: true` 仅缓解不禁止。RLS 隔离测试可作硬回归网。→ 钉 reviewed commit（`dev-master#<sha>`），每次升级当作需重跑 RLS 隔离测试的审慎变更；v4 出正式 tag 就切 `^4.0`。

**🟢 L23 · 核心 AI 依赖 laravel/ai pre-1.0** [CONFIRMED]
`composer.json:21` — `^0.9.0`（0.x 无 BC 承诺）。caret 正确锁 `>=0.9.0 <0.10.0`，但 0.x→0.x 补丁也可能破坏 API，波及 Action 工具调用层。`prefer-stable` 使 minimum-stability:dev 不强制拉 dev 构建。→ 保持显式约束，升级走手动审 + 全测，1.0 出后再放。

**🟢 L24 · pgsql sslmode 默认 'prefer'（静默明文回退）** [CONFIRMED]
`config/database.php:101` — `env('DB_SSLMODE', 'prefer')`。'prefer' 协商 TLS 但服务端不提供时**静默回退明文**，单库多租户下 tenant 数据可能明文传输且无报错。`.env.example` 未设 DB_SSLMODE。是框架默认 + 可 env 覆盖，属加固/文档缺口。→ 生产 `DB_SSLMODE=require`（或 verify-full+CA），并在 `.env.example` 列出让运维显式选择。

**🟢 L25 · PHPStan 分析 public/ 却跳过 tests/** [CONFIRMED]
`phpstan.neon:9` — paths 含 app/bootstrap/config/database/public/routes，**无 tests/**，尽管 Larastan level max、peststan 扩展已载（`:5`）、Rector 已处理 tests（`rector.php:54`）。工厂/InteractsWithTenancy trait/Pest specs 零静态分析。→ 给 phpstan.neon 加 tests 路径；可去掉 public/。

**🟢 L26 · tests.yml composer 缓存 key 无 restore-keys 回退** [CONFIRMED]
`.github/workflows/tests.yml:57` — `key: ${{runner.os}}-${{hashFiles('**/composer.lock')}}` 无 restore-keys、无 `-composer-` 前缀。每次 lock 变更 100% 缓存 miss。`lint.yml:39-41` 已正确做（含前缀 + restore-keys）。→ 镜像 lint.yml。

### 被推翻的发现（1 条，记录以免重提）
- **「不连贯的父子隔离：locations 有保护、父 business 无」** — [FALSE] 被显式、有意、测试锁定的设计推翻（`RlsPolicyTest.php:29` 断言排除 businesses、`RlsIsolationTest.php:108` 专测此行为）。软删级联担忧也被 locations 自身 RLS 反驳（`Business.php:72` 的 `locations()->delete()` 走 RLS 保护表，USING 策略把 DELETE 过滤到当前租户会话，即使加载了跨租户 Business 也删不掉别人的 location）。→ 保留为 L4 的「未文档化」低危观察。

---

## 3. 已核实的良好实践（不要过度打磨）

- 全库 `declare(strict_types=1)`、`final`/`final readonly`、返回类型、array-shape PHPDoc 一致。
- `FilamentServiceProvider` 已用 `configureUsing()` 集中组件样式默认（含全局 `translateLabel()`）。
- Filament v5 命名空间全部正确（Actions 全从 `Filament\Actions\`、layout 从 `Schemas\Components`、列从 `Tables\Columns`），静态 `make()` 一致，resource/schema/table 拆分一致，password 字段正确门控（filled 时 dehydrated、仅 create required、model cast `password=>hashed`）。
- `OpeningHours` cast 用 schema.org OpeningHoursSpecification 结构化数据存储、按 location 时区解析，设计考究且注释清晰。
- 租户 CI 正确接线：`tests.yml` 已建基础库 `ezsite_testing`，`tenancy_rls` role 由 `tenants:rls` 每次 migrate:fresh 自动创建。
- 工具链齐备：Pint(strict) + Rector(Laravel13+Pest) + Larastan(max) + roave/security-advisories + 100% 行覆盖(`--exactly=100.0`) + 100% 类型覆盖(`--min=100`)。

---

## 4. 面向 AI 建站阶段的 5 个架构动作（最高杠杆，开工前定死）

前瞻结论：**你可以开工，但接下来引入的抽象会定义整个构建器的形态。** 围绕一个 `heading` stub 和越堆越多的「表单里写逻辑」回改，代价远高于现在定好（现在无数据）。

1. **让 Action 化的 mutation 层成为内容/token 的唯一写入路径**，Filament 自己的保存也回流经过它。引入 `UpdatePageBlock` / `UpdateDesignToken`（后续 `ReorderBlocks`）为校验过、事务性的 `handle()` Action，接收类型化 payload、对 block/token schema 校验后再动 `pages.blocks`。把 `CreatePage` 的 tenant_id 盖章与未来 block 写入迁到这些 Action 后。
   - *为什么*：PLAN §1 定 Action 为 AI/MCP 工具调用层，但今天唯一写面是 Filament 表单生命周期钩子，MCP/队列 AI 调用永不进入。人类编辑与 AI 编辑若不共享同一 Action，校验写两遍且 AI 那条没有。做完后聊天编辑器只是「又一个调用方」。直接消解 M3/L7。

2. **加 `page_revisions` 快照表，在任何 AI 代码能写之前**。存 `{page_id, tenant_id(RLS), blocks JSON, tokens, author, created_at}`，一键回滚且**回滚也走 mutation Action 重新校验**。
   - *为什么*：PLAN §补充4 已选定「数据快照 > git」。这是防坏 AI 编辑/畸形 block 污染活站的最便宜保险，现在无数据时加最省。回滚重校验也防复活 schema 已变的旧 block。

3. **用第一个真实 block（不是 heading stub）定义 block-schema 契约 + 类型注册表**。每 block = `{type: string(注册表支撑，永不硬枚举——PLAN §4), variant: 按类型枚举, data:{}, bind?:{location|business}}`。注册表映射 `type → 允许 variant → blade view → Filament 字段 schema → 给 AI 的机器可读 JSON schema`。渲染器对未知 type/variant **防御式跳过+记日志，绝不 fatal**。
   - *为什么*：heading 设了「无 variant 无 bind」的先例——照它建 5 个 block 就重蹈 PLAN 开篇拒绝的「每模板重造一遍」之痛。注册表也是 MVP 里 AI「词汇量」的枚举处（修改指令 schema 用），并是「共享代码红线」（PLAN §71：禁租户专属可执行代码）的强制点——block/variant 是 app 级类，租户只能选不能造。

4. **现在就定 design_tokens 存储与派生**（PLAN §77：token/block 数据结构钩子须在编辑器前定）。每租户（或每页草稿）一套 token：色 slot（primary/secondary/accent/neutral/base）+ 字体配对 id + 圆角档 + spacing 密度，由 `businesses.brand_*` 派生，渲染成 DaisyUI CSS 变量（`--p`/`--s`/`--a`/radius）。**block variant 引用 slot，绝不写死 hex。**
   - *为什么*：token 是聊天编辑器另一半要改的东西，variant 必须引用 slot 才可换。brand_* 留作 Business 松散列而无 token 层 → 每 block 硬编码颜色，「改一个 token 全站重塑」的承诺就死了。token 值成 CSS 前按枚举/正则校验也是 SSR 路径的主要 XSS 防线。

5. **加 `pages.status(draft/published)` + 草稿态预览渲染**。渲染链路（PageController）已通，按 status 门控 + 加租户预览路由渲染当前草稿（可选指定 revision）。
   - *为什么*：PLAN §补充3 指出服务端草稿渲染是 PHP/Blade 相对 Node/WebContainers 的结构性优势——这里几乎免费。没草稿态则每次 AI 编辑直接上线，与回滚故事冲突。

### AI 阶段的结构性风险（务必先设护栏）

- **🔴 最大风险：带外写入丢失 RLS 上下文。** AI 经 MCP/队列的写入跑在租户 HTTP 请求之外，`PostgresRLSBootstrapper` 不设 `my.current_tenant`。用 RLS role 会写失败；用测试套件那种 superuser/BYPASSRLS role 则 `UpdatePageBlock` **可能静默写错租户**。每个 mutation Action 必须显式 `tenancy()->initialize()` 并在**写路径**做隔离测试（当前 RLS 测试只覆盖读）。
- **Blade SSR = 无沙箱服务端执行**（PLAN §63-66 已承认）。任何 AI 影响的值进入 Blade 的 HTML/属性/CSS 变量（尤其 token→内联样式派生、未来富文本 block）都是共享服务器上的存储型 XSS。铁律：AI 只输出 JSON；block view 一律 `{{ }}` 禁 `{!! !!}`（设成 arch/lint 规则）；token 值成 CSS 前按枚举/正则校验。
- **blocks JSON 与渲染器间无校验闸门**：未知 type/畸形 data 会在活站渲染时 fatal（500）→ mutation Action 需 JSON-schema 闸门 + 渲染器优雅降级。
- **单页混合隔离**：`businesses`(no-rls) 与 `locations`(RLS) 混在一页渲染；公共页路由已初始化 tenancy 没问题，但中控/AI 编辑与预览路径解析 bind 时可能无租户上下文——每个解析 bind 的路径都必须先 initialize tenancy。
- **共享代码红线无编码守卫**：PLAN §71 禁租户专属可执行代码，能力扩展阶段必须只产出全局共享 block 类。目前架构无东西强制「租户只能选/配注册表 block、不能写代码」。注册表须成为硬边界，否则「给某客户 just add a block」的压力会侵蚀单库 RLS 安全模型。
- **隔离命脉依赖风险延续到 AI 阶段**：M6（stancl/tenancy dev-master）——整个 AI mutation 的写安全故事都压在移动靶上。**建 mutation 层之前先钉死它。**

---

## 5. 建议执行顺序

1. **立即（trivial，现在做最便宜）**：M1（slug 唯一索引 nullsNotDistinct）+ M2/L1（posts/locations 索引迁移）+ 删空 `Helpers.php`（L11）+ L5（迁移时间戳）+ M6（钉死 tenancy commit）。
2. **接 AI 前的地基**：Move 1（Action mutation 层）+ Move 3（block 注册表）——这两者决定整个构建器成败。同时消解 M3/L7。
3. **补测试短板**：M4（负路径验证测试）+ M5（Post/Page shape 测试）。
4. **随手清理**：L12-L16 的 Filament DRY、L9 竞态、L2/L3/L6 数据模型意图拍板、L24 sslmode、L25 phpstan tests 路径。

**一句话**：底座值得信任，别过度打磨已经很干净的 CRUD 层。精力压到两处——先补 M1/M2 数据完整性缺口（现在做），然后在写第 6 个 block、接 AI 之前先把 Move 1 + Move 3 定死。
