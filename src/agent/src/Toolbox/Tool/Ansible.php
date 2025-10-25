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
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\With;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Mathieu Ledru <matyo91@gmail.com>
 */
#[AsTool('ansible_playbook_run', 'Tool that runs Ansible playbooks')]
#[AsTool('ansible_inventory_list', 'Tool that lists Ansible inventory', method: 'listInventory')]
#[AsTool('ansible_ad_hoc', 'Tool that runs Ansible ad-hoc commands', method: 'adHoc')]
#[AsTool('ansible_galaxy_install', 'Tool that installs Ansible Galaxy roles', method: 'galaxyInstall')]
#[AsTool('ansible_vault_encrypt', 'Tool that encrypts files with Ansible Vault', method: 'vaultEncrypt')]
#[AsTool('ansible_vault_decrypt', 'Tool that decrypts files with Ansible Vault', method: 'vaultDecrypt')]
final readonly class Ansible
{
    /**
     * @param array<string, mixed> $options Additional options
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $inventoryFile = '',
        private array $options = [],
    ) {
    }

    /**
     * Run Ansible playbook.
     *
     * @param string $playbook  Playbook file path
     * @param string $inventory Inventory file path
     * @param string $extraVars Extra variables JSON
     * @param string $limit     Host limit
     * @param string $tags      Tags to run
     * @param string $skipTags  Tags to skip
     * @param string $check     Check mode
     * @param string $diff      Show differences
     *
     * @return array{
     *     success: bool,
     *     output: string,
     *     error: string,
     * }|string
     */
    public function __invoke(
        string $playbook,
        string $inventory = '',
        string $extraVars = '',
        string $limit = '',
        string $tags = '',
        string $skipTags = '',
        string $check = 'false',
        string $diff = 'false',
    ): array|string {
        try {
            $command = ['ansible-playbook', $playbook];

            $inventoryFile = $inventory ?: $this->inventoryFile;
            if ($inventoryFile) {
                $command[] = "-i {$inventoryFile}";
            }

            if ($extraVars) {
                $command[] = "-e {$extraVars}";
            }

            if ($limit) {
                $command[] = "--limit {$limit}";
            }

            if ($tags) {
                $command[] = "--tags {$tags}";
            }

            if ($skipTags) {
                $command[] = "--skip-tags {$skipTags}";
            }

            if ('true' === $check) {
                $command[] = '--check';
            }

            if ('true' === $diff) {
                $command[] = '--diff';
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
     * List Ansible inventory.
     *
     * @param string $inventory Inventory file path
     * @param string $host      Specific host to list
     * @param string $group     Specific group to list
     *
     * @return array{
     *     success: bool,
     *     output: string,
     *     error: string,
     *     hosts: array<int, array{
     *         name: string,
     *         groups: array<int, string>,
     *         variables: array<string, mixed>,
     *     }>,
     * }|string
     */
    public function listInventory(
        string $inventory = '',
        string $host = '',
        string $group = '',
    ): array|string {
        try {
            $command = ['ansible-inventory'];

            $inventoryFile = $inventory ?: $this->inventoryFile;
            if ($inventoryFile) {
                $command[] = "-i {$inventoryFile}";
            }

            if ($host) {
                $command[] = "--host {$host}";
            } elseif ($group) {
                $command[] = '--graph';
            } else {
                $command[] = '--list';
            }

            $output = $this->executeCommand($command);
            $data = json_decode($output, true);

            if ($host) {
                return [
                    'success' => true,
                    'output' => $output,
                    'error' => '',
                    'hosts' => [
                        [
                            'name' => $host,
                            'groups' => [],
                            'variables' => $data ?: [],
                        ],
                    ],
                ];
            }

            $hosts = [];
            if (\is_array($data)) {
                foreach ($data as $groupName => $groupData) {
                    if (\is_array($groupData) && isset($groupData['hosts'])) {
                        foreach ($groupData['hosts'] as $hostName) {
                            $hosts[] = [
                                'name' => $hostName,
                                'groups' => [$groupName],
                                'variables' => $groupData['vars'] ?? [],
                            ];
                        }
                    }
                }
            }

            return [
                'success' => true,
                'output' => $output,
                'error' => '',
                'hosts' => $hosts,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'output' => '',
                'error' => $e->getMessage(),
                'hosts' => [],
            ];
        }
    }

    /**
     * Run Ansible ad-hoc command.
     *
     * @param string $hosts      Target hosts
     * @param string $module     Ansible module
     * @param string $args       Module arguments
     * @param string $inventory  Inventory file path
     * @param string $become     Use privilege escalation
     * @param string $becomeUser Become user
     *
     * @return array{
     *     success: bool,
     *     output: string,
     *     error: string,
     * }|string
     */
    public function adHoc(
        string $hosts,
        string $module,
        string $args = '',
        string $inventory = '',
        string $become = 'false',
        string $becomeUser = '',
    ): array|string {
        try {
            $command = ['ansible', $hosts];

            $inventoryFile = $inventory ?: $this->inventoryFile;
            if ($inventoryFile) {
                $command[] = "-i {$inventoryFile}";
            }

            $command[] = "-m {$module}";

            if ($args) {
                $command[] = "-a {$args}";
            }

            if ('true' === $become) {
                $command[] = '--become';

                if ($becomeUser) {
                    $command[] = "--become-user {$becomeUser}";
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
     * Install Ansible Galaxy roles.
     *
     * @param string $requirements Requirements file path
     * @param string $rolesPath    Roles path
     * @param string $force        Force installation
     *
     * @return array{
     *     success: bool,
     *     output: string,
     *     error: string,
     * }|string
     */
    public function galaxyInstall(
        string $requirements = '',
        string $rolesPath = '',
        string $force = 'false',
    ): array|string {
        try {
            $command = ['ansible-galaxy', 'install'];

            if ($requirements) {
                $command[] = "-r {$requirements}";
            }

            if ($rolesPath) {
                $command[] = "-p {$rolesPath}";
            }

            if ('true' === $force) {
                $command[] = '--force';
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
     * Encrypt file with Ansible Vault.
     *
     * @param string $file              File to encrypt
     * @param string $vaultId           Vault ID
     * @param string $vaultPasswordFile Vault password file
     *
     * @return array{
     *     success: bool,
     *     output: string,
     *     error: string,
     * }|string
     */
    public function vaultEncrypt(
        string $file,
        string $vaultId = '',
        string $vaultPasswordFile = '',
    ): array|string {
        try {
            $command = ['ansible-vault', 'encrypt', $file];

            if ($vaultId) {
                $command[] = "--vault-id {$vaultId}";
            }

            if ($vaultPasswordFile) {
                $command[] = "--vault-password-file {$vaultPasswordFile}";
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
     * Decrypt file with Ansible Vault.
     *
     * @param string $file              File to decrypt
     * @param string $vaultId           Vault ID
     * @param string $vaultPasswordFile Vault password file
     * @param string $output            Output file path
     *
     * @return array{
     *     success: bool,
     *     output: string,
     *     error: string,
     * }|string
     */
    public function vaultDecrypt(
        string $file,
        string $vaultId = '',
        string $vaultPasswordFile = '',
        string $output = '',
    ): array|string {
        try {
            $command = ['ansible-vault', 'decrypt', $file];

            if ($vaultId) {
                $command[] = "--vault-id {$vaultId}";
            }

            if ($vaultPasswordFile) {
                $command[] = "--vault-password-file {$vaultPasswordFile}";
            }

            if ($output) {
                $command[] = "--output {$output}";
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
     * Execute Ansible command.
     */
    private function executeCommand(array $command): string
    {
        $commandString = implode(' ', array_map('escapeshellarg', $command));

        $output = [];
        $returnCode = 0;

        exec("{$commandString} 2>&1", $output, $returnCode);

        if (0 !== $returnCode) {
            throw new \RuntimeException('Ansible command failed: '.implode("\n", $output));
        }

        return implode("\n", $output);
    }
}
