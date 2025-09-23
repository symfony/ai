<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\DockerModelRunner;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
class Embeddings extends Model
{
    public const NOMIC_EMBED_TEXT = 'ai/nomic-embed-text-v1.5';
    public const MXBAI_EMBED_LARGE = 'ai/mxbai-embed-large';
    public const EMBEDDING_GEMMA = 'ai/embeddinggemma';
    public const GRANITE_EMBEDDING_MULTI = 'ai/granite-embedding-multilingual';

    public function __construct(string $name, array $options = [])
    {
        parent::__construct($name, Capability::cases(), $options);
    }
}
