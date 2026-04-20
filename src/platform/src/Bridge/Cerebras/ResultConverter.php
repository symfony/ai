<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cerebras;

use Symfony\AI\Platform\Bridge\Generic\Completions\CompletionsConversionTrait;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model as BaseModel;
use Symfony\AI\Platform\Result\ChoiceResult;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

/**
 * @author Junaid Farooq <ulislam.junaid125@gmail.com>
 */
final class ResultConverter implements ResultConverterInterface
{
    use CompletionsConversionTrait;
    use HttpStatusErrorHandlingTrait;

    public function supports(BaseModel $model): bool
    {
        return $model instanceof Model;
    }

    public function convert(RawResultInterface|RawHttpResult $result, array $options = []): ResultInterface
    {
        if ($result instanceof RawHttpResult) {
            $this->throwOnHttpError($result->getObject());
        }

        if ($options['stream'] ?? false) {
            return new StreamResult($this->convertStream($result));
        }

        $data = $result->getData();

        if (isset($data['type'], $data['message']) && str_ends_with($data['type'], 'error')) {
            throw new RuntimeException(\sprintf('Cerebras API error: "%s"', $data['message']));
        }

        if (!isset($data['choices'][0])) {
            throw new RuntimeException('Response does not contain output.');
        }

        $choices = array_map($this->convertChoice(...), $data['choices']);

        return 1 === \count($choices) ? $choices[0] : new ChoiceResult($choices);
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }
}
