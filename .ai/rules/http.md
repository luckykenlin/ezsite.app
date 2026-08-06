---
paths:
  - 'app/Http/**'
---

# Http

## Narrow request input inside the FormRequest
An HTTP form post validates in a FormRequest under `app/Http/Requests` — never `$request->validate()` or `Validator::make()`. Give that FormRequest named accessors returning final typed values, built on `$this->string()`/`$this->integer()`, and have the controller call those. Never read `$request->input()` in a controller; a bare `->query()` read is only for an opaque token, with an explicit cast.
