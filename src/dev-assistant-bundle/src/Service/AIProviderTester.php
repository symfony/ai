<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\DevAssistantBundle\Service;

use Psr\Log\LoggerInterface;
use Symfony\AI\ToolBox\ToolboxRunner;

/**
 * Service to test AI provider connectivity and configuration.
 *
 * This service helps diagnose issues with AI provider connections,
 * API keys, and model availability before running actual analysis.
 *
 * @author Aria Vahidi <aria.vahidi2020@gmail.com>
 */
final readonly class AIProviderTester
{
    public function __construct(
        private ToolboxRunner $toolboxRunner,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Test connectivity to all configured AI providers.
     *
     * @return array<string, array<string, mixed>>
     */
    public function testAllProviders(): array
    {
        $providers = ['openai', 'anthropic', 'gemini'];
        $results = [];

        foreach ($providers as $provider) {
            $results[$provider] = $this->testProvider($provider);
        }

        return $results;
    }

    /**
     * Test a specific AI provider.
     *
     * @return array<string, mixed>
     */
    public function testProvider(string $provider): array
    {
        $this->logger->info("Testing AI provider connectivity", ['provider' => $provider]);

        try {
            // Test with a simple prompt
            $testPrompt = "Say 'Hello' if you can read this message.";
            
            $response = $this->toolboxRunner->run($provider, null, [
                'messages' => [
                    ['role' => 'user', 'content' => $testPrompt],
                ],
                'max_tokens' => 10,
                'temperature' => 0.1,
            ]);

            if (empty($response)) {
                return [
                    'status' => 'error',
                    'error' => 'Empty response from provider',
                    'provider' => $provider,
                    'suggestion' => 'Check if the model is available and your API key has sufficient credits.',
                ];
            }

            return [
                'status' => 'success',
                'provider' => $provider,
                'response_length' => strlen($response),
                'test_successful' => true,
                'message' => 'Provider is working correctly',
            ];

        } catch (\Throwable $e) {
            $errorMessage = $e->getMessage();
            
            return [
                'status' => 'error',
                'provider' => $provider,
                'error' => $errorMessage,
                'error_type' => $this->categorizeError($errorMessage),
                'suggestion' => $this->getSuggestionForError($errorMessage),
                'test_successful' => false,
            ];
        }
    }

    /**
     * Test a specific model with a provider.
     *
     * @return array<string, mixed>
     */
    public function testProviderModel(string $provider, string $model): array
    {
        $this->logger->info("Testing specific model", [
            'provider' => $provider,
            'model' => $model
        ]);

        try {
            $response = $this->toolboxRunner->run($provider, $model, [
                'messages' => [
                    ['role' => 'user', 'content' => 'Test connection'],
                ],
                'max_tokens' => 5,
            ]);

            return [
                'status' => 'success',
                'provider' => $provider,
                'model' => $model,
                'test_successful' => true,
            ];

        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'provider' => $provider,
                'model' => $model,
                'error' => $e->getMessage(),
                'error_type' => $this->categorizeError($e->getMessage()),
                'suggestion' => $this->getSuggestionForError($e->getMessage()),
                'test_successful' => false,
            ];
        }
    }

    private function categorizeError(string $errorMessage): string
    {
        $lowerError = strtolower($errorMessage);

        if (str_contains($lowerError, 'api key') || str_contains($lowerError, 'unauthorized') || str_contains($lowerError, '401')) {
            return 'authentication';
        }

        if (str_contains($lowerError, 'quota') || str_contains($lowerError, 'rate limit') || str_contains($lowerError, '429')) {
            return 'rate_limit';
        }

        if (str_contains($lowerError, 'model') || str_contains($lowerError, '404')) {
            return 'model_not_found';
        }

        if (str_contains($lowerError, 'network') || str_contains($lowerError, 'connection') || str_contains($lowerError, 'timeout')) {
            return 'network';
        }

        if (str_contains($lowerError, 'insufficient') || str_contains($lowerError, 'credit') || str_contains($lowerError, 'billing')) {
            return 'billing';
        }

        return 'unknown';
    }

    private function getSuggestionForError(string $errorMessage): string
    {
        $errorType = $this->categorizeError($errorMessage);

        return match ($errorType) {
            'authentication' => 'Check your API key configuration. Ensure the key is valid and has the correct permissions.',
            'rate_limit' => 'You have exceeded the rate limit. Wait a moment and try again, or upgrade your plan.',
            'model_not_found' => 'The specified model is not available. Check the model name or try a different model.',
            'network' => 'Network connectivity issue. Check your internet connection and firewall settings.',
            'billing' => 'Billing or credit issue. Check your account balance and payment method.',
            'unknown' => 'Unknown error occurred. Check the logs for more details and contact support if needed.',
        };
    }

    /**
     * Generate a comprehensive diagnostic report.
     *
     * @return array<string, mixed>
     */
    public function generateDiagnosticReport(): array
    {
        $results = $this->testAllProviders();
        
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'overall_status' => 'unknown',
            'providers_tested' => count($results),
            'providers_working' => 0,
            'providers_failed' => 0,
            'recommendations' => [],
            'provider_results' => $results,
        ];

        foreach ($results as $provider => $result) {
            if ($result['status'] === 'success') {
                $report['providers_working']++;
            } else {
                $report['providers_failed']++;
                $report['recommendations'][] = "Fix {$provider}: " . $result['suggestion'];
            }
        }

        $report['overall_status'] = $report['providers_working'] > 0 ? 'partial' : 'failed';
        if ($report['providers_working'] === $report['providers_tested']) {
            $report['overall_status'] = 'success';
        }

        return $report;
    }
}
