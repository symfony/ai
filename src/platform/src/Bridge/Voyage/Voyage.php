<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Voyage;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
class Voyage extends Model
{
    /** Supported dimensions: 2048, 1024, 512, or 256 */
    public const V3_5 = 'voyage-3.5';
    /** Supported dimensions: 2048, 1024, 512, or 256 */
    public const V3_5_LITE = 'voyage-3.5-lite';
    /** Fixed 1024 dimensions */
    public const V3 = 'voyage-3';
    /** Fixed 512 dimensions */
    public const V3_LITE = 'voyage-3-lite';
    /** Supported dimensions: 2048, 1024, 512, or 256 */
    public const V3_LARGE = 'voyage-3-large';
    /** Fixed 1024 dimensions */
    public const FINANCE_2 = 'voyage-finance-2';
    /** Fixed 1024 dimensions */
    public const MULTILINGUAL_2 = 'voyage-multilingual-2';
    /** Fixed 1024 dimensions */
    public const LAW_2 = 'voyage-law-2';
    /** Supported dimensions: 2048, 1024, 512, or 256 */
    public const CODE_3 = 'voyage-code-3';
    /** Fixed 1536 dimensions */
    public const CODE_2 = 'voyage-code-2';

    public const INPUT_TYPE_DOCUMENT = 'document';
    public const INPUT_TYPE_QUERY = 'query';

    /**
     * @param array{dimensions?: int, input_type?: self::INPUT_TYPE_*, truncation?: bool} $options
     */
    public function __construct(string $name = self::V3_5_LITE, array $options = [])
    {
        parent::__construct($name, [Capability::INPUT_MULTIPLE], $options);
    }
}
