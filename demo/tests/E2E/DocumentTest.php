<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\E2E;

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverSelect;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class DocumentTest extends E2ETestCase
{
    public function testChatAboutADocumentExtractedWithOcr()
    {
        $this->visit('/document');
        $this->assertSelectorTextContains('#welcome h4', 'Chat about a Document');

        $select = new WebDriverSelect($this->client->findElement(WebDriverBy::cssSelector('#document-sample')));
        $select->selectByIndex(1);

        // Selecting a document re-renders the component, which enables the start button.
        $this->client->waitForVisibility('#chat-start:not([disabled])');
        $this->click('#chat-start');

        // The welcome screen is already hidden while the action runs, so the answer of the bot is
        // what tells that the OCR of the document is through - and that takes a while.
        $this->waitForElementCount('#chat-body .bot-message:not(.loading)');

        $this->assertSelectorTextContains('#chat-body .alert', "We're discussing:");
        $this->assertNotSame('', trim($this->waitForBotMessage()));

        $this->chat('What is this document about? Answer in one sentence.');

        $this->assertNotSame('', trim($this->waitForBotMessage(2)));

        $panel = $this->openAiPanel();

        // The OCR result is cached, so only the chat call is guaranteed to be in this profile.
        $panel->assertMetrics(platformCalls: 1, toolCalls: 0);
        $panel->assertPlatformCall('mistral-medium-latest');

        // The extracted text of the document is passed to the model as system message.
        $this->assertStringContainsString('System:', $panel->platformCalls()[0]['input']);
    }

    protected function requiredApiKeys(): array
    {
        return ['MISTRAL_API_KEY'];
    }
}
