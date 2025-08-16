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
final class Embeddings
{
    /** Supported dimensions: 3072, 1536, or 768 */
    public const GEMINI_EMBEDDING_EXP_03_07 = 'gemini-embedding-exp-03-07';
    /** Fixed 768 dimensions */
    public const TEXT_EMBEDDING_004 = 'text-embedding-004';
    /** Fixed 768 dimensions */
    public const EMBEDDING_001 = 'embedding-001';

    /**
     * @param array{dimensions?: int, task_type?: TaskType|string} $options
     */
    public static function create(string $name = self::GEMINI_EMBEDDING_EXP_03_07, array $options = []): Model
    {
        return new Model($name, [Capability::INPUT_MULTIPLE], $options);
    }
}
