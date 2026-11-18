<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Image;

use Symfony\AI\Platform\Bridge\OpenAi\AbstractModelClient;
use Symfony\AI\Platform\Bridge\OpenAi\Image;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\Content\Image as ImageContent;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @see https://platform.openai.com/docs/api-reference/images/create
 * @see https://platform.openai.com/docs/api-reference/images/createEdit
 *
 * @author Denis Zunke <denis.zunke@gmail.com>
 */
final class ModelClient extends AbstractModelClient implements ModelClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly ?string $region = null,
    ) {
        self::validateApiKey($apiKey);
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Image;
    }

    /**
     * @param array{image?: ImageContent|list<ImageContent>, ...} $options
     */
    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        if (!\is_string($payload)) {
            throw new InvalidArgumentException(\sprintf('The image prompt must be a string, but "%s" was given to "%s".', get_debug_type($payload), self::class));
        }

        // A source image turns the request into an edit (different endpoint and encoding).
        if (isset($options['image'])) {
            return $this->edit($model, $payload, $options);
        }

        return new RawHttpResult($this->httpClient->request('POST', self::getBaseUrl($this->region).'/v1/images/generations', [
            'auth_bearer' => $this->apiKey,
            'json' => array_merge($options, [
                'model' => $model->getName(),
                'prompt' => $payload,
            ]),
        ]));
    }

    /**
     * @param array{image: ImageContent|list<ImageContent>, ...} $options
     */
    private function edit(Model $model, string $prompt, array $options): RawHttpResult
    {
        $images = $options['image'] instanceof ImageContent ? [$options['image']] : $options['image'];
        unset($options['image']);

        if (!\is_array($images) || [] === $images || !array_is_list($images)) {
            throw new InvalidArgumentException(\sprintf('The "image" option must be an instance of "%s" or a non-empty list of them, but "%s" was given.', ImageContent::class, get_debug_type($images)));
        }

        foreach ($images as $image) {
            if (!$image instanceof ImageContent) {
                throw new InvalidArgumentException(\sprintf('The "image" option must be an instance of "%s" or a non-empty list of them, but "%s" was given.', ImageContent::class, get_debug_type($image)));
            }
        }

        $imageField = 1 === \count($images) ? 'image' : 'image[]';

        $fields = array_merge($options, [
            'model' => $model->getName(),
            'prompt' => $prompt,
        ]);

        // The multipart body is built by hand: the HttpClient form encoder cannot advertise the image
        // MIME type without the (optional) symfony/mime component and would fall back to
        // "application/octet-stream", which the images endpoint rejects.
        $boundary = 'symfony-ai-'.bin2hex(random_bytes(16));
        $body = '';
        foreach ($fields as $name => $value) {
            $body .= \sprintf("--%s\r\nContent-Disposition: form-data; name=\"%s\"\r\n\r\n%s\r\n", $boundary, $name, $value);
        }

        foreach ($images as $image) {
            $format = $image->getFormat();
            $filename = $image->getFilename() ?? 'image.'.(substr($format, strpos($format, '/') + 1) ?: 'png');
            $body .= \sprintf("--%s\r\nContent-Disposition: form-data; name=\"%s\"; filename=\"%s\"\r\nContent-Type: %s\r\n\r\n%s\r\n", $boundary, $imageField, $filename, $format, $image->asBinary());
        }
        $body .= \sprintf("--%s--\r\n", $boundary);

        return new RawHttpResult($this->httpClient->request('POST', self::getBaseUrl($this->region).'/v1/images/edits', [
            'auth_bearer' => $this->apiKey,
            'headers' => ['Content-Type' => 'multipart/form-data; boundary='.$boundary],
            'body' => $body,
        ]));
    }
}
