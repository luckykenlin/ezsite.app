# 行业模板 + Demo 站 + Central Landing Page 路线图

> 制定于 2026-08-02。分四个阶段实施，尚未开始；每完成一个阶段请更新本文末尾的进度表。

## 背景与目标

产品走向自助获客：为 8 个行业（中餐馆、美甲店、按摩店、个人简历、设计师作品集、汉堡店、披萨店、奶茶店）各做一个 demo 站；central 域名首页做 landing page——访客了解产品、浏览模板、一键应用模板：填一个引导表单即生成自己的站点（内容与图片由表单引导 + 图库填充）。

**已拍板的产品决策**（勿在实现时重开）：

- 模板全部**手工策划**（PHP 定义，不是 AI 现生成）——闭合设计系统是护城河，模板是它的延伸。
- 引导表单**内建账号**：提交即创建 user + tenant + 站点，签名 URL 自动登录进编辑器。
- 文案**英文为主**，不做 i18n 切换。
- **即时开通 + 限流**：不做邮箱验证、不做人工审核（邮件基建后补）。
- 向导用**行业字段 + 可跳过**的三步形态。
- Landing **不放定价、不放客户评价**（两者都还不存在）。

## 调研结论（实现时直接引用，勿重新探索）

- central 域名现状：`routes/web.php` 只有一条 `Route::view('central.welcome')` 占位页；无公开注册、无邮件基建（mailer=log、零 Mailable）；central 的 robots/sitemap 不存在（现有的 `RobotsController`/`SitemapController` 都注册在 tenant 栈）。`resources/views/welcome.blade.php` 是死文件，可删。
- 可复用管线已齐：`SiteDraftValidator`（手写模板走同一校验；`image_query` 只在 `stock-photos.enabled=true` 时存活）、`StampPresetDefaults::fill()`、`ApplyStylePreset`、`SaveSiteChrome`（仅 header 为 null 时盖章导航）、`PopulateDraftImages`（**只走 draft 页，必须在 publish 前跑**）、`AdoptLibraryPhoto`（按 library_photo_id 幂等）、`library:import` 命令、`CreateTenant`（只建 tenant + 子域名，Business/页面/用户全空）、`SharePreviewAction` 的 `signed:relative` 跨域签名模式（自动登录照抄）。
- `GenerateSiteDraft` 的持久化半边（`upsertPage()`/`stampNavigation()`）焊死在 agent 调用里，Phase 1 先抽出来共用。
- 块的 `$sample` 属性 + `PageEditorPreviewController` 的 `?sample=` 预览是现成的「示例内容 → 真实 block」变换与单块缩略图机制。
- `CreatePageFromName` 永远给不出 `/` slug——首页必须像 `upsertPage()` 那样直接 `Page::create(['slug' => '/'])`。
- Browser 套件的 `*.localhost` 主机名技巧（`tests/Concerns/VisitsTenantPages.php`）可直接用于截图脚本。

## Phase 1 — 模板基建（~15 文件，2–3 天）

1. **抽取 `app/Actions/ApplySiteDraft.php`**：把 `GenerateSiteDraft` 的事务体 + `upsertPage()` + `stampNavigation()` 原样移入，签名 `handle(Business $business, array $draft, bool $overwritePublished = false): Page`（`$draft` 为 validator **输出**形状）。`GenerateSiteDraft` 保留 `requestDraft()` 与已发布首页拒绝守卫，委托持久化。**独立成 PR**，隔离 AI 草稿回归面。
2. **模板产物**：
   - `app/Templates/SiteTemplate.php`：string-backed enum，8 case（`chinese-restaurant`、`nail-salon`、`massage-spa`、`personal-resume`、`designer-portfolio`、`burger-joint`、`pizza-shop`、`bubble-tea`）；方法 `definition()` / `label()` / `demoSubdomain()`（`'demo-'.$value`）/ `description()`。
   - `app/Templates/TemplateDefinition.php`：readonly DTO——`preset` + token 覆盖、品牌三色 hex、business `category`、chrome（header/footer 的 block 形状）、`pages`（**SiteDraftValidator 输入形状**：flat variant/tone/spacing 兄弟键、`data` 内不带前缀的 `image_query`、首页 `/` 必含 hero 且 ≥3 blocks、附加页限 `/about|/services|/contact`）、`photoQueries`（query + PhotoOrientation + PhotoCategory + count）、`demoProfile`（demo 站的 Business 字段 + 一个 Location 供 bind 块用）、`extraFields`（`TemplateField` DTO 列表，驱动向导第 2 步）。
   - `app/Templates/Definitions/` 8 个类；先出 2 个试点验证形状：ChineseRestaurant（bind 重、本地商家型）+ DesignerPortfolio（gallery/媒体重）。
3. **`app/Actions/Templates/FillTemplatePlaceholders.php`**：递归替换 `data` 中所有字符串的 `{business_name}`/`{tagline}`/`{city}`/`{phone}`/`{email}` + extraFields 键；未填字段回落到 `demoProfile` 值（页面上永不出现字面 `{city}`）；在 `SiteDraftValidator` 之前跑。
4. **测试**：`SiteTemplateTest`（dataset 遍历 8 case：整体过 validator 且零丢块、hero/块数/variant/axis/hex/photoQueries/subdomain 唯一性，跑在 `stock-photos.enabled=true` 下）；`FillTemplatePlaceholdersTest`；`ApplySiteDraftTest`（迁移 GenerateSiteDraft 现有持久化断言 + overwritePublished 行为）。

## Phase 2 — Demo 租户（~8 文件 + 6 个模板定义，2–3 天，文案是工期大头）

1. 迁移：`tenants` 加 `template`（nullable string，demo 与用户套用都记，归因用）+ `is_demo`（bool，索引）。中心锚表无 RLS 牵连。`Tenant` 模型加 cast + `demo()` scope。
2. `config/templates.php`：保留子域名清单（www/admin/app/api/mail/demo/ezsite/central/status/docs…）+ 限流配置。
3. `app/Actions/Templates/ValidateSubdomain.php`：slug 化、`^[a-z0-9]([a-z0-9-]{1,61}[a-z0-9])?$`、拒保留词/`demo-` 前缀/已占用 Domain（含 FQDN 前缀），抛字段级 ValidationException。`CreateTenant` 加可选参数 `?string $subdomain`、`?SiteTemplate $template`、`bool $isDemo = false`。
4. `app/Actions/Templates/ProvisionDemoSite.php` + `php artisan demo:seed {template?*} {--skip-photos}`，幂等：查/建 demo tenant → 按 `photoQueries` 预热图库（`StockPhotoProvider::search` → `FindOrImportLibraryPhoto`，NullProvider 静默降级）→ `RunInTenant` 内 `Business::updateOrCreate`（demoProfile + 品牌色 + **preset+覆盖后的完整 design_tokens**——不能只调 ApplyStylePreset，覆盖会丢）→ Location → `SaveSiteChrome` → 占位填充 → validator → `ApplySiteDraft(overwritePublished: true)` → `PopulateDraftImages`（同步、**publish 之前**）→ 全部页 `PublishPage`。重跑 = 原 tenant 覆写不重复。demo 租户永不 attach 用户（`canAccessPanel` 天然只让超管碰）。
5. `DatabaseSeeder` 追加 `demo:seed` 调用（dev 便利）。
6. 补齐其余 6 个 `Definitions/`。美术方向（每个 preset 至少用一次，WarmCraft 用两次配不同 token 覆盖——「同一系统、两家不同餐厅」本身是卖点）：

| 行业 | Preset（+覆盖） | Hero 角度 |
|---|---|---|
| 中餐馆 | WarmCraft（ElegantSerif/WarmSand） | 全幅宴席照 inverted：三代传承的家乡味 |
| 披萨店 | WarmCraft + Sunset 盘 + FriendlyRounded | 柴火炉特写，社区披萨房的暖 |
| 汉堡店 | PlayfulFriendly | 大字压堆叠汉堡照，loud & casual |
| 奶茶店 | FreshModern | 粉彩产品照 + 几何排版，季节菜单向前 |
| 美甲店 | NightLounge（vibes 自带 salon） | 暗房金色调，hero 下即甲艺 gallery |
| 按摩/SPA | CalmCoastal | airy tall hero，单一安静 CTA |
| 个人简历 | ProfessionalMinimal | 纯排版 hero 无图：姓名/头衔/一句话 |
| 设计师作品集 | BoldEditorial | 杂志式 plum hero，首屏即作品网格 |

7. 测试：`ProvisionDemoSiteTest`（NullProvider + LibraryPhotoFactory；幂等性 = 二跑零重复）、`SeedDemoSitesTest`、`ValidateSubdomainTest`、Tenant 模型测试补 cast/scope。

## Phase 3 — Central landing + 模板画廊（2–3 天，依赖 Phase 2 的 demo 站）

1. **路由**：`routes/web.php` 现有 `Route::domain()` 循环内加 `/`、`/templates`、`/templates/{template}`（enum 绑定）、`/start/{template}`、`/robots.txt`、`/sitemap.xml`。新命名空间 `app/Http/Controllers/Central/`（现有 controllers 全是 tenant 栈专用，刻意分开）；全部 invokable readonly。**不放静态 `public/robots.txt`**（静态文件先于路由，会遮蔽 tenant 的 RobotsController）。删除死文件 `resources/views/welcome.blade.php`，替换 `central/welcome.blade.php`。
2. **CSS：复用 site.css（dogfooding 即卖点）**——central layout `@vite(['resources/css/site.css'])`，主题变量直接用 `ThemeVariables` + 固定 preset（如 FreshModern）构造（tenant-null 的 bail 在 render hook 里，不在类里）；site.css 加一行 `@source '../views/central/**/*.blade.php'` 并在 ConventionsTest 加守卫。字体已全量打包。
3. **`<x-central.layout>`** + `central/home.blade.php`，段落全用 `<x-site.section>`：Hero（"A beautiful website for your business in minutes"，CTA → /templates，三张 demo 截图扇形合成）→ How it works（pick a template → guided form → live at yourname.ezsite.app）→ 8 模板卡片条 → 设计系统段（同一 block 三种 preset 渲染，静态图）→ 收尾 CTA。不放定价、不放评价。英文文案。
4. **画廊缩略图：预渲染静态截图**（8 个 live iframe = 8 次全量加载，移动端不可接受；签名 preview URL 7 天过期不适合常驻）。`scripts/capture-template-screenshots.mjs`（Playwright 已随 pest-plugin-browser 装好；`*.localhost` 技巧照抄 `VisitsTenantPages`），1440/390 两档，截图提交入库。模板详情页：大截图 + preset 说明 + 行业特性列表 + **一个**懒加载 iframe（桌面端、首屏下）+ 两个 CTA（"View live demo" 新开 demo 子域名 / "Use this template" → `/start/{template}`）。共享卡片组件 `components/central/template-card.blade.php`。
5. **SEO**：复用 ralphjsmit/laravel-seo；central sitemap 列 `/` + `/templates` + 8 详情页；JSON-LD SoftwareApplication。
6. 测试：Home/Gallery/Detail 渲染 + SEO 标签；**碰撞守卫**（central `/` 出 landing、`acme.` 子域仍出 tenant 页）；CentralRobots/Sitemap 且断言 tenant robots 不受影响；ConventionsTest 加 Central 命名空间约定。

## Phase 4 — 一键应用流程（5–7 天，最大阶段）

1. **技术选型：全页 Livewire**（`/livewire/update` 在 central 域可用；plain Blade 做不了子域名实时查重；Filament Schema 出面板会拖进面板样式）。新家 `app/Livewire/Central/`（代码库首个 Filament 外 Livewire，加 arch 约定）。
2. **三步向导 `ApplyTemplate`**（状态放 Livewire/session，v1 不建 applications 表）：
   - Step 1 基本信息：商号、tagline（选填）、邮箱、电话（选填）+ 子域名选择器（`Str::slug` 预填、`wire:model.live.debounce.500ms` 实时可用性走 `ValidateSubdomain`、实时预览 `yourname.ezsite.app` ✓/✗ + 一个 `-2` 建议）。
   - Step 2 行业内容：字段来自 `TemplateDefinition::extraFields`（3–6 个：餐饮系填招牌菜名/价/一句话；美甲/SPA 填服务价目 + 预约电话；简历填姓名/头衔/3 段经历；作品集填工作室名/3 个项目）+ 醒目的 **"Skip — use example content, edit later"**（模板本身完整，跳过也出好站）。
   - Step 3 账号：邮箱 + 密码（本向导即注册；已有邮箱 → 提示登录，不允许 signup 抢号）。v1 不做 logo/照片上传——默认模板精选图库照，进编辑器后再换。
3. **提交 = 同步开通**（provisioning 只是 DB 写，秒级；配图异步）：`app/Actions/Templates/ProvisionSiteFromTemplate.php` `handle(SiteTemplate, SignupDetails): Tenant`——`ValidateSubdomain` → `CreateTenant(name, email, subdomain, template)` → `User::firstOrCreate` + attach → `RunInTenant`（Business 建档含品牌色与合并 tokens、chrome、占位填充 → validator → `ApplySiteDraft`，页面留 **Draft** 让用户在编辑器审后再发）→ 事务外 dispatch `PopulateDraftImagesJob`。`SignupDetails` 为 readonly DTO。
4. **落进编辑器（无邮件基建 → 签名自动登录）**：`app/Actions/Templates/CreateClaimUrl.php`（`URL::temporarySignedRoute('site.claim', 15min, absolute: false)` 再对 `Domain::getUrl()` 绝对化——照抄 SharePreviewAction 已验证的 `signed:relative` 跨域模式）+ `ClaimSiteController`（tenant 栈 `GET /_claim/{user}`：校验 `belongsToCurrentTenant`、`Auth::login`、session regenerate、跳首页的 PageEditor）。提交成功页两个按钮：View my site / Open the editor（claim URL）。
5. **护栏**：`RateLimiter::for('template-signup')`（IP + 邮箱，3/小时，配置化）+ 蜜罐字段；`ValidateSubdomain` 是唯一收口，`domains.domain` 唯一索引兜底竞态；无新增租户表 → 零 RLS 动作。
6. 测试：`ProvisionSiteFromTemplateTest`（8 模板 dataset：tenant/domain/归因/用户新建与已有路径/Business 字段/chrome/Draft 页占位已换/`Queue::fake` 断言配图 job/slug 竞态回滚）、`ClaimSiteControllerTest`（有效/过期/篡改/非成员/错租户）、`CreateClaimUrlTest`、限流注册测试、`ApplyTemplateTest`（Livewire：分步校验、实时查重、跳过路径、蜜罐、限流）、可选一条 Browser happy path。

## 明确不做（v1）

支付/套餐、邮箱验证与一切 Mailable、找回密码、注册时自定义域名（子域名 only）、注册表单上传 logo/照片、模板改版同步到已有站点、模板版本化、apply 时现跑 AI 文案（占位填充是确定性的；AI 编辑在编辑器里已有）、申请落库/漏斗表（v2）、demo 截图自动化 CI 化。

## 部署检查项

- `bootstrap/app.php` 上代理前必须补 `->trustProxies()`（当前零 middleware 注册，`X-Forwarded-*` 被忽略会搞坏 scheme/host 判断）。
- 生产 DNS 需通配子域名解析；`TenantCouldNotBeIdentified` 已兜底重定向到 central 首页。
- 上线后跑一次 `demo:seed`（Pexels key 已配置；也可先用 `library:import` 预热图库）。

## 依赖与排期

Phase 1 先行独立 PR → Phase 2 与 Phase 3 可并行（画廊需要 demo 站在线截图）→ Phase 4 需要 1+2。8 行业 × 3–4 页的文案是真正的排期驱动项，两个试点模板先行去险。合计约 2–3 周。

## 进度

| 阶段 | 状态 |
|---|---|
| Phase 1 模板基建 | 未开始 |
| Phase 2 Demo 租户 | 未开始 |
| Phase 3 Central landing | 未开始 |
| Phase 4 一键应用 | 未开始 |
