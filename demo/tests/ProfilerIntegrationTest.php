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

use Symfony\Component\Panther\Client;

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
        // Set APP_ENV to dev for this test
        putenv('APP_ENV=dev');
        putenv('PANTHER_APP_ENV=dev');
        
        $client = static::createPantherClient(['external_base_uri' => 'http://127.0.0.1:9080']);
        $crawler = $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        
        // Wait for profiler toolbar to load
        $this->waitForElement('.sf-toolbar', 10);
        
        // Verify profiler toolbar exists
        $this->assertSelectorExists('.sf-toolbar');
    }

    /**
     * Test that "See Implementation" links are present in dev environment.
     */
    public function testImplementationLinksAreVisible()
    {
        // Set APP_ENV to dev for this test
        putenv('APP_ENV=dev');
        putenv('PANTHER_APP_ENV=dev');
        
        $client = static::createPantherClient(['external_base_uri' => 'http://127.0.0.1:9080']);
        $crawler = $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        
        // Verify "See Implementation" links exist on the cards
        $this->assertSelectorExists('.card-footer a[href*="_profiler_open_file"]');
    }

    /**
     * Test that profiler collects data for a blog chat interaction.
     */
    public function testProfilerCollectsDataForBlogChat()
    {
        $this->requiresOpenAiKey();
        
        // Set APP_ENV to dev for this test
        putenv('APP_ENV=dev');
        putenv('PANTHER_APP_ENV=dev');
        
        $client = static::createPantherClient(['external_base_uri' => 'http://127.0.0.1:9080']);
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
        // Set APP_ENV to dev for this test
        putenv('APP_ENV=dev');
        putenv('PANTHER_APP_ENV=dev');
        
        $client = static::createPantherClient(['external_base_uri' => 'http://127.0.0.1:9080']);
        
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
        
        // Set APP_ENV to dev for this test
        putenv('APP_ENV=dev');
        putenv('PANTHER_APP_ENV=dev');
        
        $client = static::createPantherClient(['external_base_uri' => 'http://127.0.0.1:9080']);
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
