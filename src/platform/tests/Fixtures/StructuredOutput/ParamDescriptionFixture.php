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

final class ParamDescriptionFixture
{
    /**
     * @param list<string>                  $tags    A list of tags
     * @param string                        $comment Same format as $count
     * @param int                           $count   How often to repeat
     * @param array<string, string>         $labels  Map of field name to label
     * @param array{name: string, age: int} $person  The person to greet
     * @param array{
     *     street: string,
     *     city: string,
     * } $address The address to ship to
     * @param string $note A note that is long enough
     *                     to wrap onto a second line
     */
    public function __invoke(array $tags, string $comment, int $count, array $labels, array $person, array $address, string $note): string
    {
        return 'ok';
    }
}
