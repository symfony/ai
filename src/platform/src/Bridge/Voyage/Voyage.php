<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Voyage;

use Symfony\AI\Platform\Model;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
class Voyage extends Model
{
    public const INPUT_TYPE_DOCUMENT = 'document';
    public const INPUT_TYPE_QUERY = 'query';

    /**
     * @param array{dimensions?: int, input_type?: self::INPUT_TYPE_*, truncation?: bool} $options
     */
    public function __construct(string $name, array $capabilities = [], array $options = [])
    {
        parent::__construct($name, $capabilities, $options);
    }
}
