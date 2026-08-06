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
    | documents land on the private tenant disk, readable only by the agent.
    |
    | The image cap is generous because photos go on the page; the document
    | cap bounds what one turn ships to the vision provider — a menu or a
    | brochure, not a book.
    |
    */

    'attachments' => [
        'max_count' => 4,
        'max_image_kilobytes' => 8192,
        'max_document_kilobytes' => 15360,
        'image_mimes' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
        // Never the public disk: an attached PDF is working material for the
        // agent, not something the site serves.
        'document_disk' => 'local',
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

];
