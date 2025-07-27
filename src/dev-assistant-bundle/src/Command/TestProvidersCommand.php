<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\DevAssistantBundle\Command;

use Symfony\AI\DevAssistantBundle\Service\AIProviderTester;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command to test AI provider connectivity and diagnose configuration issues.
 *
 * @author Aria Vahidi <aria.vahidi2020@gmail.com>
 */
#[AsCommand(
    name: 'dev-assistant:test-providers',
    description: 'Test AI provider connectivity and diagnose configuration issues'
)]
final class TestProvidersCommand extends Command
{
    public function __construct(
        private readonly AIProviderTester $providerTester,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('provider', 'p', InputOption::VALUE_REQUIRED, 'Test specific provider (openai, anthropic, gemini)')
            ->addOption('model', 'm', InputOption::VALUE_REQUIRED, 'Test specific model with provider')
            ->addOption('detailed', 'd', InputOption::VALUE_NONE, 'Show detailed diagnostic information')
            ->setHelp(
                <<<'HELP'
The <info>dev-assistant:test-providers</info> command tests AI provider connectivity and helps diagnose configuration issues.

<comment>Test All Providers:</comment>
  <info>php bin/console dev-assistant:test-providers</info>

<comment>Test Specific Provider:</comment>
  <info>php bin/console dev-assistant:test-providers --provider=openai</info>

<comment>Test Specific Model:</comment>
  <info>php bin/console dev-assistant:test-providers --provider=openai --model=gpt-4</info>

<comment>Detailed Diagnostics:</comment>
  <info>php bin/console dev-assistant:test-providers --detailed</info>

This command helps identify:
  - API key authentication issues
  - Network connectivity problems
  - Model availability issues
  - Rate limiting problems
  - Billing/quota issues
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $provider = $input->getOption('provider');
        $model = $input->getOption('model');
        $detailed = $input->getOption('detailed');

        $io->title('🔧 AI Provider Connectivity Test');

        try {
            if ($provider && $model) {
                // Test specific provider and model
                $result = $this->testSpecificModel($provider, $model, $io);
                return $result ? Command::SUCCESS : Command::FAILURE;
                
            } elseif ($provider) {
                // Test specific provider
                $result = $this->testSpecificProvider($provider, $io);
                return $result ? Command::SUCCESS : Command::FAILURE;
                
            } else {
                // Test all providers
                return $this->testAllProviders($detailed, $io);
            }

        } catch (\Throwable $e) {
            $io->error('Test failed: ' . $e->getMessage());
            if ($output->isVerbose()) {
                $io->text($e->getTraceAsString());
            }
            return Command::FAILURE;
        }
    }

    private function testSpecificModel(string $provider, string $model, SymfonyStyle $io): bool
    {
        $io->section("Testing {$provider} with model {$model}");
        
        $result = $this->providerTester->testProviderModel($provider, $model);
        
        if ($result['status'] === 'success') {
            $io->success("✅ {$provider} with {$model} is working correctly!");
            return true;
        } else {
            $io->error("❌ {$provider} with {$model} failed");
            $this->displayError($result, $io);
            return false;
        }
    }

    private function testSpecificProvider(string $provider, SymfonyStyle $io): bool
    {
        $io->section("Testing {$provider}");
        
        $result = $this->providerTester->testProvider($provider);
        
        if ($result['status'] === 'success') {
            $io->success("✅ {$provider} is working correctly!");
            $io->definitionList(
                ['Provider' => $provider],
                ['Status' => 'Connected'],
                ['Response Length' => $result['response_length'] . ' characters'],
            );
            return true;
        } else {
            $io->error("❌ {$provider} connection failed");
            $this->displayError($result, $io);
            return false;
        }
    }

    private function testAllProviders(bool $detailed, SymfonyStyle $io): int
    {
        $io->section('Testing All AI Providers');
        
        $report = $this->providerTester->generateDiagnosticReport();
        
        // Display overall status
        $statusColor = match ($report['overall_status']) {
            'success' => 'green',
            'partial' => 'yellow',
            'failed' => 'red',
            default => 'gray',
        };
        
        $io->writeln([
            '',
            "📊 <fg={$statusColor}>Overall Status: " . strtoupper($report['overall_status']) . "</>",
            "🔌 Providers Working: {$report['providers_working']}/{$report['providers_tested']}",
            "❌ Providers Failed: {$report['providers_failed']}",
            '',
        ]);

        // Display provider results
        foreach ($report['provider_results'] as $provider => $result) {
            if ($result['status'] === 'success') {
                $io->writeln("✅ <fg=green>{$provider}</> - Connected successfully");
            } else {
                $io->writeln("❌ <fg=red>{$provider}</> - " . $result['error_type']);
                if ($detailed) {
                    $io->text("   💡 " . $result['suggestion']);
                }
            }
        }

        // Display recommendations
        if (!empty($report['recommendations'])) {
            $io->section('🚀 Recommendations');
            foreach ($report['recommendations'] as $recommendation) {
                $io->text("• " . $recommendation);
            }
        }

        // Show detailed information if requested
        if ($detailed) {
            $this->showDetailedDiagnostics($report, $io);
        }

        return $report['overall_status'] === 'failed' ? Command::FAILURE : Command::SUCCESS;
    }

    private function displayError(array $result, SymfonyStyle $io): void
    {
        $io->definitionList(
            ['Provider' => $result['provider']],
            ['Error Type' => $result['error_type'] ?? 'Unknown'],
            ['Error Message' => $result['error']],
            ['Suggestion' => $result['suggestion'] ?? 'No suggestion available'],
        );

        // Provide specific help based on error type
        $errorType = $result['error_type'] ?? 'unknown';
        $this->showErrorHelp($errorType, $io);
    }

    private function showErrorHelp(string $errorType, SymfonyStyle $io): void
    {
        $help = match ($errorType) {
            'authentication' => [
                '🔑 Authentication Error Help:',
                '• Check your .env file for correct API key variables',
                '• Verify the API key is valid and not expired',
                '• Ensure the key has the necessary permissions',
                '• For OpenAI: OPENAI_API_KEY=sk-...',
                '• For Anthropic: ANTHROPIC_API_KEY=...',
            ],
            'rate_limit' => [
                '⏰ Rate Limit Help:',
                '• You have exceeded the API rate limit',
                '• Wait a few minutes before trying again',
                '• Consider upgrading your API plan for higher limits',
                '• Implement proper rate limiting in production',
            ],
            'model_not_found' => [
                '🤖 Model Error Help:',
                '• Check if the model name is correct',
                '• Verify the model is available for your API tier',
                '• Try using a different model (e.g., gpt-3.5-turbo instead of gpt-4)',
                '• Check the provider documentation for available models',
            ],
            'network' => [
                '🌐 Network Error Help:',
                '• Check your internet connection',
                '• Verify firewall settings allow API calls',
                '• Try using a VPN if in a restricted region',
                '• Check if the API endpoint is accessible',
            ],
            'billing' => [
                '💳 Billing Error Help:',
                '• Check your account balance',
                '• Verify your payment method is valid',
                '• Add credits to your account',
                '• Review your usage limits',
            ],
            default => [
                '❓ General Troubleshooting:',
                '• Check the error message above for specific details',
                '• Verify your configuration is correct',
                '• Check the provider\'s status page',
                '• Contact support if the issue persists',
            ],
        };

        $io->block($help, null, 'fg=cyan');
    }

    private function showDetailedDiagnostics(array $report, SymfonyStyle $io): void
    {
        $io->section('🔍 Detailed Diagnostics');
        
        foreach ($report['provider_results'] as $provider => $result) {
            $io->text("<comment>Provider: {$provider}</comment>");
            
            if ($result['status'] === 'success') {
                $io->text("  ✅ Status: Working");
                $io->text("  📏 Response length: {$result['response_length']} chars");
            } else {
                $io->text("  ❌ Status: Failed");
                $io->text("  🏷️  Error type: {$result['error_type']}");
                $io->text("  📝 Error: {$result['error']}");
                $io->text("  💡 Fix: {$result['suggestion']}");
            }
            $io->newLine();
        }

        $io->text([
            '<comment>Environment Check:</comment>',
            '• PHP Version: ' . PHP_VERSION,
            '• Current Time: ' . $report['timestamp'],
            '• Working Directory: ' . getcwd(),
        ]);
    }
}
