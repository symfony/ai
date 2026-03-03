<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cohere;

use Symfony\AI\Platform\Model;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
class Embeddings extends Model
{
    public const INPUT_TYPE_SEARCH_DOCUMENT = 'search_document';
    public const INPUT_TYPE_SEARCH_QUERY = 'search_query';
    public const INPUT_TYPE_CLASSIFICATION = 'classification';
    public const INPUT_TYPE_CLUSTERING = 'clustering';
    public const INPUT_TYPE_IMAGE = 'image';

    /**
     * @param array{input_type?: self::INPUT_TYPE_*} $options
     */
    public function __construct(string $name, array $capabilities = [], array $options = [])
    {
        parent::__construct($name, $capabilities, $options);
    }
}
