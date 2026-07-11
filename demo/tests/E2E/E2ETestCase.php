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

use Facebook\WebDriver\Exception\StaleElementReferenceException;
use Facebook\WebDriver\WebDriver;
use Facebook\WebDriver\WebDriverBy;
use PHPUnit\Framework\Attributes\Before;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\DomCrawler\Crawler;
use Symfony\Component\Panther\PantherTestCase;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Base class for the end-to-end tests of the demo use cases.
 *
 * The browser drives the application running in the `dev` environment, which means real calls to the
 * configured AI platforms - and a working web profiler, including the Symfony AI panel to assert on.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
abstract class E2ETestCase extends PantherTestCase
{
    /**
     * Waiting for the answer of a large language model takes a while, especially with tool calling
     * and reasoning models in the loop.
     */
    protected const int AI_TIMEOUT = 120;

    protected Client $client;

    private static ?string $chromeArguments = null;

    /**
     * API keys are read from `.env.local` by the web server that Panther boots in `dev` - the test
     * process itself runs in `test` and therefore only sees the dummy key from `.env.test`.
     *
     * @return list<string>
     */
    abstract protected function requiredApiKeys(): array;

    /**
     * Boots the browser and leaves it on the home page, which the tests without a `visit()` of
     * their own start from.
     */
    #[Before]
    protected function bootBrowser(): void
    {
        foreach ($this->requiredApiKeys() as $key) {
            if (!Environment::isConfigured($key)) {
                $this->markTestSkipped(\sprintf('Set %s in .env.local, or in your environment, to run this end-to-end test.', $key));
            }
        }

        self::enableFakeMediaDevices();
        Environment::expose();

        $this->client = self::createPantherClient();

        // The browser is shared by all tests of the run, so the session of the previous use case
        // needs to be dropped - otherwise its chat history would still be in the message bag.
        $this->client->request('GET', '/');
        $this->client->getWebDriver()->manage()->deleteAllCookies();
        $this->client->request('GET', '/');
    }

    /**
     * Opens one of the use cases, and makes sure its chat starts empty: the chats of the recipe and
     * the turbo stream bot are stored in the cache, which outlives the browser session.
     */
    protected function visit(string $path): void
    {
        $this->client->request('GET', $path);
        $this->client->waitForVisibility('#chat-body');

        $reset = $this->client->findElements(WebDriverBy::cssSelector('button[data-live-action-param="reset"]'));

        if ([] === $reset) {
            return;
        }

        $reset[0]->click();

        // The hidden placeholder in `#loading-message` holds messages as well, so it is left out.
        $state = implode(', ', [
            '#chat-body > div:not(#loading-message) .user-message',
            '#chat-body > div:not(#loading-message) .bot-message:not(.loading)',
            '#recipe-loader',
            '#recipe-stream-target',
            '#bot-message-streamed',
        ]);

        $this->client->wait(10)->until(
            static fn (WebDriver $driver) => [] === $driver->findElements(WebDriverBy::cssSelector($state)) ?: null,
            'Timeout waiting for the chat to be reset.'
        );
    }

    /**
     * Types a message into the chat input at the bottom of a chat component and submits it.
     */
    protected function chat(string $message, string $input = '#chat-message'): void
    {
        $this->type($input, $message);
        $this->click('.card-footer form button');
    }

    /**
     * Clicks an element, and retries once when it went stale in between - live components and turbo
     * streams replace parts of the DOM while the answer of the bot arrives.
     */
    protected function click(string $selector): void
    {
        try {
            $this->client->findElement(WebDriverBy::cssSelector($selector))->click();
        } catch (StaleElementReferenceException) {
            $this->client->findElement(WebDriverBy::cssSelector($selector))->click();
        }
    }

    protected function type(string $selector, string $text): void
    {
        try {
            $this->client->findElement(WebDriverBy::cssSelector($selector))->sendKeys($text);
        } catch (StaleElementReferenceException) {
            $this->client->findElement(WebDriverBy::cssSelector($selector))->sendKeys($text);
        }
    }

    /**
     * Waits for the given number of bot answers and returns the text of the last one. The loading
     * placeholder in `#loading-message` carries the `loading` class and is therefore not matched.
     *
     * The latest answer is typed out character by character by Typed.js, and there is no hook to
     * wait for - so the answer counts as complete once its text stopped growing.
     */
    protected function waitForBotMessage(int $count = 1): string
    {
        $this->waitForElementCount('#chat-body .bot-message:not(.loading)', $count);

        $deadline = microtime(true) + self::AI_TIMEOUT;
        $text = '';
        $stable = 0;

        while ($stable < 3) {
            if (microtime(true) > $deadline) {
                $this->fail(\sprintf('Timeout waiting for the answer of the bot to be complete, got: "%s".', $text));
            }

            usleep(250_000);
            $current = $this->textOf('#chat-body .bot-message:not(.loading)', $count - 1);
            $stable = '' !== $current && $current === $text ? $stable + 1 : 0;
            $text = $current;
        }

        return $text;
    }

    /**
     * Reads the text of an element from the DOM, which - unlike the text reported by the browser -
     * is also available while a live component hides the element during loading.
     */
    protected function textOf(string $selector, int $index = 0): string
    {
        $script = 'return (document.querySelectorAll(arguments[0])[arguments[1]]?.textContent ?? "").trim();';

        $text = $this->client->executeScript($script, [$selector, $index]);

        return \is_string($text) ? $text : '';
    }

    /**
     * Waits until at least the given number of elements match the selector.
     */
    protected function waitForElementCount(string $selector, int $count = 1, int $timeout = self::AI_TIMEOUT): void
    {
        $this->client->wait($timeout)->until(
            static fn (WebDriver $driver) => \count($driver->findElements(WebDriverBy::cssSelector($selector))) >= $count ?: null,
            \sprintf('Timeout waiting for %d element(s) matching "%s".', $count, $selector)
        );
    }

    /**
     * Waits until the text of an element changed, and returns the new one.
     */
    protected function waitForTextChange(string $selector, string $text, int $timeout = self::AI_TIMEOUT): string
    {
        $deadline = microtime(true) + $timeout;

        do {
            usleep(250_000);
            $current = $this->textOf($selector);
        } while ($current === $text && microtime(true) < $deadline);

        $this->assertNotSame($text, $current, \sprintf('Timeout waiting for the text of "%s" to change.', $selector));

        return $current;
    }

    /**
     * Waits for an element to be removed from the DOM - Turbo removes the `turbo-stream-source`
     * elements as soon as the streamed answer is complete.
     *
     * Their visibility is no help here: a `turbo-stream-source` never renders a box, so it counts
     * as invisible while it is streaming.
     */
    protected function waitForRemoval(string $selector, int $timeout = self::AI_TIMEOUT): void
    {
        $this->client->wait($timeout)->until(
            static fn (WebDriver $driver) => [] === $driver->findElements(WebDriverBy::cssSelector($selector)) ?: null,
            \sprintf('Timeout waiting for "%s" to be removed.', $selector)
        );
    }

    /**
     * Opens the Symfony AI panel of the profiler for the last request handled by the application -
     * the live component or streaming request that called the agent.
     */
    protected function openAiPanel(int $platformCalls = 1): AiPanel
    {
        return AiPanel::openLatest($this->client, $platformCalls);
    }

    protected function crawler(): Crawler
    {
        return $this->client->refreshCrawler();
    }

    /**
     * Chrome needs to fake camera and microphone, otherwise the video and speech use cases would
     * require a human being in front of the screen. Extending PANTHER_CHROME_ARGUMENTS keeps both
     * Panther's own defaults, like headless mode, and any argument configured in the environment.
     */
    private static function enableFakeMediaDevices(): void
    {
        self::$chromeArguments ??= $_SERVER['PANTHER_CHROME_ARGUMENTS'] ?? '';

        $arguments = [
            '--use-fake-ui-for-media-stream',
            '--use-fake-device-for-media-stream',
            '--autoplay-policy=no-user-gesture-required',
        ];

        if (null !== $audio = self::fakeAudioFile()) {
            $arguments[] = '--use-file-for-fake-audio-capture='.$audio;
        }

        $_SERVER['PANTHER_CHROME_ARGUMENTS'] = trim(self::$chromeArguments.' '.implode(' ', $arguments));
    }

    /**
     * Chrome only accepts WAV as fake microphone input, so the shared MP3 fixture of the monorepo
     * is converted once - and cached in `var/`. Without it, the microphone records silence.
     */
    private static function fakeAudioFile(): ?string
    {
        $wav = \dirname(__DIR__, 2).'/var/e2e/speech.wav';

        if (is_file($wav)) {
            return $wav;
        }

        $mp3 = \dirname(__DIR__, 3).'/fixtures/audio.mp3';
        if (!is_file($mp3) || null === (new ExecutableFinder())->find('ffmpeg')) {
            return null;
        }

        if (!is_dir($directory = \dirname($wav))) {
            mkdir($directory, 0777, true);
        }

        $process = new Process(['ffmpeg', '-y', '-i', $mp3, '-ar', '48000', '-ac', '1', $wav]);
        $process->run();

        return $process->isSuccessful() ? $wav : null;
    }
}
