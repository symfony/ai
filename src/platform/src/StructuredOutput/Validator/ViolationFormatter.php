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

use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ViolationFormatter implements ViolationFormatterInterface
{
    public const VIOLATIONS_PLACEHOLDER = '{violations}';

    public const DEFAULT_TEMPLATE = <<<'TEXT'
        Your previous answer did not pass validation:
        {violations}

        Answer again with the same structure and fix all of the problems listed above.
        TEXT;

    public function __construct(
        private readonly string|TranslatableInterface $template = self::DEFAULT_TEMPLATE,
        private readonly ?TranslatorInterface $translator = null,
    ) {
        if (\is_string($this->template)) {
            $this->assertCarriesViolations($this->template);
        }
    }

    public function format(ConstraintViolationListInterface $violations): string
    {
        $lines = [];

        foreach ($violations as $violation) {
            $propertyPath = $violation->getPropertyPath();
            $message = (string) $violation->getMessage();

            $lines[] = '' === $propertyPath ? '- '.$message : \sprintf('- %s: %s', $propertyPath, $message);
        }

        return strtr($this->resolveTemplate(), [self::VIOLATIONS_PLACEHOLDER => implode("\n", $lines)]);
    }

    private function resolveTemplate(): string
    {
        if (\is_string($this->template)) {
            return $this->template;
        }

        if (null === $this->translator) {
            throw new InvalidArgumentException(\sprintf('A translatable template needs a translator, none was given to "%s".', self::class));
        }

        $template = $this->template->trans($this->translator);
        $this->assertCarriesViolations($template);

        return $template;
    }

    private function assertCarriesViolations(string $template): void
    {
        if (!str_contains($template, self::VIOLATIONS_PLACEHOLDER)) {
            throw new InvalidArgumentException(\sprintf('The template must contain the "%s" placeholder, otherwise the model is not told what to fix.', self::VIOLATIONS_PLACEHOLDER));
        }
    }
}
