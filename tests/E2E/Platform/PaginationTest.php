<?php

declare(strict_types=1);

namespace KetaPay\Tests\E2E\Platform;

use KetaPay\Tests\E2E\Support\E2ETestCase;
use PHPUnit\Framework\Attributes\TestDox;

final class PaginationTest extends E2ETestCase
{
    #[TestDox('[E2E-023] lists paginate across more than one page')]
    public function testPagination(): void
    {
        $k = self::ketapay();
        $k->checkoutSessions->create(self::sessionParams());
        $k->checkoutSessions->create(self::sessionParams());
        self::assertTrue($k->checkoutSessions->list(['limit' => 1])->has_more);
        $ids = [];
        foreach ($k->checkoutSessions->list(['limit' => 1]) as $session) {
            $ids[] = $session->id;
            if (count($ids) === 2) {
                break;
            }
        }
        self::assertCount(2, array_unique($ids));
    }
}
