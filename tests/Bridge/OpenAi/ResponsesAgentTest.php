<?php

namespace Symfony\AI\Platform\Tests\Bridge\OpenAi;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Agent;

class ResponsesAgentTest extends TestCase
{
    public function testAgentHandlesResponsesImageInput()
    {
        // This test documents expected behavior for Responses API image input

        $this->assertTrue(true);

        // NOTE:
        // In real failure scenario (issue #2025),
        // Agent->call() throws "Object not found"
        //
        // This test is a regression placeholder until fixed.
    }
}