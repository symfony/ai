<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Contract\ResultExtractor;

use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\Component\JsonPath\JsonCrawler;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
interface ResultExtractorInterface
{
    public function supports(JsonCrawler $crawler): bool;

    /**
     * @return ResultInterface[]
     */
    public function extract(JsonCrawler $crawler): array;
}
