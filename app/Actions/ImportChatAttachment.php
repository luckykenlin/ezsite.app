<?php

declare(strict_types=1);

namespace App\Actions;

use App\Ai\ChatAttachment;
use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Files\Document;
use Laravel\Ai\Files\Image;
use RuntimeException;

/**
 * Land one chat upload where the rest of the turn can reach it, at the moment
 * the message is sent.
 *
 * Images go straight into the tenant's media library ({@see Media}), exactly
 * like a stock-photo import ({@see FindOrImportStockPhoto}) — imported BEFORE
 * the job runs, so the media id already exists when the agent is told about it
 * and SetBlockImage can place it in the same turn. An image the agent never
 * uses simply stays in the library, where the operator can pick it by hand.
 *
 * Documents are working material, not site assets: they land on the private
 * tenant disk, readable only when the agent's history rehydrates them.
 *
 * Runs inside the editor's Livewire request, in tenant context — the Media
 * model stamps `tenant_id` itself and both disks are tenant-suffixed.
 */
final readonly class ImportChatAttachment
{
    public function handle(UploadedFile $file): ChatAttachment
    {
        $name = $file->getClientOriginalName();

        if (str_starts_with($file->getMimeType() ?? '', 'image/')) {
            return $this->importImage($file, $name);
        }

        return $this->storeDocument($file, $name);
    }

    private function importImage(UploadedFile $file, string $name): ChatAttachment
    {
        $disk = config()->string('curator.default_disk');

        // Server-derived extension (from the sniffed MIME), never the client's
        // filename — same reasoning as FindOrImportStockPhoto refusing to trust
        // a URL's trailing characters.
        $extension = $file->extension();
        $basename = 'chat-'.Str::uuid();
        $path = Storage::disk($disk)->putFileAs('chat', $file, $basename.'.'.$extension);

        throw_if($path === false, RuntimeException::class, 'The attached image could not be stored.');

        $dimensions = @getimagesize($file->getRealPath()) ?: null;

        $media = Media::query()->create([
            'disk' => $disk,
            'directory' => 'chat',
            'visibility' => 'public',
            'name' => $basename,
            'path' => $path,
            'width' => $dimensions[0] ?? null,
            'height' => $dimensions[1] ?? null,
            'size' => $file->getSize(),
            'type' => $file->getMimeType(),
            'ext' => $extension,
            'alt' => Str::headline(pathinfo($name, PATHINFO_FILENAME)),
        ]);

        return new ChatAttachment(
            kind: ChatAttachment::KIND_IMAGE,
            name: $name,
            file: Image::fromStorage($path, $disk)->as($name)->toArray(),
            mediaId: (int) $media->id,
            width: $dimensions[0] ?? null,
            height: $dimensions[1] ?? null,
        );
    }

    private function storeDocument(UploadedFile $file, string $name): ChatAttachment
    {
        $disk = config()->string('chat.attachments.document_disk');

        $path = Storage::disk($disk)->putFileAs('chat', $file, 'chat-'.Str::uuid().'.pdf');

        throw_if($path === false, RuntimeException::class, 'The attached document could not be stored.');

        return new ChatAttachment(
            kind: ChatAttachment::KIND_DOCUMENT,
            name: $name,
            file: Document::fromStorage($path, $disk)->as($name)->toArray(),
        );
    }
}
