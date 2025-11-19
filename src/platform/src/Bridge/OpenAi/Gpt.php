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

use Symfony\AI\Platform\Model;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
class Gpt extends Model
{
    /**
     * @param array<mixed> $options The default options for the model usage
     */
    public function __construct(string $name, array $capabilities = [], array $options = [])
    {
        parent::__construct($name, $capabilities, $options);
    }
}
