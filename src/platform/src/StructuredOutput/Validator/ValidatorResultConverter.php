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
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 *
 * @author Valtteri R <valtzu@gmail.com>
 */
final class ValidatorResultConverter implements ResultConverterInterface
{
    public function __construct(
        private readonly ResultConverterInterface $innerConverter,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function supports(Model $model): bool
    {
        return $this->innerConverter->supports($model);
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        $innerResult = $this->innerConverter->convert($result, $options);

        if (!$innerResult instanceof ObjectResult) {
            return $innerResult;
        }

        $structure = $innerResult->getContent();
        $violations = $this->validator->validate($structure);

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
