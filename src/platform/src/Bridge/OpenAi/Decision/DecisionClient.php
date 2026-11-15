<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Decision;

use Symfony\AI\Platform\Bridge\OpenAi\RegionAwareTrait;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Sends the invocations with the "questions" option to the "POST /v1/decisions" endpoint of the OpenAI API.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class DecisionClient
{
    use RegionAwareTrait;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly ?string $region = null,
    ) {
    }

    /**
     * @param array<string, mixed>|string $payload
     * @param array<string, mixed>        $options
     */
    public function request(Model $model, array|string $payload, array $options): RawHttpResult
    {
        if (!\is_array($payload) || !isset($payload['input'])) {
            throw new InvalidArgumentException(\sprintf('The decision model "%s" requires a string, an array, a "Text", an "Image" or a "Collection" input.', $model->getName()));
        }

        $questions = [];
        foreach ($options['questions'] as $name => $question) {
            $questions[] = ['name' => (string) $name] + $question;
        }

        return new RawHttpResult($this->httpClient->request('POST', self::getBaseUrl($this->region).'/v1/decisions', [
            'auth_bearer' => $this->apiKey,
            'json' => ['model' => $model->getName(), 'input' => $payload['input'], 'questions' => $questions],
        ]));
    }
}
