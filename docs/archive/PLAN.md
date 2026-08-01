# 网站构建器（AI Website Builder）— 第一阶段计划

> 状态：讨论已定方向，**尚未开始实现**。这是设计讨论的存档，下次继续时先过一遍这份文档对齐上下文。

## 背景

旧项目 `~/Projects/ezsite`（sqlite + 每租户独立数据库 + `nwidart/laravel-modules`，模板 T1-T6 各自是一个独立 Laravel Module）复杂度太高：每加一个模板都要重复造一遍 PageBlock 类、Blade 视图、migration、config、provider。

当前项目（`ezsite.app`）已经换成 Postgres RLS（单数据库），也已经开始移植 FilamentFabricator 的页面构建层（`app/Models/Page.php`、`app/Filament/Fabricator/{Layouts,PageBlocks}`、pages 迁移已加 `tenant_id`）。这次讨论的是：**在此基础上，如何重新设计"模板"这一层，避免重蹈旧项目 Modules 的复杂度，同时利用大模型能力做网站生成/编辑**。

## 最终愿景（不是第一阶段范围，仅供对齐方向）

- Central domain 是一个 AI 网站构建产品（类似 Lovable），有模板 marketplace：官方为各行业精心设计的模板（含专属组件 + design token），用户一键套用即可自动 self-onboard 出一个一样的网站，然后自行修改。
- Tenant 面板里，用户可以通过聊天框调用大模型动态修改网站。
- 模型越强，构建效果越好——但前提是我们给模型的"词汇量"（可组合的 variant / token / 组件）足够丰富，模型只负责在这个受限空间里做聪明的组合与内容生成，不负责发明新的 CSS/布局。

## 核心架构决策：Token + Variant 两层模型

关键认知（讨论中反复验证过）：**光靠 design token（颜色/字体/圆角/spacing）做不出真正的设计差异感**，那只能解释视觉差异的 20%-30%。真正让旧项目 T1（紫色 SaaS）、T2（金色餐馆）、T3（大地色理发/spa）、T4（暗金美甲）看起来是完全不同网站的，是：

1. **版式构图**（Hero 是左文右图，还是满版图叠字，还是居中极简）
2. **字体系统**（字重/字间距/大小写/行高的组合，不只是选字体族）
3. **图片处理方式**（满版出血图 vs 圆角卡片图 vs 圆形裁切）
4. **形状语言**（分割线直线/波浪/斜切，按钮药丸形/直角/纯文字链接）
5. **动效性格**（克制 vs 弹跳）

这些都不是 token，是**版式变体（Layout Variant）**。所以"模板"的本质应该是两层的组合：

- **Layer 1 — Design Token**：颜色 / 字体 / 圆角 / spacing。负责"色调"和"密度"。
- **Layer 2 — Layout Variant**：每个通用组件（Hero、Features、Gallery……）预先设计 3-4 个真正不同风格的版式变体（居中极简、左文右图、满版图叠字、网格拼贴……），由我们/设计师手工做好，AI 和用户只能从中选择，不能自由生成新的。

"模板"= 一份 token 预设 + 每个组件选哪个 variant + 一两个签名处理手法的组合。

### 重要工程提醒：不要做完全自由的笛卡尔组合

Variant × Token 如果让模型自由拼装，容易出现"极简版式配高饱和度撞色 token"这种违和搭配。第一阶段应该把 variant + token 打包成几组**"风格预设"（Style Preset）**——每个预设标注好适配的行业气质（比如"温暖手工感" = 某几个 Hero/Gallery variant + 米色系 token），AI 在预设包之间选择/小幅微调，而不是从零自由拼装。后续有把握了再放开自由度。

## 第一阶段（MVP）范围

已经明确决定的取舍：

| 项目 | 决定 |
|---|---|
| 行业差异化组件（餐馆菜单/美甲services/装修projects） | **先不做**。这些是旧项目 T6（"汉堡连锁"模板）真正独特感的来源（`MenuTabsBlock`、`LocationsBlock`、`DealsBlock`），但属于"功能级"差异化，延后到后续阶段 |
| Design Token 自由度 | **有限预设**，不做自由取色/连续调节 |
| Marketplace 模板来源 | **先不管**，这阶段先把单个网站的构建+编辑体验做扎实 |
| 聊天框编辑范围 | **仅改内容和 token**，不做增删 block/页面（AI 不能重新排布结构，只能改文案/图片/token 值） |

MVP 收敛成三块东西：

1. **通用 PageBlock 库 + variant 系统** — Hero / Features / Gallery / Testimonials / Contact / CTA / Footer 这套核心组件，每个至少 3-4 个版式变体，覆盖几种不同"气质"。
2. **有限枚举的 Design Token 系统** — 颜色（精选色板/语义 slot：primary/secondary/accent/neutral/base）、字体搭配（几组 heading+body 配对，非自由选择）、圆角等级（如 5 档）、spacing 密度（如 3 档）。基于 DaisyUI 现有的 CSS 变量体系（`--p`/`--s`/`--a`、v5 的 radius 变量），不重新发明。
3. **聊天驱动的编辑器** — 用结构化输出（而非直接生成 HTML/代码）：把当前页面的 blocks JSON + token 值 + 允许的字段 schema 喂给大模型，模型输出"修改指令"（改某 block 的文案/图片、改某个 token 值），校验后应用。不允许模型生成任意 HTML/CSS，保持在 schema 范围内确保安全和可渲染。

## 参照 Wix / Lovable / Bolt / v0 / Base44 对比后的定位

这几家的核心差异是在"给 AI 多大自由度"这条轴上选了不同的点：

| 产品 | 自由度 | 安全边界 |
|---|---|---|
| Wix AI | 只能从预建模板/区块库里选+填内容 | 最安全，最不灵活 |
| v0 | 在 React+shadcn 已知组件库内生成代码 | 中等，仍限定技术栈 |
| Lovable/Bolt | 近乎任意生成全栈代码 | 靠**客户端沙箱**（WebContainers）兜底风险 |
| Base44 | 生成完整应用+后端，但全程不暴露代码 | 靠**自家托管的审核层**兜底风险 |

关键判断：PHP/Blade 没有 Lovable/Bolt 那种"客户端沙箱"能兜底任意代码执行的风险——Blade 编译后是真正在服务端跑的 PHP，且这里是单库 + RLS 的共享多租户架构，不是每个用户一个独立沙箱。所以硬凑"任意代码生成"这条路线在架构上是弱势的，**ezsite.app 的自然定位应该是 Wix（内容层自由度拉满）+ Base44（能力扩展走受控管线）的混合**，不是 Lovable/Bolt 那条路。这与本文档已定的 Token+Variant / 仅改内容和 token 的 MVP 范围是一致的，以下是几点补充设计：

1. **把 Action 模式当 AI 的"工具调用层"**：CLAUDE.md 已注明 Action 会被 "MCP requests" 调用，这其实已经预留了接口。AI 不直接吐 HTML/代码，而是调用一组受限 Action（如 `UpdatePageBlock`、`UpdateDesignToken`）——这是结构化、可校验、可回滚的操作，对应"修改指令 schema"落地时的执行层。
2. **两级自由度，对应两条不同的安全通道**：
   - **内容层（MVP 范围）**：AI 只在 block/variant/token 的既有词汇表内做选择和填空，输出永远是 JSON，走现成 Blade 渲染管线，零代码执行风险，可以做到实时生效、无需人审。
   - **能力扩展层（MVP 之后）**：当词汇表不够用（新 block 类型、新 variant），走"隔离环境生成 → 自动跑 Pint/Larastan/Pest → 失败把错误喂回模型自愈 → 通过后才合并部署"的受控管线（类似 Bolt 的报错自愈循环，但验证器换成已有的质量工具链）。**这一层产出的是全局共享代码，绝不允许是某个租户专属的可执行代码**——这是单库 RLS 架构下必须守住的红线。
3. **预览不需要造 WebContainers**：Blade 服务端渲染本身够快，草稿态直接渲染（如 `pages.status = draft`）+ Livewire/Turbo 局部刷新即可做到"所见即所得"，这是 PHP 路线相对 Node 路线的结构性优势。
4. **版本化走"数据快照"而不是 git**：内容层改动都是 JSON，一张 `page_revisions` 存快照即可一键回滚，比 git checkpoint 更轻量。

## 待定 / 下次继续讨论

优先级建议（今天讨论后收敛）：**先定 1，再定 2**，因为 1 决定了 block/token 数据结构要留什么钩子，2 决定了执行层怎么接。

1. **聊天编辑器的"修改指令" schema 长什么样？如何校验/应用/回滚？**（关键路径，建议下次优先展开）
2. **风格预设（Style Preset）具体怎么组织存储**（数据结构、和 tenant 的 `design_tokens` / `pages.blocks` 的关系）？
3. 核心 block 清单最终定几个？每个 block 具体要几个 variant、覆盖哪几种气质（如"温暖手工感"、"专业极简"、"活泼亲和"……）？
4. 差异化组件、Marketplace 这两块虽然不在第一阶段，但要不要现在就预留数据结构上的扩展空间（避免以后大改）？建议至少让 block 的 `type` 字段用字符串+注册表模式，不要写死枚举。

---
*本文档记录的是讨论结论，尚未开始编码。*
