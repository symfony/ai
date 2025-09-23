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
 */
class Embeddings extends Model
{
    public const TEXT_ADA_002 = 'text-embedding-ada-002';
    public const TEXT_3_LARGE = 'text-embedding-3-large';
    public const TEXT_3_SMALL = 'text-embedding-3-small';

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(string $name, array $options = [])
    {
        parent::__construct($name, [], $options);
    }
}
