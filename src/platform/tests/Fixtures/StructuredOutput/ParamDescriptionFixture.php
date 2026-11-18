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
     * @param list<string>                  $tags   A list of tags
     * @param array<string, string>         $labels Map of field name to label
     * @param array{name: string, age: int} $person The person to greet
     */
    public function __invoke(array $tags, array $labels, array $person): string
    {
        return 'ok';
    }
}
