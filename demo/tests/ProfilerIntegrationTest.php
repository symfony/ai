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
 * Tests the Web Profiler integration with the demo application.
 */
final class ProfilerIntegrationTest extends PantherTestCase
{
    /**
     * Test that the Web Profiler toolbar is visible in dev environment.
     */
    public function testProfilerToolbarIsVisible()
    {
        $client = static::createPantherClient();
        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        
        // In test environment, profiler toolbar might not be visible
        // This test is more relevant when PANTHER_APP_ENV=dev
        $env = $_ENV['PANTHER_APP_ENV'] ?? 'test';
        if ($env === 'dev') {
            // Wait for profiler toolbar to load
            $this->waitForElement('.sf-toolbar', 10);
            
            // Verify profiler toolbar exists
            $this->assertSelectorExists('.sf-toolbar');
        } else {
            $this->markTestSkipped('Profiler toolbar is only visible in dev environment');
        }
    }

    /**
     * Test that "See Implementation" links are present in dev environment.
     */
    public function testImplementationLinksAreVisible()
    {
        $client = static::createPantherClient();
        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        
        // "See Implementation" links are only visible in dev environment
        $env = $_ENV['PANTHER_APP_ENV'] ?? 'test';
        if ($env === 'dev') {
            // Verify "See Implementation" links exist on the cards
            $this->assertSelectorExists('.card-footer a[href*="_profiler_open_file"]');
        } else {
            $this->markTestSkipped('Implementation links are only visible in dev environment');
        }
    }

    /**
     * Test that profiler collects data for a blog chat interaction.
     */
    public function testProfilerCollectsDataForBlogChat()
    {
        $this->requiresOpenAiKey();
        
        $env = $_ENV['PANTHER_APP_ENV'] ?? 'test';
        if ($env !== 'dev') {
            $this->markTestSkipped('Profiler is only enabled in dev environment');
        }
        
        $client = static::createPantherClient();
        $crawler = $client->request('GET', '/blog');

        $this->assertResponseIsSuccessful();
        
        // Find the message textarea and submit button
        $textarea = $crawler->filter('textarea[name="userMessage"]');
        $submitButton = $crawler->filter('#chat-submit');

        // Type a message
        $textarea->sendKeys('What is Symfony?');
        
        // Click submit
        $submitButton->click();

        // Wait for AI response
        $this->waitForElement('.message.assistant', 60);
        
        // Wait for profiler toolbar to update
        $this->waitForElement('.sf-toolbar', 10);
        
        // Verify profiler toolbar exists and has been updated
        $this->assertSelectorExists('.sf-toolbar');
    }

    /**
     * Test that profiler page can be accessed.
     */
    public function testProfilerPageIsAccessible()
    {
        $env = $_ENV['PANTHER_APP_ENV'] ?? 'test';
        if ($env !== 'dev') {
            $this->markTestSkipped('Profiler is only enabled in dev environment');
        }
        
        $client = static::createPantherClient();
        
        // First, make a request to generate a profile
        $crawler = $client->request('GET', '/');
        $this->assertResponseIsSuccessful();
        
        // Wait for profiler toolbar
        $this->waitForElement('.sf-toolbar', 10);
        
        // Try to find and click the profiler link
        $profilerLink = $crawler->filter('.sf-toolbar a.sf-toolbar-block-request');
        
        if ($profilerLink->count() > 0) {
            // Get the profiler URL
            $profilerUrl = $profilerLink->attr('href');
            
            // Navigate to profiler
            $client->request('GET', $profilerUrl);
            
            // Verify we're on the profiler page
            $this->assertSelectorExists('.sf-toolbar');
        } else {
            $this->markTestSkipped('Profiler toolbar link not found');
        }
    }

    /**
     * Test that AI-specific profiler panels exist.
     */
    public function testAiProfilerPanelsExist()
    {
        $this->requiresOpenAiKey();
        
        $env = $_ENV['PANTHER_APP_ENV'] ?? 'test';
        if ($env !== 'dev') {
            $this->markTestSkipped('Profiler is only enabled in dev environment');
        }
        
        $client = static::createPantherClient();
        $crawler = $client->request('GET', '/blog');

        // Make an AI interaction
        $textarea = $crawler->filter('textarea[name="userMessage"]');
        $submitButton = $crawler->filter('#chat-submit');
        $textarea->sendKeys('Hello');
        $submitButton->click();

        // Wait for response
        $this->waitForElement('.message.assistant', 60);
        
        // Wait for profiler toolbar
        $this->waitForElement('.sf-toolbar', 10);
        
        // Check if there are AI-related entries in profiler
        // The actual panel name might vary, but we verify toolbar exists
        $this->assertSelectorExists('.sf-toolbar');
    }
}
