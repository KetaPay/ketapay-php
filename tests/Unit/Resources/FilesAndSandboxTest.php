<?php

declare(strict_types=1);

namespace KetaPay\Tests\Unit\Resources;

use KetaPay\Errors\InvalidRequestError;
use KetaPay\Models\File;
use KetaPay\Tests\Support\MockTransport;
use KetaPay\Tests\Support\TestCase;
use PHPUnit\Framework\Attributes\TestDox;

final class FilesAndSandboxTest extends TestCase
{
    #[TestDox('[SEC-007] uploads as multipart with the purpose, never inlined into JSON')]
    public function testMultipart(): void
    {
        $t = new MockTransport(['body' => ['id' => 'f1', 'object' => 'file']]);
        $file = self::client($t)->files->create(['file' => '%PDF-1.7', 'filename' => 'brief.pdf', 'purpose' => 'agreement'], ['customerToken' => 't']);
        self::assertInstanceOf(File::class, $file);
        $r = $t->last();
        self::assertMatchesRegularExpression('#^multipart/form-data; boundary=(ketapay-[0-9a-f]{32})$#', $r->headers['Content-Type']);
        $boundary = substr($r->headers['Content-Type'], strlen('multipart/form-data; boundary='));
        self::assertStringContainsString("Content-Disposition: form-data; name=\"file\"; filename=\"brief.pdf\"\r\nContent-Type: application/octet-stream\r\n\r\n%PDF-1.7\r\n", (string) $r->body);
        self::assertStringContainsString("Content-Disposition: form-data; name=\"purpose\"\r\n\r\nagreement\r\n--$boundary--", (string) $r->body);
        self::assertNull(json_decode((string) $r->body));
    }

    #[TestDox('reads the file from a stream or a path')]
    public function testSources(): void
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'ketapay');
        file_put_contents($path, 'from disk');
        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);
        fwrite($stream, 'from stream');
        rewind($stream);
        try {
            $t = new MockTransport(['body' => []], ['body' => []]);
            $k = self::client($t);
            $k->files->create(['file' => new \SplFileInfo($path), 'filename' => 'a.txt', 'purpose' => 'agreement']);
            $k->files->create(['file' => $stream, 'filename' => 'b.txt', 'purpose' => 'agreement']);
            self::assertStringContainsString("\r\n\r\nfrom disk\r\n", (string) $t->requests[0]->body);
            self::assertStringContainsString("\r\n\r\nfrom stream\r\n", (string) $t->requests[1]->body);
            self::catching(\InvalidArgumentException::class, fn() => $k->files->create(['file' => new \SplFileInfo('/nope/missing'), 'filename' => 'c', 'purpose' => 'agreement']));
            /** @phpstan-ignore argument.type (a wrong type on purpose) */
            self::catching(\InvalidArgumentException::class, fn() => $k->files->create(['file' => 42, 'filename' => 'c', 'purpose' => 'agreement']));
            self::assertCount(2, $t->requests);
        } finally {
            fclose($stream);
            unlink($path);
        }
    }

    #[TestDox('[SEC-005] test helpers fail at once with a live key, without contacting KetaPay')]
    public function testSandboxOnly(): void
    {
        $t = new MockTransport(['body' => []]);
        $live = self::client($t, ['apiKey' => 'sk_live_x']);
        $e = self::catching(InvalidRequestError::class, fn() => $live->testHelpers->escrows->simulate('e1', ['action' => 'deliver']));
        self::assertSame('test_mode_only', $e->code);
        $e = self::catching(InvalidRequestError::class, fn() => $live->testHelpers->checkoutSessions->pay('cs1'));
        self::assertSame('test_mode_only', $e->code);
        self::assertCount(0, $t->requests);
    }

    #[TestDox('[SEC-003] refuses an empty id instead of calling the collection')]
    public function testEmptyId(): void
    {
        $t = new MockTransport();
        self::catching(\InvalidArgumentException::class, fn() => self::client($t)->escrows->retrieve(''));
        self::assertCount(0, $t->requests);
    }
}
