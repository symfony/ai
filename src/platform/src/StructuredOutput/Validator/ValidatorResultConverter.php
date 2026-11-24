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
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\StructuredOutput\Streaming\PartialObjectStreamListener;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\Component\Validator\Constraints\GroupSequence;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 *
 * @author Valtteri R <valtzu@gmail.com>
 */
final class ValidatorResultConverter implements ResultConverterInterface
{
    /**
     * @param string|list<string>|GroupSequence|null                                          $groups
     * @param (\Closure(ConstraintViolationListInterface, string|null): ResultInterface)|null $retry
     */
    public function __construct(
        private readonly ResultConverterInterface $innerConverter,
        private readonly ValidatorInterface $validator,
        private readonly string|array|GroupSequence|null $groups = null,
        private readonly ?\Closure $retry = null,
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

        if (null === $objectResult = $this->findObjectResult($innerResult)) {
            return $innerResult;
        }

        $violations = $this->validator->validate($objectResult->getContent(), groups: $this->groups);

        if (0 === \count($violations)) {
            return $innerResult;
        }

        if (null === $this->retry) {
            throw new ValidationException($violations);
        }

        return ($this->retry)($violations, $objectResult->getText());
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return $this->innerConverter->getTokenUsageExtractor();
    }

    /**
     * Finds the object the same way DeferredResult::asObject() does, so reasoning models are validated too.
     */
    private function findObjectResult(ResultInterface $result): ?ObjectResult
    {
        if ($result instanceof ObjectResult) {
            return $result;
        }

        if (!$result instanceof MultiPartResult) {
            return null;
        }

        $objectResults = array_filter($result->getContent(), static fn (ResultInterface $part): bool => $part instanceof ObjectResult);

        return 1 === \count($objectResults) ? array_first($objectResults) : null;
    }
}
