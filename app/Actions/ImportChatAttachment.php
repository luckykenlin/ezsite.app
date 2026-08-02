<?php

declare(strict_types=1);

namespace App\Actions;

use App\Ai\ChatAttachment;
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
    public function __construct(
        private OptimizeImage $optimizeImage,
        private StoreMedia $storeMedia,
    ) {
        //
    }

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
        // Downscaled and re-encoded before it is stored, like every other
        // image this app writes. This is the path a phone photograph arrives
        // on — the chat rules allow 8 MB and four of them per message — and an
        // attachment the assistant drops into a block is served to the public
        // site verbatim, so it has to be sized for one.
        $optimized = $this->optimizeImage->handle((string) $file->get());

        $media = $this->storeMedia->handle(
            $optimized,
            directory: 'chat',
            prefix: 'chat',
            extra: ['alt' => Str::headline(pathinfo($name, PATHINFO_FILENAME))],
        );

        return new ChatAttachment(
            kind: ChatAttachment::KIND_IMAGE,
            name: $name,
            file: Image::fromStorage($media->path, $media->disk)->as($name)->toArray(),
            mediaId: (int) $media->id,
            width: $optimized->width,
            height: $optimized->height,
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
