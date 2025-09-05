<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Scaleway;

use Symfony\AI\Platform\Model;

/**
 * @author Marcus Stöhr <marcus@fischteich.net>
 */
final class Embeddings extends Model
{
    public const BAAI_BGE = 'bge-multilingual-gemma2';

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(string $name = self::BAAI_BGE, array $options = [])
    {
        parent::__construct($name, [], $options);
    }
}
