<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\StructuredOutput\Validator;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\StructuredOutput\Validator\ViolationFormatter;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ViolationFormatterTest extends TestCase
{
    public function testFormatListsEveryViolationWithItsPropertyPath()
    {
        $violations = new ConstraintViolationList([
            new ConstraintViolation('This value should not be blank.', null, [], null, 'number', ''),
            new ConstraintViolation('This value should be positive.', null, [], null, 'lineItems[0].amount', -1),
        ]);

        $message = (new ViolationFormatter())->format($violations);

        $this->assertStringContainsString('- number: This value should not be blank.', $message);
        $this->assertStringContainsString('- lineItems[0].amount: This value should be positive.', $message);
        $this->assertStringContainsString('Answer again with the same structure', $message);
    }

    public function testFormatOmitsAnEmptyPropertyPath()
    {
        $violations = new ConstraintViolationList([
            new ConstraintViolation('The invoice is not balanced.', null, [], null, '', null),
        ]);

        $message = (new ViolationFormatter())->format($violations);

        $this->assertStringContainsString("\n- The invoice is not balanced.", $message);
    }

    public function testFormatUsesTheGivenTemplate()
    {
        $violations = new ConstraintViolationList([
            new ConstraintViolation('This value should not be blank.', null, [], null, 'number', ''),
        ]);

        $formatter = new ViolationFormatter("Corrections needed:\n{violations}");

        $this->assertSame("Corrections needed:\n- number: This value should not be blank.", $formatter->format($violations));
    }

    public function testTemplateWithoutThePlaceholderIsRejected()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The template must contain the "{violations}" placeholder, otherwise the model is not told what to fix.');

        new ViolationFormatter('Answer again, please.');
    }

    public function testFormatTranslatesATranslatableTemplate()
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects($this->once())
            ->method('trans')
            ->with('ai.fix_violations', [], 'ai', null)
            ->willReturn("Corrections needed:\n{violations}");

        $formatter = new ViolationFormatter($this->createTranslatable('ai.fix_violations', 'ai'), $translator);

        $this->assertSame("Corrections needed:\n- number: This value should not be blank.", $formatter->format($this->createViolations()));
    }

    public function testTranslatableTemplateWithoutATranslatorIsRejected()
    {
        $formatter = new ViolationFormatter($this->createTranslatable('ai.fix_violations', 'ai'));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A translatable template needs a translator, none was given to "Symfony\\AI\\Platform\\StructuredOutput\\Validator\\ViolationFormatter".');

        $formatter->format($this->createViolations());
    }

    public function testTranslatedTemplateWithoutThePlaceholderIsRejected()
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('Please answer again.');

        $formatter = new ViolationFormatter($this->createTranslatable('ai.fix_violations', 'ai'), $translator);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The template must contain the "{violations}" placeholder, otherwise the model is not told what to fix.');

        $formatter->format($this->createViolations());
    }

    private function createViolations(): ConstraintViolationList
    {
        return new ConstraintViolationList([
            new ConstraintViolation('This value should not be blank.', null, [], null, 'number', ''),
        ]);
    }

    private function createTranslatable(string $id, string $domain): TranslatableInterface
    {
        return new class($id, $domain) implements TranslatableInterface {
            public function __construct(
                private readonly string $id,
                private readonly string $domain,
            ) {
            }

            public function trans(TranslatorInterface $translator, ?string $locale = null): string
            {
                return $translator->trans($this->id, [], $this->domain, $locale);
            }
        };
    }
}
