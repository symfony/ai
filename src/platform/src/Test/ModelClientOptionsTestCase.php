<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Test;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\PlatformOptions;

/**
 * Base test case checking that a model client sends the canonical platform options as its provider expects them.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
abstract class ModelClientOptionsTestCase extends TestCase
{
    private const CANONICAL_VALUES = [
        PlatformOptions::MAX_OUTPUT_TOKENS => 123,
        PlatformOptions::TEMPERATURE => 0.3,
    ];

    /**
     * The wire field per canonical option, with dots for nested fields, or null if the model client does not send it.
     *
     * @return array<string, string|null>
     */
    abstract public static function wireFields(): array;

    public function testEveryCanonicalOptionIsCovered()
    {
        $this->assertEqualsCanonicalizing(array_keys(self::CANONICAL_VALUES), array_keys(static::wireFields()));
    }

    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function provideCanonicalOptions(): iterable
    {
        foreach (static::wireFields() as $option => $wireField) {
            yield $option => [$option, $wireField];
        }
    }

    #[DataProvider('provideCanonicalOptions')]
    public function testCanonicalOptionIsSentAsItsWireField(string $option, ?string $wireField)
    {
        $value = self::CANONICAL_VALUES[$option];
        $sent = $this->sendRequest([$option => $value]);

        if (null === $wireField) {
            $this->assertArrayNotHasKey($option, $sent);

            return;
        }

        $this->assertSame($value, $this->read($sent, $wireField));
        if ($option !== $wireField) {
            $this->assertArrayNotHasKey($option, $sent);
        }

        $providerValue = \is_int($value) ? 42 : 0.9;
        $sent = $this->sendRequest([$option => $value, PlatformOptions::PROVIDER_OPTIONS => $this->nest($wireField, $providerValue)]);

        $this->assertSame($providerValue, $this->read($sent, $wireField), 'The provider option must win over the canonical one.');
    }

    /**
     * Sends a request with the given options and returns the options the provider received.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    abstract protected function sendRequest(array $options): array;

    /**
     * @param array<string, mixed> $options
     */
    private function read(array $options, string $wireField): mixed
    {
        foreach (explode('.', $wireField) as $key) {
            $this->assertIsArray($options);
            $this->assertArrayHasKey($key, $options);
            $options = $options[$key];
        }

        return $options;
    }

    /**
     * @return array<string, mixed>
     */
    private function nest(string $wireField, mixed $value): array
    {
        foreach (array_reverse(explode('.', $wireField)) as $key) {
            $value = [$key => $value];
        }

        return $value;
    }
}
