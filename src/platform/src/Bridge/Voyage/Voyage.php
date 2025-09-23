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
    public const V3_5 = 'voyage-3.5';
    public const V3_5_LITE = 'voyage-3.5-lite';
    public const V3 = 'voyage-3';
    public const V3_LITE = 'voyage-3-lite';
    public const V3_LARGE = 'voyage-3-large';
    public const FINANCE_2 = 'voyage-finance-2';
    public const MULTILINGUAL_2 = 'voyage-multilingual-2';
    public const LAW_2 = 'voyage-law-2';
    public const CODE_3 = 'voyage-code-3';
    public const CODE_2 = 'voyage-code-2';

    public const INPUT_TYPE_DOCUMENT = 'document';
    public const INPUT_TYPE_QUERY = 'query';

    /**
     * @param array{dimensions?: int, input_type?: self::INPUT_TYPE_*, truncation?: bool} $options
     */
    public function __construct(string $name, array $options = [])
    {
        parent::__construct($name, [Capability::INPUT_MULTIPLE], $options);
    }
}
