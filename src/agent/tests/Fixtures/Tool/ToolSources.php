<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Fixtures\Tool;

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Agent\Toolbox\Source\HasSourcesInterface;
use Symfony\AI\Agent\Toolbox\Source\HasSourcesTrait;
use Symfony\AI\Agent\Toolbox\Source\Source;

#[AsTool('tool_sources', 'Tool that records some sources')]
final class ToolSources implements HasSourcesInterface
{
    use HasSourcesTrait;

    /**
     * @param string $query Search query
     */
    public function __invoke(string $query): string
    {
        $foundContent = 'Content of that relevant article.';

        $this->addSource(
            new Source('Relevant Article', 'https://example.com/relevant-article', $foundContent),
        );

        return $foundContent;
    }
}
