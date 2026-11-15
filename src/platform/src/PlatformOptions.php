<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform;

/**
 * Names of the invocation options the platform knows; everything else goes under PROVIDER_OPTIONS.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class PlatformOptions
{
    public const MAX_OUTPUT_TOKENS = 'max_output_tokens';
    public const TEMPERATURE = 'temperature';

    public const STREAM = 'stream';
    public const TOOLS = 'tools';
    public const RESPONSE_FORMAT = 'response_format';

    public const PROVIDER_OPTIONS = 'provider_options';

    private const NAMES = [
        self::MAX_OUTPUT_TOKENS,
        self::TEMPERATURE,
        self::STREAM,
        self::TOOLS,
        self::RESPONSE_FORMAT,
        self::PROVIDER_OPTIONS,
    ];

    private function __construct()
    {
    }

    public static function isPlatformOption(string $name): bool
    {
        return \in_array($name, self::NAMES, true);
    }

    /**
     * Moves the options that are not platform options under PROVIDER_OPTIONS.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public static function nest(array $options): array
    {
        $nested = [];
        $providerOptions = [];

        foreach ($options as $name => $value) {
            if (self::isPlatformOption($name)) {
                $nested[$name] = $value;
            } else {
                $providerOptions[$name] = $value;
            }
        }

        if ([] !== $providerOptions) {
            $nested[self::PROVIDER_OPTIONS] = [...$providerOptions, ...$nested[self::PROVIDER_OPTIONS] ?? []];
        }

        return $nested;
    }

    /**
     * Translates the options into the ones the provider expects, in their order.
     *
     * Platform options without a wire field are dropped. A provider option wins over a platform
     * option for the same wire field, and dots in a wire field nest it, like "generationConfig.maxOutputTokens".
     *
     * @param array<string, mixed>  $options    the invocation options
     * @param array<string, string> $wireFields wire field per platform option, like [self::MAX_OUTPUT_TOKENS => 'max_tokens']
     *
     * @return array<string, mixed>
     */
    public static function toProviderOptions(array $options, array $wireFields = []): array
    {
        $providerOptions = [];

        foreach ($options as $name => $value) {
            if (self::PROVIDER_OPTIONS === $name) {
                foreach ($value as $key => $providerValue) {
                    $providerOptions[$key] = isset($providerOptions[$key]) && \is_array($providerOptions[$key]) && \is_array($providerValue)
                        ? array_replace_recursive($providerOptions[$key], $providerValue)
                        : $providerValue;
                }

                continue;
            }

            if (null === $value || !isset($wireFields[$name])) {
                continue;
            }

            $target = &$providerOptions;
            $path = explode('.', $wireFields[$name]);
            $last = array_pop($path);

            foreach ($path as $key) {
                if (!isset($target[$key]) || !\is_array($target[$key])) {
                    $target[$key] = [];
                }
                $target = &$target[$key];
            }

            if (!\array_key_exists($last, $target)) {
                $target[$last] = $value;
            }
            unset($target);
        }

        return $providerOptions;
    }
}
