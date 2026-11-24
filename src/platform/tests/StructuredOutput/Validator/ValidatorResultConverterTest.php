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
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ThinkingResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\StructuredOutput\Validator\ValidatorResultConverter;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\UserWithConstraints;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\UserWithValidationGroups;
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

    public function testConvertValidatesTheObjectOfAMultiPartResult()
    {
        $innerConverter = $this->createMock(ResultConverterInterface::class);
        $innerConverter->method('convert')->willReturn(new MultiPartResult([
            new ThinkingResult('The user wants a user object.'),
            new ObjectResult(new UserWithConstraints()),
        ]));

        $converter = new ValidatorResultConverter($innerConverter, $this->createValidator());

        $this->expectException(ValidationException::class);

        $converter->convert($this->createMock(RawResultInterface::class));
    }

    public function testConvertOnlyChecksTheGivenGroups()
    {
        $user = new UserWithValidationGroups();
        $user->age = 1;

        $innerConverter = $this->createMock(ResultConverterInterface::class);
        $innerConverter->method('convert')->willReturn(new ObjectResult($user));

        $converter = new ValidatorResultConverter($innerConverter, $this->createValidator(), ['strict']);

        $this->assertSame($user, $converter->convert($this->createMock(RawResultInterface::class))->getContent());
    }

    public function testConvertHandsTheViolationsToTheRetry()
    {
        $invalidUser = new UserWithConstraints();
        $correctedUser = new UserWithConstraints();
        $correctedUser->id = 1;
        $correctedUser->name = 'Alice';

        $innerConverter = $this->createMock(ResultConverterInterface::class);
        $innerConverter->method('convert')->willReturn(new ObjectResult($invalidUser, '{"id":0,"name":""}'));

        $receivedViolations = null;
        $receivedText = null;
        $converter = new ValidatorResultConverter(
            $innerConverter,
            $this->createValidator(),
            null,
            static function (ConstraintViolationListInterface $violations, ?string $text) use (&$receivedViolations, &$receivedText, $correctedUser): ObjectResult {
                $receivedViolations = $violations;
                $receivedText = $text;

                return new ObjectResult($correctedUser);
            },
        );

        $result = $converter->convert($this->createMock(RawResultInterface::class));

        $this->assertSame($correctedUser, $result->getContent());
        $this->assertSame('{"id":0,"name":""}', $receivedText);
        $this->assertCount(2, $receivedViolations);
    }

    public function testSupportsDelegatesToInnerConverter()
    {
        $model = new Model('gpt-4o');
        $innerConverter = $this->createMock(ResultConverterInterface::class);
        $innerConverter->method('supports')->with($model)->willReturn(true);

        $converter = new ValidatorResultConverter($innerConverter, Validation::createValidator());

        $this->assertTrue($converter->supports($model));
    }

    private function createValidator(): ValidatorInterface
    {
        return Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }
}
