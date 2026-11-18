<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Bc;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Finding
{
    /**
     * A change the BC promise forbids.
     */
    public const BREAK = 'break';

    /**
     * The second step of an upgrade path, e.g. removing deprecated code. Allowed in a major version only.
     */
    public const MAJOR_ONLY = 'major-only';

    /**
     * The first step of an upgrade path, e.g. a deprecation. Needs a CHANGELOG.md entry.
     */
    public const DEPRECATION = 'deprecation';

    /**
     * An upgrade path that is applied incorrectly.
     */
    public const VIOLATION = 'violation';

    /**
     * Worth knowing in a review, but not covered by the BC promise.
     */
    public const NOTICE = 'notice';

    /**
     * @param self::* $type
     * @param string  $guide URL of the matching section of the BC promise
     */
    public function __construct(
        public readonly string $type,
        public readonly string $symbol,
        public readonly string $message,
        public readonly string $file,
        public readonly int $line,
        public readonly string $guide,
    ) {
    }
}
