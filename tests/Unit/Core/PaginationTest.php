<?php

declare(strict_types=1);

namespace KetaPay\Tests\Unit\Core;

use KetaPay\Models\Escrow;
use KetaPay\Tests\Support\MockTransport;
use KetaPay\Tests\Support\TestCase;
use PHPUnit\Framework\Attributes\TestDox;

final class PaginationTest extends TestCase
{
    #[TestDox('[PAG-001] returns the first page')]
    public function testFirstPage(): void
    {
        $t = new MockTransport(self::page([['id' => 'e1', 'object' => 'escrow']], 'cur_2'));
        $page = self::client($t)->escrows->list();
        self::assertCount(1, $page->data);
        self::assertInstanceOf(Escrow::class, $page->data[0]);
        self::assertSame('cur_2', $page->next_cursor);
        self::assertTrue($page->has_more);
        self::assertCount(1, $t->requests);
    }

    #[TestDox('[PAG-002] walks every page with foreach, passing the cursor and keeping filters')]
    public function testWalksPages(): void
    {
        $t = new MockTransport(self::page([['id' => 'e1'], ['id' => 'e2']], 'cur_2'), self::page([['id' => 'e3']], null));
        $ids = [];
        foreach (self::client($t)->escrows->list(['status' => 'active'], ['headers' => ['X-Trace' => 't']]) as $i => $escrow) {
            $ids[$i] = $escrow->id;
        }
        self::assertSame(['e1', 'e2', 'e3'], $ids);
        self::assertSame('https://api.ketapay.com/v1/escrows?status=active&cursor=cur_2', $t->requests[1]->url);
        self::assertSame('t', $t->requests[1]->headers['X-Trace']);
    }

    #[TestDox('[PAG-002] stops after the last page, and an early break fetches nothing more')]
    public function testStops(): void
    {
        $t = new MockTransport(self::page([['id' => 'e1'], ['id' => 'e2']], 'cur_2'), self::page([['id' => 'e3']], null));
        foreach (self::client($t)->escrows->list() as $escrow) {
            break;
        }
        self::assertCount(1, $t->requests);
    }

    #[TestDox('[PAG-003] gives the first page with its response')]
    public function testPageResponse(): void
    {
        $t = new MockTransport([...self::page([], null), 'headers' => ['KetaPay-Request-Id' => 'req_9']]);
        $page = self::client($t)->events->list();
        self::assertSame('list', $page->object);
        self::assertSame('req_9', $page->getLastResponse()?->requestId);
        self::assertSame([], iterator_to_array($page));
    }

    #[TestDox('non-paginated lists are countable and iterable')]
    public function testCollection(): void
    {
        $t = new MockTransport(['body' => ['object' => 'list', 'data' => [['object' => 'bank', 'code' => '057'], ['object' => 'bank', 'code' => '058']]], 'headers' => ['KetaPay-Request-Id' => 'req_b']]);
        $banks = self::client($t)->banks->list();
        self::assertCount(2, $banks);
        self::assertSame(['057', '058'], array_map(static fn($b) => $b->code, iterator_to_array($banks)));
        self::assertSame('req_b', $banks->getLastResponse()?->requestId);
    }
}
