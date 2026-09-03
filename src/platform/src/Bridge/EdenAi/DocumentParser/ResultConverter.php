<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\EdenAi\DocumentParser;

use Symfony\AI\Platform\Bridge\EdenAi\DocumentParser;
use Symfony\AI\Platform\Bridge\EdenAi\DocumentParser\Result\DocumentParsingResult;
use Symfony\AI\Platform\Bridge\EdenAi\ErrorHandlingTrait;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class ResultConverter implements ResultConverterInterface
{
    use ErrorHandlingTrait;

    public function supports(Model $model): bool
    {
        return $model instanceof DocumentParser;
    }

    public function convert(RawResultInterface $result, array $options = []): ObjectResult
    {
        $httpResponse = $result->getObject();

        $this->assertSuccessfulResponse($httpResponse);

        $data = $result->getData();

        if ('fail' === ($data['status'] ?? null)) {
            throw new RuntimeException(\sprintf('Eden AI request failed: "%s"', $data['error']['message'] ?? 'Unknown error'));
        }

        if (!isset($data['output']['extracted_data']) || !\is_array($data['output']['extracted_data'])) {
            throw new RuntimeException('Response does not contain extracted_data.');
        }

        return new ObjectResult(new DocumentParsingResult(
            $data['output']['extracted_data'],
            $data['provider'] ?? null,
            isset($data['cost']) ? (float) $data['cost'] : null,
            $data['original_response'] ?? null,
        ));
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }
}
