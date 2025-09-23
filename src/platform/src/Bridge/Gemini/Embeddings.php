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

use Symfony\AI\Platform\Bridge\Gemini\Embeddings\TaskType;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;

/**
 * @author Valtteri R <valtzu@gmail.com>
 */
class Embeddings extends Model
{
    public const GEMINI_EMBEDDING_EXP_03_07 = 'gemini-embedding-exp-03-07';
    public const TEXT_EMBEDDING_004 = 'text-embedding-004';
    public const EMBEDDING_001 = 'embedding-001';

    /**
     * @param array{dimensions?: int, task_type?: TaskType|string} $options
     */
    public function __construct(string $name, array $options = [])
    {
        parent::__construct($name, [Capability::INPUT_MULTIPLE], $options);
    }
}
