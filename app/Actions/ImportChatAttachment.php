<?php

declare(strict_types=1);

namespace App\Actions;

use App\Ai\ChatAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Laravel\Ai\Files\Image;

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
 * Documents are reduced to their text ({@see ExtractPdfText}) and the PDF
 * itself is not kept: the chat's provider reads no documents, so the text is
 * the only part of the file anything downstream uses, and it travels inside
 * the attachment row. Storing bytes nothing reads would only be a cost.
 *
 * Runs inside the editor's Livewire request, in tenant context — the Media
 * model stamps `tenant_id` itself and the public disk is tenant-suffixed.
 */
final readonly class ImportChatAttachment
{
    public function __construct(
        private OptimizeImage $optimizeImage,
        private StoreMedia $storeMedia,
        private ExtractPdfText $extractPdfText,
    ) {
        //
    }

    public function handle(UploadedFile $file): ChatAttachment
    {
        $name = $file->getClientOriginalName();

        if (str_starts_with($file->getMimeType() ?? '', 'image/')) {
            return $this->importImage($file, $name);
        }

        return $this->readDocument($file, $name);
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

    private function readDocument(UploadedFile $file, string $name): ChatAttachment
    {
        return new ChatAttachment(
            kind: ChatAttachment::KIND_DOCUMENT,
            name: $name,
            file: [],
            text: $this->extractPdfText->handle((string) $file->get()),
        );
    }
}
