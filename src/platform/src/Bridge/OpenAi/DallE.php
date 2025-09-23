<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;

/**
 * @author Denis Zunke <denis.zunke@gmail.com>
 */
class DallE extends Model
{
    public const DALL_E_2 = 'dall-e-2';
    public const DALL_E_3 = 'dall-e-3';

    /** @param array<string, mixed> $options The default options for the model usage */
    public function __construct(string $name, array $options = [])
    {
        $capabilities = [
            Capability::INPUT_TEXT,
            Capability::OUTPUT_IMAGE,
        ];

        parent::__construct($name, $capabilities, $options);
    }
}
