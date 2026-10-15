<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Chat Attachments
    |--------------------------------------------------------------------------
    |
    | Limits for files the operator attaches to a page-editor chat message.
    | Images are imported into the tenant's media library the moment the
    | message is sent (so the agent can place them in blocks by media id);
    | PDFs are reduced to their text at the same moment (ExtractPdfText) and
    | never stored — the model reads the text, not the file.
    |
    | The image cap is generous because photos go on the page. The document
    | caps bound what one PDF costs: the upload size, and the characters of
    | it that ride in the prompt — and in every later turn's history while
    | the message stays in the window. A menu or a brochure, not a book.
    |
    */

    'attachments' => [
        'max_count' => 4,
        'max_image_kilobytes' => 8192,
        'max_document_kilobytes' => 15360,
        'max_document_characters' => 20000,
        'image_mimes' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Web Page Fetching
    |--------------------------------------------------------------------------
    |
    | Bounds for the chat's FetchWebPage tool. `max_bytes` caps what is read
    | off the wire; `max_chars` caps the extracted text handed to the model.
    | Redirects are followed manually so every hop passes the same SSRF
    | guard — see FetchWebPageText.
    |
    */

    'fetch' => [
        'timeout' => 8,
        'max_redirects' => 3,
        'max_bytes' => 2 * 1024 * 1024,
        'max_chars' => 12000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Chat Rate Limit
    |--------------------------------------------------------------------------
    |
    | Every chat message dispatches an agent turn that can run the provider
    | for up to 90 seconds, so the quota exists to bound spend, not traffic.
    | It is shared by the whole tenant — the site pays for its turns as one
    | account, whoever on the team sends them.
    |
    | Enabled only in production by default: locally and in CI the limiter
    | would only get in the way of iterating on the editor. Set
    | CHAT_RATE_LIMIT_ENABLED=true to reproduce the limit elsewhere.
    |
    */

    'rate_limit' => [
        'enabled' => (bool) env('CHAT_RATE_LIMIT_ENABLED', env('APP_ENV', 'production') === 'production'),
        'max_turns' => (int) env('CHAT_RATE_LIMIT_MAX_TURNS', 30),
        'decay_minutes' => (int) env('CHAT_RATE_LIMIT_DECAY_MINUTES', 60),
    ],

];
