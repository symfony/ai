<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Perplexity;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class Perplexity extends Model
{
    public const SONAR = 'sonar';
    public const SONAR_PRO = 'sonar-pro';
    public const SONAR_REASONING = 'sonar-reasoning';
    public const SONAR_REASONING_PRO = 'sonar-reasoning-pro';
    public const SONAR_DEEP_RESEARCH = 'sonar-deep-research';

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        string $name = self::SONAR,
        array $options = [],
    ) {
        $capabilities = [
            Capability::INPUT_MESSAGES,
            Capability::INPUT_PDF,
            Capability::OUTPUT_TEXT,
            Capability::OUTPUT_STREAMING,
            Capability::OUTPUT_STRUCTURED,
        ];

        if (self::SONAR_DEEP_RESEARCH !== $name) {
            $capabilities[] = Capability::INPUT_IMAGE;
        }

        parent::__construct($name, $capabilities, $options);
    }
}
