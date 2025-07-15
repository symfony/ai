<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Contract\ResponseConverter;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Response\ResponseInterface as LlmResponse;
use Symfony\AI\Platform\Response\VectorResponse;
use Symfony\AI\Platform\ResponseConverterInterface;
use Symfony\AI\Platform\Vector\Vector;
use Symfony\Component\JsonPath\JsonCrawler;
use Symfony\Component\JsonPath\JsonPath;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponse;

readonly class VectorResponseConverter implements ResponseConverterInterface
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

    public function convert(HttpResponse $response, array $options = []): LlmResponse
    {
        $crawler = new JsonCrawler($response->getContent(false));
        $vectors = $crawler->find($this->query);

        if (empty($vectors)) {
            throw new RuntimeException('Response does not contain any vectors');
        }

        return new VectorResponse(
            ...array_map(static fn (array $vector): Vector => new Vector($vector), $vectors),
        );
    }
}
