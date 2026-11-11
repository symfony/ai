<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform;

/**
 * Names of the options that bridges translate to their own wire format.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class PlatformOptions
{
    public const MAX_OUTPUT_TOKENS = 'max_output_tokens';

    /**
     * Whether an embeddings request embeds a search query or a document, see the INPUT_TYPE_* values.
     */
    public const INPUT_TYPE = 'embedding_input_type';

    public const INPUT_TYPE_QUERY = 'query';
    public const INPUT_TYPE_DOCUMENT = 'document';

    private function __construct()
    {
    }
}
