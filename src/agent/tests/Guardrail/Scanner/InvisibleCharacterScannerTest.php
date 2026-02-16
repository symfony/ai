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
use Symfony\AI\Agent\Guardrail\Scanner\InvisibleCharacterScanner;
use Symfony\AI\Agent\Input;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;

final class InvisibleCharacterScannerTest extends TestCase
{
    public function testDetectsZeroWidthSpace()
    {
        $scanner = new InvisibleCharacterScanner();
        $input = new Input('gpt-4', new MessageBag(
            Message::ofUser("Hello\u{200B}World"),
        ));

        $result = $scanner->validateInput($input);

        $this->assertTrue($result->isTriggered());
        $this->assertSame('invisible_characters', $result->getScanner());
        $this->assertStringContainsString('1 invisible character(s)', $result->getReason());
    }

    public function testDetectsZeroWidthJoiner()
    {
        $scanner = new InvisibleCharacterScanner();
        $input = new Input('gpt-4', new MessageBag(
            Message::ofUser("Test\u{200D}input"),
        ));

        $result = $scanner->validateInput($input);

        $this->assertTrue($result->isTriggered());
    }

    public function testDetectsByteOrderMark()
    {
        $scanner = new InvisibleCharacterScanner();
        $input = new Input('gpt-4', new MessageBag(
            Message::ofUser("\u{FEFF}Hidden BOM"),
        ));

        $result = $scanner->validateInput($input);

        $this->assertTrue($result->isTriggered());
    }

    public function testDetectsSoftHyphen()
    {
        $scanner = new InvisibleCharacterScanner();
        $input = new Input('gpt-4', new MessageBag(
            Message::ofUser("Soft\u{00AD}Hyphen"),
        ));

        $result = $scanner->validateInput($input);

        $this->assertTrue($result->isTriggered());
    }

    public function testDetectsBidiOverrideCharacters()
    {
        $scanner = new InvisibleCharacterScanner();
        $input = new Input('gpt-4', new MessageBag(
            Message::ofUser("Bidi\u{202E}Override"),
        ));

        $result = $scanner->validateInput($input);

        $this->assertTrue($result->isTriggered());
    }

    public function testAllowsCleanInput()
    {
        $scanner = new InvisibleCharacterScanner();
        $input = new Input('gpt-4', new MessageBag(
            Message::ofUser('Normal text without invisible characters'),
        ));

        $result = $scanner->validateInput($input);

        $this->assertFalse($result->isTriggered());
    }

    public function testMaxAllowedThreshold()
    {
        $scanner = new InvisibleCharacterScanner(maxAllowed: 2);

        $inputBelow = new Input('gpt-4', new MessageBag(
            Message::ofUser("One\u{200B}Two\u{200B}"),
        ));
        $this->assertFalse($scanner->validateInput($inputBelow)->isTriggered());

        $inputAbove = new Input('gpt-4', new MessageBag(
            Message::ofUser("One\u{200B}Two\u{200B}Three\u{200B}"),
        ));
        $this->assertTrue($scanner->validateInput($inputAbove)->isTriggered());
    }

    public function testScoreScalesWithCount()
    {
        $scanner = new InvisibleCharacterScanner();
        $input = new Input('gpt-4', new MessageBag(
            Message::ofUser("A\u{200B}B\u{200C}C\u{200D}"),
        ));

        $result = $scanner->validateInput($input);

        $this->assertTrue($result->isTriggered());
        $this->assertSame(0.3, $result->getScore());
    }

    public function testSkipsNonUserMessages()
    {
        $scanner = new InvisibleCharacterScanner();
        $input = new Input('gpt-4', new MessageBag(
            Message::forSystem("System\u{200B}Message"),
        ));

        $result = $scanner->validateInput($input);

        $this->assertFalse($result->isTriggered());
    }

    public function testSkipsUserMessageWithoutTextContent()
    {
        $scanner = new InvisibleCharacterScanner();
        $input = new Input('gpt-4', new MessageBag(
            new UserMessage(new Image('data', 'image/png')),
        ));

        $result = $scanner->validateInput($input);

        $this->assertFalse($result->isTriggered());
    }
}
