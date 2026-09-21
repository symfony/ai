<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Jev;

use Symfony\AI\Platform\Bridge\Jev\Output\EvaluationResult;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Mathieu Ledru <matyo91@gmail.com>
 */
final class ResultConverter implements ResultConverterInterface
{
    use HttpStatusErrorHandlingTrait;

    public function supports(Model $model): bool
    {
        return $model instanceof Jev;
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        if ($result instanceof RawHttpResult) {
            $response = $result->getObject();
            $status = $response->getStatusCode();

            if ($status >= 400) {
                $message = $this->extractTypesafeMessage($response);

                if (\in_array($status, [401, 403], true)) {
                    throw new AuthenticationException($message ?? 'Unauthorized');
                }

                if (422 === $status) {
                    throw new BadRequestException($message ?? 'Unprocessable Entity');
                }

                $this->throwOnHttpError($response);

                throw new RuntimeException(\sprintf('Unexpected response code %d: "%s"', $status, $message ?? $response->getContent(false)));
            }
        }

        return new ObjectResult(EvaluationResult::fromArray($result->getData()));
    }

    public function getTokenUsageExtractor(): TokenUsageExtractorInterface
    {
        return new TokenUsageExtractor();
    }

    private function extractTypesafeMessage(ResponseInterface $response): ?string
    {
        try {
            $data = $response->toArray(false);
        } catch (DecodingExceptionInterface) {
            $content = $response->getContent(false);

            return '' !== $content ? $content : null;
        }

        $detail = $data['detail'] ?? null;
        if (\is_string($detail) && '' !== $detail) {
            return $detail;
        }

        if (\is_array($detail)) {
            if (isset($detail['message']) && \is_string($detail['message']) && '' !== $detail['message']) {
                return $detail['message'];
            }

            if (isset($detail[0]['msg']) && \is_string($detail[0]['msg'])) {
                return $detail[0]['msg'];
            }
        }

        return $data['error']['message'] ?? $data['message'] ?? null;
    }
}
