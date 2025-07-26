<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Contract\ResultConverter;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\Component\JsonPath\JsonCrawler;
use Symfony\Component\JsonPath\JsonPath;

readonly class VectorResultConverter implements ResultConverterInterface
{
    public function __construct(
        private string|JsonPath $query = '$.data[*].embedding',
    ) {
    }

    public function supports(Model $model): bool
    {
        // TODO: THIS IS NOT ENOUGH TO GET ACTIVATED
        return $model->supports(Capability::OUTPUT_VECTOR);
    }

    public function convert(RawResultInterface|RawHttpResult $result, array $options = []): ResultInterface
    {
        $crawler = new JsonCrawler($result->getObject()->getContent(false));
        $vectors = $crawler->find($this->query);

        if (empty($vectors)) {
            throw new RuntimeException('Response does not contain any vectors');
        }

        return new VectorResult(
            ...array_map(static fn (array $vector): Vector => new Vector($vector), $vectors),
        );
    }
}
