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

    private const TOOL_PATTERNS = [
        '/./' => [
            Capability::INPUT_MESSAGES,
            Capability::OUTPUT_TEXT,
            Capability::OUTPUT_STRUCTURED,
        ],
        '/^(nomic).*/' => [
            Capability::INPUT_MULTIPLE,
        ],
    ];

    public function __construct(
        string $name = self::NOMIC_EMBED_TEXT,
        array $options = [],
    ) {
        $capabilities = [];

        foreach (self::TOOL_PATTERNS as $pattern => $possibleCapabilities) {
            if (1 === preg_match($pattern, $name)) {
                foreach ($possibleCapabilities as $capability) {
                    $capabilities[] = $capability;
                }
            }
        }

        parent::__construct($name, $capabilities, $options);
    }
}
