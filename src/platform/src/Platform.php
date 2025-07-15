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

use Symfony\AI\Platform\Contract\ResponseConverter\VectorResponseConverter;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Response\RawHttpResponse;
use Symfony\AI\Platform\Response\ResponsePromise;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Platform implements PlatformInterface
{
    /**
     * @var ModelClientInterface[]
     */
    private readonly array $modelClients;

    /**
     * @var ResponseConverterInterface[]
     */
    private readonly array $responseConverter;

    /**
     * @param iterable<ModelClientInterface>       $modelClients
     * @param iterable<ResponseConverterInterface> $responseConverter
     */
    public function __construct(
        iterable $modelClients,
        iterable $responseConverter,
        private ?Contract $contract = null,
    ) {
        $this->contract = $contract ?? Contract::create();
        $this->modelClients = $modelClients instanceof \Traversable ? iterator_to_array($modelClients) : $modelClients;
        $this->responseConverter = array_merge(
            $responseConverter instanceof \Traversable ? iterator_to_array($responseConverter) : $responseConverter,
            [new VectorResponseConverter()],
        );
    }

    public function request(Model $model, array|string|object $input, array $options = []): ResponsePromise
    {
        $payload = $this->contract->createRequestPayload($model, $input);
        $options = array_merge($model->getOptions(), $options);

        if (isset($options['tools'])) {
            $options['tools'] = $this->contract->createToolOption($options['tools'], $model);
        }

        $response = $this->doRequest($model, $payload, $options);

        return $this->convertResponse($model, $response, $options);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     */
    private function doRequest(Model $model, array|string $payload, array $options = []): ResponseInterface
    {
        foreach ($this->modelClients as $modelClient) {
            if ($modelClient->supports($model)) {
                return $modelClient->request($model, $payload, $options);
            }
        }

        throw new RuntimeException('No response factory registered for model "'.$model::class.'" with given input.');
    }

    /**
     * @param array<string, mixed> $options
     */
    private function convertResponse(Model $model, ResponseInterface $response, array $options): ResponsePromise
    {
        foreach ($this->responseConverter as $responseConverter) {
            if ($responseConverter->supports($model)) {
                return new ResponsePromise(
                    fn (ResponseInterface $response, array $options) => $responseConverter->convert($response, $options),
                    new RawHttpResponse($response),
                    $options,
                );
            }
        }

        throw new RuntimeException('No response converter registered for model "'.$model::class.'" with given input.');
    }
}
