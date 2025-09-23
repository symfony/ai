<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Mistral;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Mistral extends Model
{
    public const CODESTRAL = 'codestral-latest';
    public const MISTRAL_LARGE = 'mistral-large-latest';
    public const MISTRAL_MEDIUM = 'mistral-medium-latest';
    public const MISTRAL_SMALL = 'mistral-small-latest';
    public const MISTRAL_NEMO = 'open-mistral-nemo';
    public const MISTRAL_SABA = 'mistral-saba-latest';
    public const MINISTRAL_3B = 'ministral-3b-latest';
    public const MINISTRAL_8B = 'ministral-8b-latest';
    public const PIXSTRAL_LARGE = 'pixstral-large-latest';
    public const PIXSTRAL = 'pixstral-12b-latest';
    public const VOXTRAL_SMALL = 'voxtral-small-latest';
    public const VOXTRAL_MINI = 'voxtral-mini-latest';

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        string $name = self::MISTRAL_LARGE,
        array $options = [],
    ) {
        $capabilities = [
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_TEXT,
            Capability::OUTPUT_STREAMING,
            Capability::OUTPUT_STRUCTURED,
        ];

        if (\in_array($name, [self::PIXSTRAL, self::PIXSTRAL_LARGE, self::MISTRAL_MEDIUM, self::MISTRAL_SMALL], true)) {
            $capabilities[] = Capability::INPUT_IMAGE;
        }

        if (\in_array($name, [self::VOXTRAL_SMALL, self::VOXTRAL_MINI], true)) {
            $capabilities[] = Capability::INPUT_AUDIO;
        }

        if (\in_array($name, [
            self::CODESTRAL,
            self::MISTRAL_LARGE,
            self::MISTRAL_MEDIUM,
            self::MISTRAL_SMALL,
            self::MISTRAL_NEMO,
            self::MINISTRAL_3B,
            self::MINISTRAL_8B,
            self::PIXSTRAL,
            self::PIXSTRAL_LARGE,
            self::VOXTRAL_MINI,
            self::VOXTRAL_SMALL,
        ], true)) {
            $capabilities[] = Capability::TOOL_CALLING;
        }

        parent::__construct($name, $capabilities, $options);
    }
}
