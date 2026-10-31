<?php

declare(strict_types=1);

namespace KetaPay\Resources;

use KetaPay\Models\MoneyAction;

/**
 * @phpstan-import-type RequestOptions from \KetaPay\Http\HttpClient
 */
final class MoneyActions extends AbstractResource
{
    /** @param RequestOptions $options */
    public function retrieve(string $id, array $options = []): MoneyAction
    {
        return $this->object(MoneyAction::class, 'GET', '/money_actions/' . self::seg($id), options: $options);
    }
}
