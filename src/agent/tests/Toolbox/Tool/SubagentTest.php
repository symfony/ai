<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Toolbox\Tool;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\MockAgent;
use Symfony\AI\Agent\MockResponse;
use Symfony\AI\Agent\Toolbox\Source\Source;
use Symfony\AI\Agent\Toolbox\Tool\Subagent;

final class SubagentTest extends TestCase
{
    private MockAgent $agent;
    private Subagent $subagent;

    protected function setUp(): void
    {
        $this->agent = new MockAgent();
        $this->subagent = new Subagent($this->agent);
    }

    public function testItCallsAgentAndReturnsContent()
    {
        $sources = [
            new Source('doc1', 'ref1', 'content1'),
            new Source('doc2', 'ref2', 'content2'),
        ];

        $response = new MockResponse($responseContent = 'It will be sunny today!');
        $response->getMetadata()->add('sources', $sources);

        $this->agent->addResponse($userPrompt = 'What is the weather?', $response);

        $result = ($this->subagent)($userPrompt);

        $this->assertSame($responseContent, $result);
        $this->assertSame($sources, $this->subagent->getSourceCollection()->all());

        $this->agent->assertCallCount(1);
        $this->agent->assertCalledWith($userPrompt);
    }
}
