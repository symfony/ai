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
use Symfony\AI\Platform\Result\ResultInterface;

final class PlainConverter implements ResultConverterInterface
{
    public function __construct(
        private readonly ResultInterface $result,
    ) {
    }

    public function supports(Model $model): bool
    {
        return true;
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        return $this->result;
    }

    public function getTokenUsageExtractor(): null
    {
        return null;
    }
}
