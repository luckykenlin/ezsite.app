---
paths:
  - 'database/migrations/**'
---

# Migrations

## Enums are string columns cast to backed PHP enums
Store an enum-valued column as `string()` with a default matching the PHP case value, never as a database `enum()` column, so adding a case needs no migration. Back every enum with `: string` in `app/Enums` and register it in the model's `casts()`; add `HasLabel`/`HasColor` when Filament renders it.

## constrained() for normal FKs; tenant_id is a hand-written string FK
Declare an ordinary foreign key with `$table->foreignId('x_id')->constrained()` plus an explicit `nullOnDelete()`/`cascadeOnDelete()`, and pass the fabricator table name from config when the target is the pages table. `tenant_id` is the exception: it must be `string('tenant_id')` with a separate `$table->foreign('tenant_id')->references('id')->on('tenants')`, because the tenants primary key is a string and `foreignId`/`foreignUuid`/`constrained()` would emit the wrong column type.
