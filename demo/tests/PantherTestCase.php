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

use PHPUnit\Framework\Attributes\CoversNothing;

if (!class_exists(\Symfony\Component\Panther\PantherTestCase::class)) {
    // Panther is not installed, create a dummy base class
    abstract class PantherTestCase extends \PHPUnit\Framework\TestCase
    {
        protected static function createPantherClient(array $options = [], array $kernelOptions = []): void
        {
            self::markTestSkipped('Panther is not installed. Install it with: composer require --dev symfony/panther');
        }

        protected static function getPantherOptions(): array
        {
            return [];
        }

        protected function requiresOpenAiKey(): void
        {
            self::markTestSkipped('Panther is not installed.');
        }

        protected function requiresHuggingFaceToken(): void
        {
            self::markTestSkipped('Panther is not installed.');
        }

        protected function waitForElement(string $selector, int $timeoutInSeconds = 30): void
        {
            self::markTestSkipped('Panther is not installed.');
        }

        protected function waitForText(string $text, int $timeoutInSeconds = 30): void
        {
            self::markTestSkipped('Panther is not installed.');
        }
    }
} else {
    /**
     * Base class for Panther integration tests.
     */
    #[CoversNothing]
    abstract class PantherTestCase extends \Symfony\Component\Panther\PantherTestCase
    {
        protected static function getPantherOptions(): array
        {
            $options = parent::getPantherOptions();
            
            // Increase timeout for AI operations which can take longer
            $options['connection_timeout_in_ms'] = 60000;
            $options['request_timeout_in_ms'] = 60000;

            return $options;
        }

        /**
         * Skip test if OPENAI_API_KEY is not set or is a dummy value.
         */
        protected function requiresOpenAiKey(): void
        {
            $apiKey = $_ENV['OPENAI_API_KEY'] ?? $_SERVER['OPENAI_API_KEY'] ?? null;
            
            if (!$apiKey || str_starts_with($apiKey, 'sk-proj-testing')) {
                $this->markTestSkipped('OpenAI API key required. Set OPENAI_API_KEY environment variable.');
            }
        }

        /**
         * Skip test if HUGGINGFACE_API_TOKEN is not set or is a dummy value.
         */
        protected function requiresHuggingFaceToken(): void
        {
            $token = $_ENV['HUGGINGFACE_API_TOKEN'] ?? $_SERVER['HUGGINGFACE_API_TOKEN'] ?? null;
            
            if (!$token || str_starts_with($token, 'hf_testing')) {
                $this->markTestSkipped('HuggingFace API token required. Set HUGGINGFACE_API_TOKEN environment variable.');
            }
        }

        /**
         * Wait for an element to be present and visible.
         */
        protected function waitForElement(string $selector, int $timeoutInSeconds = 30): void
        {
            $client = static::$pantherClient ?? static::createPantherClient();
            $client->waitFor($selector, $timeoutInSeconds);
        }

        /**
         * Wait for text to appear on the page.
         */
        protected function waitForText(string $text, int $timeoutInSeconds = 30): void
        {
            $client = static::$pantherClient ?? static::createPantherClient();
            $client->waitForVisibility("//body[contains(., '{$text}')]", $timeoutInSeconds);
        }
    }
}
