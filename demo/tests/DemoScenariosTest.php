<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests;

/**
 * Tests the demo application scenarios using a real browser.
 * 
 * These tests require API keys and are intended to be run with a label
 * similar to the run-examples workflow.
 */
final class DemoScenariosTest extends PantherTestCase
{
    public function testIndexPageLoadsSuccessfully()
    {
        $client = static::createPantherClient();
        $client->request('GET', '/');

        $this->assertSelectorTextContains('h1', 'Welcome to the Symfony AI Demo');
        $this->assertSelectorCount(8, '.card');
        
        // Verify all scenario cards are present
        $this->assertSelectorTextContains('.card.youtube .card-title', 'YouTube Transcript Bot');
        $this->assertSelectorTextContains('.card.recipe .card-title', 'Recipe Bot');
        $this->assertSelectorTextContains('.card.wikipedia .card-title', 'Wikipedia Research Bot');
        $this->assertSelectorTextContains('.card.blog .card-title', 'Symfony Blog Bot');
        $this->assertSelectorTextContains('.card.speech .card-title', 'Speech Bot');
        $this->assertSelectorTextContains('.card.video .card-title', 'Video Bot');
        $this->assertSelectorTextContains('.card.crop .card-title', 'Smart Image Cropping');
        $this->assertSelectorTextContains('.card.stream .card-title', 'Turbo Stream Bot');
    }

    public function testBlogScenarioPageLoads()
    {
        $this->requiresOpenAiKey();
        
        $client = static::createPantherClient();
        $client->request('GET', '/blog');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h4', 'Retrieval Augmented Generation based on the Symfony blog');
        $this->assertSelectorExists('#chat-submit');
        $this->assertSelectorExists('textarea[name="userMessage"]');
    }

    public function testRecipeScenarioPageLoads()
    {
        $this->requiresOpenAiKey();
        
        $client = static::createPantherClient();
        $client->request('GET', '/recipe');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h4', 'Cooking Recipes');
        $this->assertSelectorExists('#chat-submit');
        $this->assertSelectorExists('textarea[name="userMessage"]');
    }

    public function testWikipediaScenarioPageLoads()
    {
        $this->requiresOpenAiKey();
        
        $client = static::createPantherClient();
        $client->request('GET', '/wikipedia');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h4', 'Wikipedia Research');
        $this->assertSelectorExists('#chat-submit');
        $this->assertSelectorExists('textarea[name="userMessage"]');
    }

    public function testYoutubeScenarioPageLoads()
    {
        $this->requiresOpenAiKey();
        
        $client = static::createPantherClient();
        $client->request('GET', '/youtube');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h4', 'Chat about a YouTube Video');
        $this->assertSelectorExists('#chat-submit');
        $this->assertSelectorExists('input[name="videoId"]');
        $this->assertSelectorExists('textarea[name="userMessage"]');
    }

    public function testSpeechScenarioPageLoads()
    {
        $this->requiresOpenAiKey();
        
        $client = static::createPantherClient();
        $client->request('GET', '/speech');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('button'); // Audio recording interface
        $this->assertSelectorExists('#chat-submit');
    }

    public function testVideoScenarioPageLoads()
    {
        $this->requiresOpenAiKey();
        
        $client = static::createPantherClient();
        $client->request('GET', '/video');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('video'); // Webcam video element
    }

    public function testCropScenarioPageLoads()
    {
        $this->requiresOpenAiKey();
        
        $client = static::createPantherClient();
        $client->request('GET', '/crop');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
    }

    public function testStreamScenarioPageLoads()
    {
        $this->requiresOpenAiKey();
        
        $client = static::createPantherClient();
        $client->request('GET', '/stream');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('#chat-submit');
        $this->assertSelectorExists('textarea[name="userMessage"]');
    }

    /**
     * Test the blog scenario with a real interaction.
     * This sends a message and waits for a response from the AI.
     */
    public function testBlogScenarioInteraction()
    {
        $this->requiresOpenAiKey();
        
        $client = static::createPantherClient();
        $crawler = $client->request('GET', '/blog');

        // Find the message textarea and submit button
        $textarea = $crawler->filter('textarea[name="userMessage"]');
        $submitButton = $crawler->filter('#chat-submit');

        // Type a message
        $textarea->sendKeys('What is Symfony?');
        
        // Click submit
        $submitButton->click();

        // Wait for AI response (increase timeout for AI operations)
        $this->waitForElement('.message.assistant', 60);
        
        // Verify response exists
        $this->assertSelectorExists('.message.assistant');
    }

    /**
     * Test the recipe scenario with a real interaction.
     * This tests structured output functionality.
     */
    public function testRecipeScenarioInteraction()
    {
        $this->requiresOpenAiKey();
        
        $client = static::createPantherClient();
        $crawler = $client->request('GET', '/recipe');

        // Find the message textarea and submit button
        $textarea = $crawler->filter('textarea[name="userMessage"]');
        $submitButton = $crawler->filter('#chat-submit');

        // Type a message requesting a simple recipe
        $textarea->sendKeys('Give me a simple pasta recipe');
        
        // Click submit
        $submitButton->click();

        // Wait for AI response with recipe
        $this->waitForElement('.recipe', 60);
        
        // Verify recipe structure exists
        $this->assertSelectorExists('.recipe');
    }

    /**
     * Test the Wikipedia scenario with a real interaction.
     * This tests agent tool usage.
     */
    public function testWikipediaScenarioInteraction()
    {
        $this->requiresOpenAiKey();
        
        $client = static::createPantherClient();
        $crawler = $client->request('GET', '/wikipedia');

        // Find the message textarea and submit button
        $textarea = $crawler->filter('textarea[name="userMessage"]');
        $submitButton = $crawler->filter('#chat-submit');

        // Ask a question that requires Wikipedia lookup
        $textarea->sendKeys('Tell me about PHP programming language');
        
        // Click submit
        $submitButton->click();

        // Wait for AI response (this may take longer due to tool calls)
        $this->waitForElement('.message.assistant', 90);
        
        // Verify response exists
        $this->assertSelectorExists('.message.assistant');
    }

    /**
     * Test the YouTube scenario with a real interaction.
     */
    public function testYoutubeScenarioInteraction()
    {
        $this->requiresOpenAiKey();
        
        $client = static::createPantherClient();
        $crawler = $client->request('GET', '/youtube');

        // Use a known Symfony video ID
        $videoIdInput = $crawler->filter('input[name="videoId"]');
        $videoIdInput->sendKeys('dQw4w9WgXcQ'); // Example video ID
        
        $textarea = $crawler->filter('textarea[name="userMessage"]');
        $submitButton = $crawler->filter('#chat-submit');

        // Ask a question about the video
        $textarea->sendKeys('What is this video about?');
        
        // Click submit
        $submitButton->click();

        // Wait for AI response
        $this->waitForElement('.message.assistant', 90);
        
        // Verify response exists
        $this->assertSelectorExists('.message.assistant');
    }

    /**
     * Test the stream scenario interaction.
     * This tests streaming response functionality.
     */
    public function testStreamScenarioInteraction()
    {
        $this->requiresOpenAiKey();
        
        $client = static::createPantherClient();
        $crawler = $client->request('GET', '/stream');

        // Find the message textarea and submit button
        $textarea = $crawler->filter('textarea[name="userMessage"]');
        $submitButton = $crawler->filter('#chat-submit');

        // Type a message
        $textarea->sendKeys('Write a short story about Symfony');
        
        // Click submit
        $submitButton->click();

        // Wait for streaming response to start
        $this->waitForElement('.message.assistant', 60);
        
        // Verify response exists
        $this->assertSelectorExists('.message.assistant');
    }
}
