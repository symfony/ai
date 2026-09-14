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
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\UserWithGroupedConstraints;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

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

    public function testConvertPassesGroupsToValidator()
    {
        $user = new UserWithGroupedConstraints();

        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects($this->once())
            ->method('validate')
            ->with($this->identicalTo($user), null, ['strict'])
            ->willReturn(new ConstraintViolationList());

        $innerConverter = $this->createStub(ResultConverterInterface::class);
        $innerConverter->method('convert')->willReturn(new ObjectResult($user));

        $converter = new ValidatorResultConverter($innerConverter, $validator, ['strict']);

        $result = $converter->convert($this->createStub(RawResultInterface::class));
        $this->assertInstanceOf(ObjectResult::class, $result);
        $this->assertSame($user, $result->getContent());
    }

    public function testConvertValidatesOnlyConfiguredGroups()
    {
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();

        $user = new UserWithGroupedConstraints();
        $user->id = 0; // Violates Positive in the "Default" group
        $user->name = ''; // Violates NotBlank in the "strict" group

        $innerConverter = $this->createStub(ResultConverterInterface::class);
        $innerConverter->method('convert')->willReturn(new ObjectResult($user));

        $converter = new ValidatorResultConverter($innerConverter, $validator, ['strict']);

        try {
            $converter->convert($this->createStub(RawResultInterface::class));
            $this->fail('Expected a ValidationException to be thrown.');
        } catch (ValidationException $e) {
            $violations = $e->getViolations();
            $this->assertInstanceOf(ConstraintViolationListInterface::class, $violations);
            $this->assertCount(1, $violations);
            $this->assertSame('name', $violations->get(0)->getPropertyPath());
        }
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
