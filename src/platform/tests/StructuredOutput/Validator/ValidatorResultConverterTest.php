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
use Symfony\AI\Platform\Exception\ValidationException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\StructuredOutput\Validator\ValidatorResultConverter;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\UserWithConstraints;
use Symfony\Component\Validator\Validation;

final class ValidatorResultConverterTest extends TestCase
{
    public function testConvertValidatesObject()
    {
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        $innerConverter = $this->createMock(ResultConverterInterface::class);
        $converter = new ValidatorResultConverter($innerConverter, $validator);

        $validUser = new UserWithConstraints();
        $validUser->id = 1;
        $validUser->name = 'John Doe';

        $rawResult = $this->createMock(RawResultInterface::class);
        $innerConverter->expects($this->once())
            ->method('convert')
            ->with($rawResult, [])
            ->willReturn(new ObjectResult($validUser));

        $result = $converter->convert($rawResult, []);
        $this->assertInstanceOf(ObjectResult::class, $result);
        $this->assertSame($validUser, $result->getContent());
    }

    public function testConvertThrowsOnValidationError()
    {
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        $innerConverter = $this->createMock(ResultConverterInterface::class);
        $converter = new ValidatorResultConverter($innerConverter, $validator);

        $invalidUser = new UserWithConstraints();
        $invalidUser->id = -1; // Violates Positive constraint

        $rawResult = $this->createMock(RawResultInterface::class);
        $innerConverter->method('convert')
            ->willReturn(new ObjectResult($invalidUser));

        $this->expectException(ValidationException::class);
        $converter->convert($rawResult, []);
    }

    public function testSupportsDelegatesToInnerConverter()
    {
        $model = new Model('gpt-4o');
        $innerConverter = $this->createMock(ResultConverterInterface::class);
        $innerConverter->method('supports')->with($model)->willReturn(true);

        $converter = new ValidatorResultConverter($innerConverter, Validation::createValidator());

        $this->assertTrue($converter->supports($model));
    }
}
