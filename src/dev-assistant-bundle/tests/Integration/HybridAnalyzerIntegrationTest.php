<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\DevAssistantBundle\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\AI\DevAssistantBundle\Analyzer\HybridCodeQualityAnalyzer;
use Symfony\AI\DevAssistantBundle\Model\AnalysisRequest;
use Symfony\AI\DevAssistantBundle\Model\AnalysisType;
use Symfony\AI\DevAssistantBundle\Model\Severity;
use Symfony\AI\ToolBox\ToolboxRunner;

/**
 * Integration test for the hybrid analyzer with real AI provider testing.
 *
 * @author Aria Vahidi <aria.vahidi2020@gmail.com>
 */
class HybridAnalyzerIntegrationTest extends TestCase
{
    private ToolboxRunner $toolboxRunner;
    private HybridCodeQualityAnalyzer $analyzer;

    protected function setUp(): void
    {
        $this->toolboxRunner = $this->createMock(ToolboxRunner::class);
        $this->analyzer = new HybridCodeQualityAnalyzer(
            $this->toolboxRunner,
            new NullLogger()
        );
    }

    public function testAnalyzerWithWorkingAI(): void
    {
        // Mock successful AI response
        $aiResponse = json_encode([
            'summary' => 'AI analysis found security vulnerabilities',
            'issues' => [
                [
                    'id' => 'ai_001',
                    'title' => 'SQL Injection Risk',
                    'description' => 'Direct SQL concatenation detected',
                    'severity' => 'critical',
                    'category' => 'security',
                    'file' => 'test.php',
                    'line' => 10,
                    'fix_suggestion' => 'Use prepared statements',
                ],
            ],
            'suggestions' => [
                [
                    'id' => 'ai_sugg_001',
                    'title' => 'Implement Input Validation',
                    'description' => 'Add comprehensive input validation',
                    'type' => 'code_cleanup',
                    'priority' => 'high',
                ],
            ],
        ]);

        $this->toolboxRunner
            ->expects($this->once())
            ->method('run')
            ->with('openai')
            ->willReturn($aiResponse);

        $request = new AnalysisRequest(
            id: 'test_001',
            type: AnalysisType::CODE_QUALITY,
            files: [
                'test.php' => 'query("SELECT * FROM users WHERE id = " . $id);',
            ]
        );

        $result = $this->analyzer->analyze($request);

        $this->assertEquals(AnalysisType::CODE_QUALITY, $result->type);
        $this->assertStringContains('AI analysis found security vulnerabilities', $result->summary);
        $this->assertCount(1, $result->issues);
        $this->assertEquals(Severity::CRITICAL, $result->issues[0]->severity);
        $this->assertEquals('ai_powered', $result->metrics['analysis_type']);
    }

    public function testAnalyzerWithFailingAI(): void
    {
        // Mock AI failure - will fall back to static analysis
        $this->toolboxRunner
            ->expects($this->exactly(3)) // Tries all 3 providers
            ->method('run')
            ->willThrowException(new \Exception('API Error'));

        $request = new AnalysisRequest(
            id: 'test_002',
            type: AnalysisType::CODE_QUALITY,
            files: [
                'vulnerable.php' => 'query("SELECT * FROM users WHERE id = " . $_GET["id"]);',
            ]
        );

        $result = $this->analyzer->analyze($request);

        $this->assertEquals(AnalysisType::CODE_QUALITY, $result->type);
        $this->assertStringContains('Static analysis found', $result->summary);
        $this->assertStringContains('check provider configuration', $result->summary);
        $this->assertEquals('static_fallback', $result->metrics['analysis_type']);
        $this->assertFalse($result->metrics['ai_available']);
    }

    public function testDetectsSecurityIssuesStatically(): void
    {
        // Force static analysis by making AI fail
        $this->toolboxRunner
            ->method('run')
            ->willThrowException(new \Exception('No API key'));

        $request = new AnalysisRequest(
            id: 'test_003',
            type: AnalysisType::CODE_QUALITY,
            files: [
                'insecure.php' => 'query("SELECT * FROM users WHERE name = " . $userInput);',
            ]
        );

        $result = $this->analyzer->analyze($request);

        // Should detect SQL injection even in static mode
        $securityIssues = array_filter(
            $result->issues,
            fn($issue) => $issue->category->name === 'security'
        );

        $this->assertNotEmpty($securityIssues, 'Should detect security issues statically');
        
        $sqlInjectionIssue = current($securityIssues);
        $this->assertEquals(Severity::CRITICAL, $sqlInjectionIssue->severity);
        $this->assertStringContains('SQL Injection', $sqlInjectionIssue->title);
    }

    public function testReturnsConfigurationSuggestions(): void
    {
        // Mock AI failure
        $this->toolboxRunner
            ->method('run')
            ->willThrowException(new \Exception('Invalid API key'));

        $request = new AnalysisRequest(
            id: 'test_004',
            type: AnalysisType::CODE_QUALITY,
            files: ['clean.php' => '<?php echo "Hello World";']
        );

        $result = $this->analyzer->analyze($request);

        // Should suggest enabling AI analysis
        $configSuggestions = array_filter(
            $result->suggestions,
            fn($suggestion) => str_contains($suggestion->title, 'Enable AI Analysis')
        );

        $this->assertNotEmpty($configSuggestions, 'Should suggest AI configuration');
    }
}
