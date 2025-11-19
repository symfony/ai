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

use Symfony\AI\Platform\Exception\ExceptionInterface;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface ResultConverterInterface
{
    public function supports(Model $model): bool;

    /**
     * @param array<string, mixed> $options
     *
     * @throws ExceptionInterface
     */
    public function convert(RawResultInterface $result, array $options = []): ResultInterface;
}
