<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\EdenAi\UniversalAi;

use Symfony\AI\Platform\Bridge\EdenAi\ImageGeneration;
use Symfony\AI\Platform\Bridge\EdenAi\Tts;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;

/**
 * Builds the request body for the Eden AI universal-ai endpoints, shared between the
 * synchronous and the asynchronous model clients.
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
trait UniversalAiPayloadTrait
{
    /**
     * Options that belong to the root of the request body instead of the "input" object.
     *
     * "webhook_receiver" and "user_webhook_parameters" are only honored by the
     * asynchronous endpoint, which extends the synchronous body with them.
     *
     * @var list<string>
     */
    private static array $rootOptions = ['fallbacks', 'provider_params', 'show_original_response', 'user_webhook_parameters', 'webhook_receiver'];

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function createRequestBody(Model $model, array|string $payload, array $options): array
    {
        $input = \is_string($payload) ? [$this->getStringPayloadKey($model) => $payload] : $payload;

        if (isset($input['file_data'])) {
            $fileData = $input['file_data'];
            unset($input['file_data']);

            $input['file'] = $this->uploadBinaryInput($fileData);
        }

        $rootOptions = array_intersect_key($options, array_flip(self::$rootOptions));
        $inputOptions = array_diff_key($options, array_flip(self::$rootOptions));

        return array_merge($rootOptions, [
            'model' => $model->getName(),
            'input' => array_merge($inputOptions, $input),
        ]);
    }

    /**
     * Uploads a normalized binary content entry and returns the resulting file ID.
     *
     * The entry is validated first: an unusable one would otherwise be uploaded as an
     * empty file and then submitted as a billable request.
     */
    private function uploadBinaryInput(mixed $fileData): string
    {
        if (!\is_array($fileData)) {
            throw new RuntimeException('Eden AI binary input must be an array.');
        }

        $data = $fileData['data'] ?? null;

        if (!\is_string($data) || '' === $data) {
            throw new RuntimeException('Eden AI binary input does not contain any data.');
        }

        $decoded = base64_decode($data, true);

        if (false === $decoded || '' === $decoded) {
            throw new RuntimeException('Eden AI binary input is not valid base64 content.');
        }

        $filename = $fileData['filename'] ?? null;
        $format = $fileData['format'] ?? null;

        return $this->getFileUploader()->upload(
            $decoded,
            \is_string($filename) ? $filename : null,
            \is_string($format) ? $format : 'application/octet-stream',
        );
    }

    private function getStringPayloadKey(Model $model): string
    {
        if ($model instanceof Tts || $model instanceof ImageGeneration) {
            return 'text';
        }

        return 'file';
    }

    abstract private function getFileUploader(): FileUploader;
}
