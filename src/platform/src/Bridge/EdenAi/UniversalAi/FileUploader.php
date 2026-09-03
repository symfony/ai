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

use Symfony\AI\Platform\Bridge\EdenAi\ErrorHandlingTrait;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Uploads binary files to Eden AI through POST /v3/upload and returns the resulting
 * file ID, which can then be referenced as "file" input of the universal-ai endpoints.
 *
 * Every binary input of the bridge goes through here, so upload failures - a rejected
 * media type, an oversized payload, an expired key - are mapped onto platform exceptions
 * rather than escaping as HttpClient ones.
 *
 * Uploads are retained by Eden AI for 30 days by default; $expiresInDays shortens that.
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class FileUploader
{
    use ErrorHandlingTrait;

    private const MAX_EXPIRY_DAYS = 30;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $baseUrl,
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly ?int $expiresInDays = null,
    ) {
        if (null !== $expiresInDays && ($expiresInDays < 1 || $expiresInDays > self::MAX_EXPIRY_DAYS)) {
            throw new RuntimeException(\sprintf('Eden AI keeps uploads for 1 to %d days, "%d" given.', self::MAX_EXPIRY_DAYS, $expiresInDays));
        }
    }

    public function upload(string $content, ?string $filename, string $mimeType): string
    {
        $fields = ['file' => new DataPart($content, $filename ?? 'file', $mimeType)];

        if (null !== $this->expiresInDays) {
            $fields['expires_in_days'] = (string) $this->expiresInDays;
        }

        $formData = new FormDataPart($fields);

        try {
            $response = $this->httpClient->request('POST', $this->baseUrl.'/v3/upload', [
                'auth_bearer' => $this->apiKey,
                'headers' => $formData->getPreparedHeaders()->toArray(),
                'body' => $formData->bodyToIterable(),
            ]);

            $this->assertSuccessfulResponse($response);

            $data = $response->toArray(false);
        } catch (DecodingExceptionInterface|TransportExceptionInterface $e) {
            throw new RuntimeException('Eden AI file upload failed.', 0, $e);
        }

        if (!isset($data['file_id']) || !\is_string($data['file_id'])) {
            throw new RuntimeException('Eden AI file upload response does not contain a file_id.');
        }

        return $data['file_id'];
    }
}
