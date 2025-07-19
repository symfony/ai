<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform;

use Symfony\AI\Platform\Result\RawResultInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface ModelClientInterface
{
    public function supports(Model $model): bool;

    /**
     * @param array<string|int, mixed> $payload
     * @param array<string, mixed>     $options
     */
    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface;
}
