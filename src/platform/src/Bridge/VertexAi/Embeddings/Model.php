<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\VertexAi\Embeddings;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model as BaseModel;

/**
 * @author Junaid Farooq <ulislam.junaid125@gmail.com>
 */
final class Model extends BaseModel
{
    /** Upto 3072 dimensions */
    public const GEMINI_EMBEDDING_001 = 'gemini-embedding-001';
    /** Upto 768 dimensions */
    public const TEXT_EMBEDDING_005 = 'text-embedding-005';
    /** Upto 768 dimensions */
    public const TEXT_MULTILINGUAL_EMBEDDING_002 = 'text-multilingual-embedding-002';

    /**
     * @see https://cloud.google.com/vertex-ai/generative-ai/docs/model-reference/text-embeddings-api for various options
     */
    public function __construct(string $name = self::GEMINI_EMBEDDING_001, array $options = [])
    {
        parent::__construct($name, [Capability::INPUT_TEXT, Capability::INPUT_MULTIPLE], $options);
    }
}
