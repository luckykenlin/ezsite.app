# 可视化页面编辑器 · 打磨路线图

> 生成于 2026-07-25,来源:7 维度 ultracode 审查(用户体验、左栏可视化、性能、多页面、
> Site Chrome、Design token、Wix/Squarespace/Builder.io 竞品视角)——7 个 agent 实际读
> 代码产出 50 条提案,synthesis 去重、逐条对源码核实后按 影响÷成本 分成三个各自独立
> 可发布的小迭代。原则:不要一开始就做到完美,一小点一点打磨;超预算从每表表尾砍。
>
> 状态:**尚未实施**。实施任一项前先核对相关代码是否已变化。

## 去重与代码校验说明

**合并项**:拖拽排序(3 个维度重复→一项);设备宽度切换(2 处→一项);空页引导(取父窗口覆盖层版,可直接挂 `$wire.addBlock`);"选块跳过整页 reload"并入"pushPreview 幂等化";"chrome 通知加跳转按钮"被迭代 3 chrome 可编辑取代(降入 NOT NOW)。

**已核实的代码事实**(生成时属实):`AddPageBlock` 的 `?int $position` 参数无人调用;编辑器 Visit page 手拼 `'/'.ltrim(slug)` 导致子页面 404(**bug**);Design 页预设 Radio `afterStateUpdated` 点击即写库上线(**误操作风险**);`selectBlock()` 无条件 `pushPreview()` 造成点选闪屏;画布 `chrome-clicked` 不区分 header/footer;编辑器 blade 中零 `wire:loading`/`wire:confirm`。一处纠错:Save 按钮实为 Filament 默认 primary 色(非 gray),相关提案仅保留禁用态+Cmd+S 部分。

---

## ✅ 迭代 1 「立竿见影」【2026-07-25 已全部完成】

> 实现偏差说明:"pushPreview 幂等化"未用 md5 哈希,而是 commit 时递归剥离 null 值使
> 未编辑块与存储态可比,`selectBlock()` 仅在 commit 前后 `$blocks` 有实际差异时才
> push——效果相同且更简单。落库 JSON 从此不再携带 null 字段(形状更小)。

| 改进 | 说明 | 文件 | 工作量 | 依赖 |
|---|---|---|---|---|
| Canvas 加载反馈 + 防连点 | Alpine `reloading` 标志驱动画布顶部 2px 不确定进度条(iframe.onload 清除,挂现有 scroll 恢复 handler);↑/↓/✕/添加块按钮加 `wire:loading.attr="disabled"` 防往返期连点乱序 | `resources/views/filament/tenant/pages/page-editor.blade.php` | S | 无 |
| pushPreview 幂等化(点选不再闪屏) | `pushPreview()` 只置 stale 标志,dehydrate 钩子每请求 flush 一次;flush 时对代入草稿后的 payload 取 md5,与上次相同则跳过 `Cache::put` 和 refresh dispatch——点选未编辑的块不再整页 reload、不再写缓存 | `app/Filament/Tenant/Resources/PageResource/Pages/PageEditor.php`、`tests/Feature/Filament/Tenant/PageEditorTest.php` | S | 无 |
| 设备宽度切换(desktop/tablet/mobile) | 画布上方三键分段控件,Alpine 本地状态改 iframe 包裹层 max-width(100% / 768px / 390px),零往返零 reload,响应式断点天然生效——全表性价比最高的一项 | `page-editor.blade.php` | S | 无 |
| 复制块 ⧉ | 新纯 action `DuplicatePageBlock`(深拷贝 + 新 uuid,插到源块后),`PageEditor::duplicateBlock()` 先 commit 再复制并选中副本;结构行加 ⧉ 按钮。phase-2 AI 免费获得该动词 | `app/Actions/Pages/DuplicatePageBlock.php`、`PageEditor.php`、`page-editor.blade.php`、`tests/Unit/Actions/Pages/DuplicatePageBlockTest.php`、`PageEditorTest.php` | S | 无(迭代 2 画布工具栏的 ⧉ 依赖它) |
| 编辑器内发布/取消发布 | 把 `PageResource::table()` 的 publish 行动作镜像为 header action;`isDirty` 时先走 save() 或警告(明确测试这个交互),发布后 Visit page 经现有闭包自动可见 | `PageEditor.php`、`PageEditorTest.php` | S | 无 |
| ✅ 子页面 Visit URL 修复(bug)【2026-07-25 已完成】 | `'/'.ltrim(slug)` 换成 `$this->pageRecord()->getUrl()`(Fabricator 走 parent 链,带缓存);补 parent+child 回归测试 | `PageEditor.php`、`PageEditorTest.php` | S | 无 |
| 删除块加 `wire:confirm` | ✕ 与 ↑↓ 紧贴且一击即删、自动换选中——先加一行 Livewire 原生确认;迭代 2 撤销栈落地后可换成删除 toast + Undo | `page-editor.blade.php` | S | 无 |
| ✅ 风格预设不再"点击即上线"【2026-07-25 已完成】 | Design 页 preset Radio 的 `afterStateUpdated` 从立即 `ApplyStylePreset` 写库改为仅 `$set` 四个 fine-tune 字段;`save()` 里若四字段仍与预设完全一致则 `ApplyStylePreset`(保留预设标记),否则 `UpdateDesignTokens`(脱离预设) | `app/Filament/Tenant/Pages/Design.php`、`DesignPageTest.php` | S | 无;是迭代 3 Design 弹窗的前置(弹窗内绝不能点击即写库) |

## ✅ 迭代 2 「结构升级」【2026-07-25 已全部完成】

> 全部 11 项已落地。补充:header actions 的回调必须用闭包(`->action(fn () => ...)`),
> 字符串写法只是原始 wire:click,过不了 confirmation 弹窗和 `callAction` 测试。

| 改进 | 说明 | 文件 | 工作量 | 依赖 |
|---|---|---|---|---|
| 拖拽排序(x-sortable + ReorderPageBlocks) | 新纯 action 按 key 序重排(未知/缺失 key 抛异常,风格同 `MovePageBlock`);`.pe-structure` 挂 Filament 自带 `x-sortable`,行加 `x-sortable-item` + ⋮⋮ 把手,`x-on:end` 调 `$wire.reorderBlocks($el.sortable.toArray())`;↑↓ 保留为键盘无障碍路径。N 次往返变 1 次;AI 获得整页重排动词 | `app/Actions/Pages/ReorderPageBlocks.php`、`PageEditor.php`、`page-editor.blade.php`、对应 Unit/Feature 测试 | M | 落地前 search-docs 核对 x-sortable 事件载荷 |
| 结构级撤销/重做 | `$history`/`$future` 快照栈([blocks, selectedBlockKey],上限 ~50),add/remove/move/reorder/duplicate/`applyBlocks` 改动前入栈;Undo/Redo header action + Cmd/Ctrl+Z。phase-2 AI 的安全网:"AI 改坏 → 一键撤销"免费获得 | `PageEditor.php`、`page-editor.blade.php`、`PageEditorTest.php` | M | 建议排在拖拽排序后(reorder 一并入栈) |
| 行摘要 + 变体徽章(一次行布局改造) | `snippet()` 按类型序取 heading/content/首链接文案并 `Str::limit(40)`,选中行读 `data.block` 实时草稿;`variantLabel()` 取 `variants()` 人类标签渲染小 pill。两行行布局(图标+类型+徽章 / 摘要)一次改完,解决"两个 hero 分不清" | `PageEditor.php`、`page-editor.blade.php`、`PageEditorTest.php` | S | 与迭代 3 skipRender 有已知冲突(见该项备注) |
| 块图标进 contract() | Block 基类 `static Heroicon $icon`,9 块各自声明,`contract()` 附带 `icon`(存 enum value 保持 JSON 可序列化);结构行与块库按钮渲染 `<x-filament::icon>`,未注册类型回退问号图标。AI 词汇表同步受益 | `app/Filament/Fabricator/PageBlocks/*.php`(10 个)、`PageEditor.php`、`page-editor.blade.php`、`BlockTest.php`、`BlockRegistryTest.php` | S | 跑一遍 `SiteDraftAgentTest`(消费 vocabulary) |
| 画布悬浮工具栏(↑ ↓ ⧉ ✕) | 选中块的 `data-block-key` wrapper 内追加 absolute 工具条,按钮 postMessage `{type:'action', action, key}`,父窗口映射到现有 `$wire.moveBlock/removeBlock/duplicateBlock`——零新变更路径,"在哪看就在哪操作"。按钮 stopPropagation 避开捕获阶段选块逻辑 | `partials/page-editor-canvas.blade.php`、`page-editor.blade.php`、`PageEditorTest.php` | M | ⧉ 依赖迭代 1 复制块 |
| 左栏悬停联动画布高亮 | 画布脚本新增 `hover` 消息类型(set/clear `data-editor-hover`,样式同现有 :hover 虚线框),结构行 `mouseenter/mouseleave` postMessage。纯客户端,零往返;故意不做 scrollIntoView | `partials/page-editor-canvas.blade.php`、`page-editor.blade.php` | S | 无 |
| 指定位置插入块(激活沉睡的 $position) | v1 最小版:左栏结构行之间的 '+' 按钮,存 `pendingInsertPosition`,`addBlock()` 转发给 `AddPageBlock` 已有的 `$position` 参数并清除。画布间隙 '+' 线作为后续第二步(M) | `PageEditor.php`、`page-editor.blade.php`、`PageEditorTest.php` | S | 无 |
| Save 禁用态 + Cmd+S | Save 在 `!isDirty` 时 disabled、label 切 Save changes/Saved;窗口与 iframe 内均捕获 Cmd/Ctrl+S(iframe 侧 post 'save' 消息转发)。验证失败时 isDirty 仍 true,按钮保持可点,语义自洽 | `PageEditor.php`、`page-editor.blade.php`、`partials/page-editor-canvas.blade.php`、`PageEditorTest.php` | S | 无 |
| 空页引导覆盖层 | blocks 为空时画布上叠居中卡片(父窗口侧 blade 条件渲染,随 Livewire 重渲染自动消失),内嵌 Hero/Features/CTA 快捷 `addBlock` 按钮;同时纠正右栏误导文案("Click a block on the canvas"——空页画布上无可点块) | `page-editor.blade.php`、`PageEditorTest.php` | S | 无 |
| 变体/绑定 Select 去 500ms 防抖 | `variantField()`/`bindField()` 显式 `->live()`,字段级设置覆盖 Section 继承的 debounce——变体切换是视觉反差最大的高频操作,白等半秒最伤手感。落地前先用一个块实测覆盖行为成立 | `app/Filament/Fabricator/PageBlocks/Block.php`、`BlockTest.php` | S | 无 |
| 绑定块 Business 指引 | `bindType === Business` 时右栏 Section 顶部提示"品牌名/logo/联系方式来自 Business profile"+ 新标签页链接(避开 dirty guard);Business 缺失时升级为警示 callout,与画布 amber 占位对齐(现在两栏互相矛盾) | `PageEditor.php`、`PageEditorTest.php` | S | 迭代 3 chrome 伪块自动继承 |

## ✅ 迭代 3 「编辑器即工作台」【2026-07-25 已全部完成】

> 实现备注:(1) chrome 草稿只水合"已存储"的 SiteSetting 槽位(null 保持 null),画布
> 预览在 push 时才计算有效默认——只打开编辑器绝不物化默认 chrome;空槽初始化带默认
> variant,"只看不改"不会置 dirty。(2) commit 的 `withoutNulls` 同时剔除递归后为空的
> 数组(空 repeater / 未设 bind),视图语义等价。(3) 单块补丁:预览路由 `?block=<key>`
> 返回片段(page-blocks 的 `@aware(['page' => null])` 让它可独立渲染);字段编辑走
> `page-editor:patch-canvas`(fetch + outerHTML 替换,失败降级整页),二次击键起
> `skipRender()`——已知取舍:左栏摘要只在 commit/选中时刷新。(4) Design 草稿经
> `ThemeVariables::styleFor()` 编译为 body 级覆盖 style,弹窗关闭(unmountAction)自动
> 丢弃。(5) 内链仍存纯字符串,`page:{id}` 引用方案继续推迟。

### 线 A · 多页面

| 改进 | 说明 | 文件 | 工作量 | 依赖 |
|---|---|---|---|---|
| 左栏页面切换器 | `siblingPages()` 计算方法(RLS 自然限定租户)返回 title/status/editor URL,左栏顶部紧凑列表 + 草稿/已发布圆点,用普通 `<a href>`——现有 `livewire:navigate` dirty guard 零成本复用 | `PageEditor.php`、`page-editor.blade.php`、`PageEditorTest.php` | S | 无 |
| 编辑器内快速新建页 | 切换器底部 "New page" modal(title 自动填 slug + parent 选择),把 `pageSettingsAction` 的 slug 两条规则抽私有 helper 共用,建 Draft 后 redirect 到新页编辑器 | `PageEditor.php`、`page-editor.blade.php`、`PageEditorTest.php` | S | UI 落点依赖切换器,可独立先做成 header action |
| 页面复制 DuplicatePage | 纯 action:replicate title/layout/parent/blocks,强制 Draft,slug 同 parent 内 `-copy`/`-copy-2` 去重;表格行动作 + 编辑器 header action,跳转新页编辑器。AI 可直接调用("照 Services 做一个 roofing 页") | `app/Actions/Pages/DuplicatePage.php`、`PageResource.php`、`PageEditor.php`、对应测试 | S | 无 |
| 内链 datalist(LinkInput 工厂) | 共享字段工厂 `LinkInput::make()`:原 TextInput + `->datalist(内部页面路径)`(经 Fabricator `getUrl()` 组合父子路径),替换 Header/Hero/Cta 的 url 字段。存储仍是纯字符串——XSS、contract、视图全不变。slug 改名不回写是已知局限,`page:{id}` 引用方案明确推迟 | `app/Filament/Fabricator/Fields/LinkInput.php`(新目录需确认)、`Header.php`、`Hero.php`、`Cta.php`、块测试 | S | 无 |
| 设置弹窗实时 URL 预览 | parent_id `->live()` + slug 下 helperText 实时算出 `/services/plumbing` 最终路径 | `PageEditor.php`、`PageEditorTest.php` | S | 无 |

### 线 B · Chrome 与设计进编辑器

| 改进 | 说明 | 文件 | 工作量 | 依赖 |
|---|---|---|---|---|
| Chrome 可选中、可在右栏编辑 | `'chrome:header'/'chrome:footer'` 伪 key,在 selectedBlock/selectedBlockSection/fillBlockForm/commitSelectedBlock/save 五个触点分支(注意:现 `blockIndexOrNull` 对 chrome key 返回 null 会让 commit 静默丢弃,分支是硬前提);`SiteChromeSettings::save()` 抽成 `SaveSiteChrome` action 共用;预览 payload 带 chrome 草稿,main.blade.php 编辑分支渲染草稿 chrome 并取消调暗。**chrome 首次拥有实时预览**。v1 不做 chrome 增删排;`applyBlocks`(AI 入口)保持 page-blocks-only。同 PR 顺带:空槽渲染可点虚线占位条,左栏顶/底钉住 Header/Footer 行 | `PageEditor.php`、`CachePageEditorPreview.php`、`PageEditorPreviewController.php`、`main.blade.php`、`app/Actions/SaveSiteChrome.php`、`SiteChromeSettings.php`、`page-editor.blade.php`、相关测试 | M–L | 无硬依赖;迭代 2 绑定块指引自动生效 |
| 编辑器 Design 弹窗 + 画布 token 草稿预览 | 第一步(S,零行为变化):`ThemeVariables::variablesFor(DesignTokens, ?Business)` 重构,原方法一行委托——所有草稿预览的 plumbing。第二步(M):预览 payload 可带 `design_tokens` 草稿,预览文档在 HEAD_END 样式后追加 draft `<style>`(后者胜出,XSS 保证不变:全部 enum 常量/正则校验 hex);PageEditor 加 Design header action 复用 Design.php 抽出的 schema,改字段只刷画布,"Apply to site" 才落库。字体草稿需同 PR 补 `Vite::fonts()` 预载 | `ThemeVariables.php`、`CachePageEditorPreview.php`、`PageEditorPreviewController.php`、`page-editor-preview.blade.php`、`PageEditor.php`、`Design.php`、`ThemeVariablesTest.php` 等 | S + M | 硬依赖迭代 1 "预设不点击即上线" |

### 线 C · 性能重构

| 改进 | 说明 | 文件 | 工作量 | 依赖 |
|---|---|---|---|---|
| 单块 HTML 补丁替代整页 reload | 预览路由接受 `?block=<key>` 返回单块片段(喂单元素数组给现有 page-blocks 组件,转义管线不变);字段编辑改 dispatch patch 事件,父窗口 fetch 后 postMessage 进 iframe 做 `[data-block-key]` outerHTML 替换并保留选中态;结构变更仍整页,fetch 失败降级整页。打字反馈从"整文档重载+闪烁"变为局部换块 | `PageEditorPreviewController.php`、`PageEditor.php`、`page-editor.blade.php`、`partials/page-editor-canvas.blade.php`、两个测试文件 | M | 建立在迭代 1 幂等化之上 |
| 字段编辑 skipRender() | `data.block.*` 更新时 `skipRender()`(仅 isDirty false→true 那次放行,Save 徽章需要);打字时响应体从整页 HTML 缩到 snapshot。**注意**:迭代 2 行摘要的"跟打字实时更新"依赖每 tick 重渲染——落地时摘要改为提交时更新并加注释钉住前提 | `PageEditor.php`、`PageEditorTest.php` | S | 与上一项同 PR 顺手做 |

## ✅ 迭代 4 「画布即操作面」【2026-07-25 已完成】

| 改进 | 说明 |
|---|---|
| 画布内拖拽排序 | 悬浮工具栏新增 ⠿ 把手,HTML5 DnD 实时移位,松手比对顺序变化后一次 `reorderBlocks`;chrome 不可拖。**拖拽模式**:dragstart 后整页块折叠为 4.5rem 紧凑卡片(`data-block-type` 标签覆盖,dragstart 内改布局会中断原生拖拽,故 setTimeout 0 延迟进入),解决整屏高块拖不动的问题;iframe 内原生自动滚动不可靠,dragover 近边缘手动 scrollBy 助滚;drop 后还原并居中落点块 |
| 画布块间 "+" 线 | `page-blocks` 编辑模式(`insertable` prop,仅页面块)注入 `data-editor-insert` 悬停分隔线,点击 `queueInsertAt` 并回发 armed 高亮;patch 片段与 live 站不含(有泄漏断言) |
| 画布内联文本编辑 | 见上表 ✅ 行 |
| 结构列表默认折叠 | 左栏 Page structure 改为可折叠(`$persist` 记忆,默认收起)——画布已覆盖选中/排序/插入/增删,列表退为概览 |

## ✅ 迭代 5 「Wix 手感」【2026-07-26 已完成】

| 改进 | 说明 |
|---|---|
| 库拖入画布 | 库按钮 `draggable`(同源 iframe 的 DnD 事件跨框架连通);拖起即画布折叠+插入线常亮(`data-editor-insert-mode`,与 reorder 的 drag-mode 共用折叠 CSS),rAF 节流计算最近插入线并 armed 高亮,drop 读 `application/x-ezsite-block` MIME → 父窗口校验 type ∈ 块库白名单 → `addBlockAt(type, position)`(自 `addBlock` 重构抽出,drop 直插)。点击加块老路径原样保留 |
| 画布键盘操作 | Esc 取消选中(新 `deselectBlock()`:commit 守卫,无效草稿保持选中)、Delete/Backspace 删除(确认)、Cmd/Ctrl+↑↓ 移动;**输入框聚焦或有 mounted action 弹窗时一律不劫持**;父窗口与 iframe 双侧生效(iframe 走 shortcut 转发) |
| 保存/发布 toast 带 View live | `Notification->actions()`(统一 `Filament\Actions\Action`),已发布页保存和发布成功都附新标签页直达按钮 |
| 50% 概览档 | 设备切换组第四档:frame 200% 尺寸 + scale(0.5),纯 CSS 零往返,块仍可点选 |
| 空白点击取消选中 | 画布空白区(canvas 内)与设备框外灰区(父窗口 `.pe-canvas-body` click.self)都清除选中,canvas 侧先行本地清除做即时反馈 |
| 块示例内容(2026-07-26 补) | `Block::$sample` 每块声明可通过自身校验的示例文案(文案自我说明"Your headline goes here";gallery 用自包含 SVG data-URI 占位图),`AddPageBlock` 落块即填——修复"拖入即 required 报错";首次加块弹一次性提示"双击画布文字即可修改";守卫测试:sample 键 ⊆ contract fields、9 块连加连存全过校验 |

## NOT NOW(明确推迟,及理由)

| 提案 | 推迟理由 |
|---|---|
| ✅ 画布内联文本编辑【2026-07-25 已完成】 | 双击选中块文本 → 父窗口按草稿字段值**精确匹配**授权(只有与某字符串字段完全一致的文本可编辑,含 chrome)→ `contenteditable="plaintext-only"`(旧浏览器回退 + paste 剥离)→ 防抖 400ms 走 `$wire.set` + 单块补丁;Enter/blur 提交(`$refresh` 同步右栏),Esc 还原;编辑中抑制该块 patch。零服务端新代码 |
| Livewire snapshot 瘦身(非选中块移出 wire 状态) | 草稿唯一权威移入 cache 意味着缓存驱逐=丢稿,还改变 `applyBlocks` 语义;等幂等化+补丁落地后实测体积再决定 |
| Section 预设(预填块组合) | 文案编写与预设腐烂的维护成本前置;AI 初稿已覆盖该场景;等 phase-2 AI few-shot 设计时一起做,复用一份数据 |
| 块库分组 + BlockCategory/description | 9 个块不需要分组;图标先行;description 对 AI 词汇表有复利,待真正需要时随 contract 一起加 |
| CSS 迷你缩略图 | 先看迭代 2 图标+摘要效果 |
| Design 页 live 小样/字体真实渲染/预设色卡 | 大部分价值被迭代 3 Design 弹窗取代;若做,随弹窗 schema 抽取一起最省 |
| Per-block tone(块级底色枚举) | 唯一扩宽块数据模型的提案,动 Token+Variant 设计哲学——按约定先与用户确认再排期 |
| chrome-clicked 通知加跳转按钮 | 被迭代 3 线 B 完全取代;仅当线 B 排期超一个月才值得做过渡版 |
| 自动保存 | 显式 Save + dirty guard + beforeunload 已成体系;迭代 2 撤销栈先补"误操作恢复"更缺的板 |

## 验证方式(每项落地都适用)

- 新 action 必配 `tests/Unit/Actions/Pages/*` 单元测试;Livewire 方法必配 `PageEditorTest` feature 测试;写前查 `pest-testing` skill。
- `composer test:unit` 守 100% 行覆盖,`composer test:type-coverage`、phpstan、rector、`vendor/bin/pint --dirty`、`vendor/bin/filacheck --dirty` 全绿。
- 所有输出走 `{{ }}` 转义;postMessage 双向校验 origin + ns;全程无新 npm 依赖、无 rebuild(x-sortable 与 Heroicon 均 Filament 自带)。
- 手工验证:`composer run dev` → 租户面板 → Pages → Edit,逐项过交互(canvas 进度条、设备切换、复制块、拖拽、悬浮工具栏、Cmd+S 等)。
