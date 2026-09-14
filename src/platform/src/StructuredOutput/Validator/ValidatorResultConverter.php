<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\StructuredOutput\Validator;

use Symfony\AI\Platform\Exception\ValidationException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\StructuredOutput\Streaming\PartialObjectStreamListener;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\Component\Validator\Constraints\GroupSequence;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 *
 * @author Valtteri R <valtzu@gmail.com>
 */
final class ValidatorResultConverter implements ResultConverterInterface
{
    /**
     * @param string|GroupSequence|array<string|GroupSequence>|null $groups The validation groups to validate the structured output in, or null for the validator's default group
     */
    public function __construct(
        private readonly ResultConverterInterface $innerConverter,
        private readonly ValidatorInterface $validator,
        private readonly string|GroupSequence|array|null $groups = null,
    ) {
    }

    public function supports(Model $model): bool
    {
        return $this->innerConverter->supports($model);
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        $innerResult = $this->innerConverter->convert($result, $options);

        if ($innerResult instanceof StreamResult) {
            foreach ($innerResult->getListeners() as $listener) {
                if ($listener instanceof PartialObjectStreamListener) {
                    $listener->setValidator($this->validator, $this->groups);
                }
            }

            return $innerResult;
        }

        if (!$innerResult instanceof ObjectResult) {
            return $innerResult;
        }

        $structure = $innerResult->getContent();
        $violations = $this->validator->validate($structure, null, $this->groups);

        if (0 !== \count($violations)) {
            throw new ValidationException($violations);
        }

        return $innerResult;
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return $this->innerConverter->getTokenUsageExtractor();
    }
}
