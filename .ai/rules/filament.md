---
paths:
  - 'app/Filament/**'
---

# Filament

## Authorization lives in Filament hooks, not Gates or Policies
Do not add Policy classes or `Gate::define` — panel entry is decided in `User::canAccessPanel()`, and per-resource or per-page permission is a static `can*()` override on the Resource, Page or Widget itself. Express conditional access with Filament's `->visible()`/`can*()` hooks rather than an `authorize()` call in the body. Row visibility is RLS's job; never re-check tenant ownership in PHP.

## Validate at the surface, never with $request->validate()
Filament fields validate through fluent field methods such as `->required()` and `->maxLength()`, never a `->rules([...])` array. A Livewire or Filament page class validates with an inline rules array passed to `$this->validate([...])`. Never call `$request->validate()` or `Validator::make()`.

## Custom validation is a closure or a throwing Action, not a Rule object
Express a one-off custom check as a closure passed to a field's `->rule()`, extracting it to a named static method when it is long or reused in the same schema. When the same check guards more than one surface, put it in an Action whose `handle()` throws `ValidationException::withMessages()`. Do not create Rule objects or call `Validator::extend()`.

## Extract a Filament action once it carries a modal or real work
Extract into an `Actions/` directory beside the resource it serves, as a class exposing `static make(): Action` that delegates straight to an `app/Actions` class, once the action has a modal schema, multi-step state, or a handler worth testing on its own — `PageResource/Actions/` and `Posts/Actions/` are the reference. Below that threshold, leave it inline in the `Tables/`, `Pages/` or settings class: a `->url()` jump or a single-expression `->action()` (see `Leads/Tables/LeadsTable.php`'s `markAsRead`/`archive`, `Tenants/Tables/TenantsTable.php`'s `manageUsers`) reads better where it is used. Nothing enforces this in `tests/Arch`, so it is a judgement call — if you want it absolute, add the arch rule and migrate the inline ones rather than asserting it here.

## AI 消耗类� �口的限流模式(production-only)
给会花 AI provider 钱的 Livewire 入口加限流时:直接调 RateLimiter facade(不建 RateLimiter::for()/middleware),key 按租户共享(`page-chat:{tenant_id}`;CacheTenancyBootstrapper 的 cache 前缀才是真正的隔离机制),限额放 config(`chat.rate_limit`),开关默认跟随 APP_ENV(`enabled => env('CHAT_RATE_LIMIT_ENABLED', APP_ENV === 'production')`)——不要在代码里写 App::isProduction(),config 开关才能让 100% 覆盖率两侧分支都可测。页面编辑器聊天里拒绝必须走 Notification + early return(不设 chatTurnToken),不能抛 ValidationException:chat-rail.ts 以 "promise resolve 后无 token" 识别拒绝,reject 会把 composer 卡在 sending 态。参考 InteractsWithPageChat::chatTurnRateLimited()。
