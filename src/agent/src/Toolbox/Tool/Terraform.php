<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox\Tool;

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Mathieu Ledru <matyo91@gmail.com>
 */
#[AsTool('terraform_init', 'Tool that initializes Terraform workspace')]
#[AsTool('terraform_plan', 'Tool that creates Terraform execution plan', method: 'plan')]
#[AsTool('terraform_apply', 'Tool that applies Terraform configuration', method: 'apply')]
#[AsTool('terraform_destroy', 'Tool that destroys Terraform resources', method: 'destroy')]
#[AsTool('terraform_validate', 'Tool that validates Terraform configuration', method: 'validate')]
#[AsTool('terraform_show', 'Tool that shows Terraform state', method: 'show')]
final readonly class Terraform
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $workingDirectory = '.',
        private array $options = [],
    ) {
    }

    /**
     * Initialize Terraform workspace.
     *
     * @param string $backendConfig Backend configuration JSON
     * @param string $upgrade       Upgrade modules and plugins
     *
     * @return array{
     *     success: bool,
     *     output: string,
     *     error: string,
     * }|string
     */
    public function __invoke(
        string $backendConfig = '',
        string $upgrade = 'false',
    ): array|string {
        try {
            $command = ['terraform', 'init'];

            if ('true' === $upgrade) {
                $command[] = '-upgrade';
            }

            if ($backendConfig) {
                $config = json_decode($backendConfig, true);
                if ($config) {
                    foreach ($config as $key => $value) {
                        $command[] = "-backend-config={$key}={$value}";
                    }
                }
            }

            $output = $this->executeCommand($command);

            return [
                'success' => true,
                'output' => $output,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'output' => '',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create Terraform execution plan.
     *
     * @param string $varFile Variable file path
     * @param string $target  Target resource
     * @param string $out     Output plan file
     * @param string $destroy Destroy plan
     *
     * @return array{
     *     success: bool,
     *     output: string,
     *     error: string,
     *     planFile: string,
     * }|string
     */
    public function plan(
        string $varFile = '',
        string $target = '',
        string $out = '',
        string $destroy = 'false',
    ): array|string {
        try {
            $command = ['terraform', 'plan'];

            if ($varFile) {
                $command[] = "-var-file={$varFile}";
            }

            if ($target) {
                $command[] = "-target={$target}";
            }

            if ($out) {
                $command[] = "-out={$out}";
            }

            if ('true' === $destroy) {
                $command[] = '-destroy';
            }

            $output = $this->executeCommand($command);

            return [
                'success' => true,
                'output' => $output,
                'error' => '',
                'planFile' => $out ?: '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'output' => '',
                'error' => $e->getMessage(),
                'planFile' => '',
            ];
        }
    }

    /**
     * Apply Terraform configuration.
     *
     * @param string $planFile    Plan file path
     * @param string $autoApprove Auto approve changes
     * @param string $target      Target resource
     *
     * @return array{
     *     success: bool,
     *     output: string,
     *     error: string,
     * }|string
     */
    public function apply(
        string $planFile = '',
        string $autoApprove = 'false',
        string $target = '',
    ): array|string {
        try {
            $command = ['terraform', 'apply'];

            if ($planFile) {
                $command[] = $planFile;
            } else {
                if ('true' === $autoApprove) {
                    $command[] = '-auto-approve';
                }

                if ($target) {
                    $command[] = "-target={$target}";
                }
            }

            $output = $this->executeCommand($command);

            return [
                'success' => true,
                'output' => $output,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'output' => '',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Destroy Terraform resources.
     *
     * @param string $autoApprove Auto approve destruction
     * @param string $target      Target resource
     *
     * @return array{
     *     success: bool,
     *     output: string,
     *     error: string,
     * }|string
     */
    public function destroy(
        string $autoApprove = 'false',
        string $target = '',
    ): array|string {
        try {
            $command = ['terraform', 'destroy'];

            if ('true' === $autoApprove) {
                $command[] = '-auto-approve';
            }

            if ($target) {
                $command[] = "-target={$target}";
            }

            $output = $this->executeCommand($command);

            return [
                'success' => true,
                'output' => $output,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'output' => '',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Validate Terraform configuration.
     *
     * @return array{
     *     success: bool,
     *     output: string,
     *     error: string,
     * }|string
     */
    public function validate(): array|string
    {
        try {
            $command = ['terraform', 'validate'];

            $output = $this->executeCommand($command);

            return [
                'success' => true,
                'output' => $output,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'output' => '',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Show Terraform state.
     *
     * @param string $resource Specific resource to show
     *
     * @return array{
     *     success: bool,
     *     output: string,
     *     error: string,
     * }|string
     */
    public function show(string $resource = ''): array|string
    {
        try {
            $command = ['terraform', 'show'];

            if ($resource) {
                $command[] = $resource;
            }

            $output = $this->executeCommand($command);

            return [
                'success' => true,
                'output' => $output,
                'error' => '',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'output' => '',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Execute Terraform command.
     */
    private function executeCommand(array $command): string
    {
        $commandString = implode(' ', array_map('escapeshellarg', $command));

        $output = [];
        $returnCode = 0;

        exec("cd {$this->workingDirectory} && {$commandString} 2>&1", $output, $returnCode);

        if (0 !== $returnCode) {
            throw new \RuntimeException('Terraform command failed: '.implode("\n", $output));
        }

        return implode("\n", $output);
    }
}
