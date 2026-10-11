<?php

declare(strict_types=1);

namespace KetaPay\Resources;

use KetaPay\Models\File;

/**
 * @phpstan-import-type RequestOptions from \KetaPay\Http\HttpClient
 */
final class Files extends AbstractResource
{
    /**
     * Uploads a file for checkout attachments (`agreement`, your key alone), milestone deliveries
     * (`delivery`, seller's token) or dispute evidence (`dispute_evidence`, buyer's or seller's token).
     *
     * `file` is the contents as a string, an open stream, or an `SplFileInfo` for a file on disk.
     *
     * @param array{file: string|resource|\SplFileInfo, filename: string, purpose: string, contentType?: string} $params
     * @param RequestOptions                                                                                   $options
     */
    public function create(array $params, array $options = []): File
    {
        $boundary = 'ketapay-' . bin2hex(random_bytes(16));
        $filename = str_replace(['"', "\r", "\n"], ['%22', '', ''], $params['filename']);
        $contentType = $params['contentType'] ?? 'application/octet-stream';
        $body = "--$boundary\r\n"
            . "Content-Disposition: form-data; name=\"file\"; filename=\"$filename\"\r\n"
            . "Content-Type: $contentType\r\n\r\n"
            . self::contents($params['file']) . "\r\n"
            . "--$boundary\r\n"
            . "Content-Disposition: form-data; name=\"purpose\"\r\n\r\n"
            . $params['purpose'] . "\r\n"
            . "--$boundary--\r\n";
        [$data, $response] = $this->http->request('POST', '/files', multipart: [
            'body' => $body,
            'contentType' => "multipart/form-data; boundary=$boundary",
        ], options: $options);

        return File::constructFrom($data, $response);
    }

    /** @param string|resource|\SplFileInfo $file */
    private static function contents(mixed $file): string
    {
        if ($file instanceof \SplFileInfo) {
            $contents = @file_get_contents($file->getPathname());
            if ($contents === false) {
                throw new \InvalidArgumentException("KetaPay: cannot read {$file->getPathname()}");
            }

            return $contents;
        }
        if (is_resource($file)) {
            return (string) stream_get_contents($file);
        }
        if (is_string($file)) {
            return $file;
        }
        throw new \InvalidArgumentException('KetaPay: file must be a string of bytes, a stream or an SplFileInfo');
    }
}
