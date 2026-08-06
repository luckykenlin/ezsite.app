---
paths:
  - 'lang/**'
---

# Lang

## Marketing-only dotted keys in one group
Translatable copy belongs to the central marketing site only, as dotted keys inside the single `marketing` group — never a new lang group, never a JSON catalogue. A second group collides with Filament's `->translateLabel()` on a case-insensitive filesystem, which is green on Linux CI and fatal on a Mac. Elsewhere `__('Sentence')` is a plain English literal with no catalogue behind it. Add `marketing` keys to both locales in the same change so those two files stay key-identical; that parity requirement is `marketing`-only — `validation.php` is an overlay and exists for `zh` alone, because English already falls back to the framework's own messages.

## Validation wording lives in lang files, not messages()/attributes()
Override a validation message or field label by adding the key to `lang/<locale>/validation.php`, keeping that file an overlay of only the rules the surface can actually trigger and letting the rest fall back to the framework's. Do not add `messages()` or `attributes()` to a FormRequest. Custom failure text is a translation key resolved with `__()`.
