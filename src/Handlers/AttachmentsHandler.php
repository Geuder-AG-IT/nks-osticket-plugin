<?php
/**
 * Attachment retrieval.
 *
 * Streams a stored attachment's bytes back as base64 so a JSON transport can
 * carry it. Large files are guarded by a size cap to avoid blowing memory.
 */

declare(strict_types=1);

namespace NksOsticket;

final class AttachmentsHandler
{
    /** Max attachment size returned inline (base64), in bytes. */
    private const MAX_INLINE_BYTES = 15 * 1024 * 1024;

    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function download(array $params): array
    {
        $fileId = (int)($params['fileId'] ?? $params['file_id'] ?? 0);
        if ($fileId <= 0) {
            throw new ApiException('download requires a numeric "fileId"', 400);
        }

        $file = \AttachmentFile::lookup($fileId);
        if (!$file) {
            throw new ApiException('Attachment not found', 404);
        }

        $size = (int)$file->getSize();
        if ($size > self::MAX_INLINE_BYTES) {
            throw new ApiException(sprintf(
                'Attachment too large for inline transfer (%d bytes, max %d)',
                $size,
                self::MAX_INLINE_BYTES
            ), 413);
        }

        $data = $file->getData();
        if ($data === false || $data === null) {
            throw new ApiException('Failed to read attachment data', 500);
        }

        return [
            'fileId' => $fileId,
            'filename' => $file->getName(),
            'mimeType' => $file->getType(),
            'size' => $size,
            'encoding' => 'base64',
            'data' => base64_encode($data),
        ];
    }
}
