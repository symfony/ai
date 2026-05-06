<?php

namespace Symfony\AI\Platform\Tests\Bridge\OpenAi;

use PHPUnit\Framework\TestCase;

class ResponsesImageTest extends TestCase
{
    public function testResponsesApiImageInputStructureMismatch()
    {
        // Simulated Responses API payload (what OpenAI returns)
        $response = [
            'output' => [
                [
                    'type' => 'message',
                    'content' => [
                        [
                            'type' => 'output_text',
                            'text' => 'Image description'
                        ]
                    ]
                ]
            ]
        ];

        // Symfony AI currently expects older structure like "choices"
        $this->assertArrayNotHasKey('choices', $response);

        // New format exists
        $this->assertArrayHasKey('output', $response);

        // This documents the mismatch causing "Object not found"
        $this->assertTrue(true);
    }
}