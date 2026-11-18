<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Fixtures\StructuredOutput;

final class WrappedDescription
{
    /**
     * @var string A note that is long enough
     *             to wrap onto a second line
     */
    public string $note;
}
