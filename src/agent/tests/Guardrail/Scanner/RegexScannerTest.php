<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Guardrail\Scanner;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Exception\InvalidArgumentException;
use Symfony\AI\Agent\Guardrail\Scanner\RegexScanner;
use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\Output;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Result\ResultInterface;

final class RegexScannerTest extends TestCase
{
    public function testDetectsMatchingInputPattern()
    {
        $scanner = new RegexScanner(['/password\s*[:=]/i']);
        $input = new Input('gpt-4', new MessageBag(
            Message::ofUser('My password: secret123'),
        ));

        $result = $scanner->validateInput($input);

        $this->assertTrue($result->isTriggered());
        $this->assertSame('regex', $result->getScanner());
        $this->assertSame('Content matched a restricted pattern.', $result->getReason());
    }

    public function testAllowsNonMatchingInput()
    {
        $scanner = new RegexScanner(['/forbidden/i']);
        $input = new Input('gpt-4', new MessageBag(
            Message::ofUser('This is perfectly fine'),
        ));

        $result = $scanner->validateInput($input);

        $this->assertFalse($result->isTriggered());
    }

    public function testDetectsMatchingOutputPattern()
    {
        $scanner = new RegexScanner(['/credit\s*card/i'], 'Sensitive data detected in output.');

        $resultInterface = $this->createMock(ResultInterface::class);
        $resultInterface->method('getContent')->willReturn('Your credit card number is 1234');

        $output = new Output('gpt-4', $resultInterface, new MessageBag());

        $result = $scanner->validateOutput($output);

        $this->assertTrue($result->isTriggered());
        $this->assertSame('Sensitive data detected in output.', $result->getReason());
    }

    public function testAllowsNonMatchingOutput()
    {
        $scanner = new RegexScanner(['/forbidden/i']);

        $resultInterface = $this->createMock(ResultInterface::class);
        $resultInterface->method('getContent')->willReturn('This is safe output');

        $output = new Output('gpt-4', $resultInterface, new MessageBag());

        $result = $scanner->validateOutput($output);

        $this->assertFalse($result->isTriggered());
    }

    public function testSkipsNonStringOutputContent()
    {
        $scanner = new RegexScanner(['/anything/i']);

        $resultInterface = $this->createMock(ResultInterface::class);
        $resultInterface->method('getContent')->willReturn(null);

        $output = new Output('gpt-4', $resultInterface, new MessageBag());

        $result = $scanner->validateOutput($output);

        $this->assertFalse($result->isTriggered());
    }

    public function testMultiplePatterns()
    {
        $scanner = new RegexScanner(['/pattern_one/i', '/pattern_two/i']);

        $input = new Input('gpt-4', new MessageBag(
            Message::ofUser('This contains pattern_two'),
        ));

        $result = $scanner->validateInput($input);

        $this->assertTrue($result->isTriggered());
    }

    public function testCustomReasonMessage()
    {
        $scanner = new RegexScanner(['/test/i'], 'Custom reason message');

        $input = new Input('gpt-4', new MessageBag(
            Message::ofUser('This is a test'),
        ));

        $result = $scanner->validateInput($input);

        $this->assertTrue($result->isTriggered());
        $this->assertSame('Custom reason message', $result->getReason());
    }

    public function testThrowsOnInvalidRegexPattern()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid regex pattern');

        new RegexScanner(['/invalid[/']);
    }

    public function testSkipsNonUserMessages()
    {
        $scanner = new RegexScanner(['/forbidden/i']);
        $input = new Input('gpt-4', new MessageBag(
            Message::forSystem('This contains forbidden word'),
        ));

        $result = $scanner->validateInput($input);

        $this->assertFalse($result->isTriggered());
    }

    public function testSkipsUserMessageWithoutTextContent()
    {
        $scanner = new RegexScanner(['/anything/i']);
        $input = new Input('gpt-4', new MessageBag(
            new UserMessage(new Image('data', 'image/png')),
        ));

        $result = $scanner->validateInput($input);

        $this->assertFalse($result->isTriggered());
    }
}
