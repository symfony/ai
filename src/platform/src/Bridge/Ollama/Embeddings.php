<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ollama;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;

final class Embeddings extends Model
{
    public const NOMIC_EMBED_TEXT = 'nomic-embed-text';

    public const BGE_M3 = 'bge-m3';

    public const ALL_MINILM = 'all-minilm';

    public function __construct(
        string $name = self::NOMIC_EMBED_TEXT,
        array $options = [],
    ) {
        parent::__construct($name, [Capability::INPUT_MULTIPLE], $options);
    }
}
