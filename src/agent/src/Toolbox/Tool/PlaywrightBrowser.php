<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox\Tool;

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * @author Mathieu Ledru <matyo91@gmail.com>
 */
#[AsTool('navigate_browser', 'Tool for navigating a browser to a URL')]
#[AsTool('extract_text', 'Tool for extracting all text from the current webpage', method: 'extractText')]
#[AsTool('click_element', 'Tool for clicking on an element on the webpage', method: 'clickElement')]
#[AsTool('take_screenshot', 'Tool for taking a screenshot of the current page', method: 'takeScreenshot')]
final readonly class PlaywrightBrowser
{
    public function __construct(
        private bool $allowDangerousNavigation = false,
        private string $browserType = 'chromium',
        private bool $headless = true,
        private string $outputDir = '/tmp',
    ) {
        if (!$this->allowDangerousNavigation) {
            throw new \InvalidArgumentException('You must set allowDangerousNavigation to true to use this tool. Browser automation can be dangerous and can lead to security vulnerabilities. This tool can navigate to any URL, including internal network URLs.');
        }
    }

    /**
     * Navigate a browser to the specified URL.
     *
     * @param string $url The URL to navigate to
     */
    public function __invoke(string $url): string
    {
        try {
            // Validate URL scheme
            $parsedUrl = parse_url($url);
            if (!$parsedUrl || !isset($parsedUrl['scheme']) || !\in_array($parsedUrl['scheme'], ['http', 'https'])) {
                return 'Error: URL scheme must be http or https';
            }

            // Create a simple navigation script
            $script = $this->createNavigationScript($url);
            $scriptPath = $this->outputDir.'/navigate_'.uniqid().'.js';

            file_put_contents($scriptPath, $script);

            // Execute the Playwright script using exec
            $command = 'npx playwright test '.escapeshellarg($scriptPath).' 2>&1';
            $output = [];
            $returnCode = 0;
            exec($command, $output, $returnCode);

            // Clean up
            unlink($scriptPath);

            if (0 === $returnCode) {
                return "Successfully navigated to {$url}";
            } else {
                return 'Navigation failed: '.implode("\n", $output);
            }
        } catch (\Exception $e) {
            return 'Error navigating to URL: '.$e->getMessage();
        }
    }

    /**
     * Extract all text from the current webpage.
     *
     * @param string $url The URL to extract text from
     */
    public function extractText(string $url): string
    {
        try {
            $script = $this->createTextExtractionScript($url);
            $scriptPath = $this->outputDir.'/extract_'.uniqid().'.js';

            file_put_contents($scriptPath, $script);

            $command = 'npx playwright test '.escapeshellarg($scriptPath).' 2>&1';
            $output = [];
            $returnCode = 0;
            exec($command, $output, $returnCode);

            unlink($scriptPath);

            if (0 === $returnCode) {
                $outputString = implode("\n", $output);
                // Extract text content from the output
                if (preg_match('/TEXT_CONTENT_START(.*?)TEXT_CONTENT_END/s', $outputString, $matches)) {
                    return trim($matches[1]);
                }

                return 'No text content found';
            } else {
                return 'Text extraction failed: '.implode("\n", $output);
            }
        } catch (\Exception $e) {
            return 'Error extracting text: '.$e->getMessage();
        }
    }

    /**
     * Click on an element on the webpage.
     *
     * @param string $url      The URL of the page
     * @param string $selector CSS selector for the element to click
     */
    public function clickElement(string $url, string $selector): string
    {
        try {
            $script = $this->createClickScript($url, $selector);
            $scriptPath = $this->outputDir.'/click_'.uniqid().'.js';

            file_put_contents($scriptPath, $script);

            $command = 'npx playwright test '.escapeshellarg($scriptPath).' 2>&1';
            $output = [];
            $returnCode = 0;
            exec($command, $output, $returnCode);

            unlink($scriptPath);

            if (0 === $returnCode) {
                return "Successfully clicked element: {$selector}";
            } else {
                return 'Click failed: '.implode("\n", $output);
            }
        } catch (\Exception $e) {
            return 'Error clicking element: '.$e->getMessage();
        }
    }

    /**
     * Take a screenshot of the current page.
     *
     * @param string $url The URL to screenshot
     */
    public function takeScreenshot(string $url): string
    {
        try {
            $screenshotPath = $this->outputDir.'/screenshot_'.uniqid().'.png';
            $script = $this->createScreenshotScript($url, $screenshotPath);
            $scriptPath = $this->outputDir.'/screenshot_'.uniqid().'.js';

            file_put_contents($scriptPath, $script);

            $command = 'npx playwright test '.escapeshellarg($scriptPath).' 2>&1';
            $output = [];
            $returnCode = 0;
            exec($command, $output, $returnCode);

            unlink($scriptPath);

            if (0 === $returnCode && file_exists($screenshotPath)) {
                return "Screenshot saved to: {$screenshotPath}";
            } else {
                return 'Screenshot failed: '.implode("\n", $output);
            }
        } catch (\Exception $e) {
            return 'Error taking screenshot: '.$e->getMessage();
        }
    }

    /**
     * Create navigation script.
     */
    private function createNavigationScript(string $url): string
    {
        return "
const { test, expect } = require('@playwright/test');

test('navigate to {$url}', async ({ page }) => {
  const response = await page.goto('{$url}');
  console.log('Navigation status:', response?.status());
});
";
    }

    /**
     * Create text extraction script.
     */
    private function createTextExtractionScript(string $url): string
    {
        return "
const { test, expect } = require('@playwright/test');

test('extract text from {$url}', async ({ page }) => {
  await page.goto('{$url}');
  const text = await page.textContent('body');
  console.log('TEXT_CONTENT_START');
  console.log(text);
  console.log('TEXT_CONTENT_END');
});
";
    }

    /**
     * Create click script.
     */
    private function createClickScript(string $url, string $selector): string
    {
        return "
const { test, expect } = require('@playwright/test');

test('click element on {$url}', async ({ page }) => {
  await page.goto('{$url}');
  await page.click('{$selector}');
  console.log('Element clicked successfully');
});
";
    }

    /**
     * Create screenshot script.
     */
    private function createScreenshotScript(string $url, string $screenshotPath): string
    {
        return "
const { test, expect } = require('@playwright/test');

test('take screenshot of {$url}', async ({ page }) => {
  await page.goto('{$url}');
  await page.screenshot({ path: '{$screenshotPath}' });
  console.log('Screenshot taken');
});
";
    }
}
