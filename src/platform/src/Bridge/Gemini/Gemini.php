<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini;

use Symfony\AI\Platform\Model;

/**
 * @author Roy Garrido
 */
final class Gemini extends Model
{
    public function __construct(
        string $name,
        array $capabilities = [],
        array $options = [],
        private readonly ?string $version = null,
        private readonly ?int $inputTokenLimit = null,
        private readonly ?int $outputTokenLimit = null,
    ) {
        parent::__construct($name, $capabilities, $options);
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getInputTokenLimit(): int
    {
        return $this->inputTokenLimit;
    }

    public function getOutputTokenLimit(): int
    {
        return $this->outputTokenLimit;
    }
}
