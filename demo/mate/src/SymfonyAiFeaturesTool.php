<?php

namespace App\Mate;

use Mcp\Capability\Attribute\McpTool;
use Symfony\Component\Yaml\Yaml;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
class SymfonyAiFeaturesTool
{
    public function __construct(
        private string $projectDir,
    ) {
    }

    /**
     * @return array{
     *     success: bool,
     *     summary: array<string, int>,
     *     platforms?: array<int, array<string, mixed>>,
     *     agents?: array<int, array<string, mixed>>,
     *     stores?: array<int, array<string, mixed>>,
     *     tools?: array<int, array<string, mixed>>,
     *     multi_agent_setups?: array<int, array<string, mixed>>,
     *     indexers?: array<int, array<string, mixed>>,
     *     retrievers?: array<int, array<string, mixed>>,
     *     vectorizers?: array<int, array<string, mixed>>,
     *     installed_packages?: array<int, array<string, string>>,
     *     error?: string,
     *     message?: string
     * }
     */
    #[McpTool('symfony-ai-features', 'Detects and lists all available Symfony AI features, platforms, agents, tools, and configurations in this project')]
    public function getFeatures(bool $includeDetails = true): array
    {
        $configPath = $this->projectDir . '/config/packages/ai.yaml';
        $composerPath = $this->projectDir . '/composer.json';

        if (!file_exists($configPath)) {
            return [
                'success' => false,
                'error' => 'AI configuration file not found',
                'summary' => [],
            ];
        }

        try {
            $config = Yaml::parseFile($configPath);
            $aiConfig = $config['ai'] ?? [];

            $composer = json_decode(file_get_contents($composerPath), true);

            $features = [
                'platforms' => $this->detectPlatforms($aiConfig, $includeDetails),
                'agents' => $this->detectAgents($aiConfig, $includeDetails),
                'stores' => $this->detectStores($aiConfig, $includeDetails),
                'tools' => $this->detectTools($aiConfig, $includeDetails),
                'multi_agent_setups' => $this->detectMultiAgentSetups($aiConfig, $includeDetails),
                'indexers' => $this->detectIndexers($aiConfig, $includeDetails),
                'retrievers' => $this->detectRetrievers($aiConfig, $includeDetails),
                'vectorizers' => $this->detectVectorizers($aiConfig, $includeDetails),
                'installed_packages' => $this->detectInstalledPackages($composer),
            ];

            return [
                'success' => true,
                'summary' => $this->generateSummary($features),
                ...$features,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to parse configuration',
                'message' => $e->getMessage(),
                'summary' => [],
            ];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function detectPlatforms(array $config, bool $includeDetails): array
    {
        $platforms = [];
        $platformConfig = $config['platform'] ?? [];

        foreach ($platformConfig as $name => $settings) {
            $platform = [
                'name' => $name,
                'configured' => true,
            ];

            if ($includeDetails) {
                $platform['has_api_key'] = isset($settings['api_key']);
                if (isset($settings['api_key'])) {
                    $platform['api_key_env_var'] = $this->extractEnvVar($settings['api_key']);
                }
            }

            $platforms[] = $platform;
        }

        return $platforms;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function detectAgents(array $config, bool $includeDetails): array
    {
        $agents = [];
        $agentConfig = $config['agent'] ?? [];

        foreach ($agentConfig as $name => $settings) {
            $agent = [
                'name' => $name,
                'platform' => $settings['platform'] ?? 'unknown',
                'model' => is_array($settings['model'] ?? null)
                    ? ($settings['model']['name'] ?? 'unknown')
                    : ($settings['model'] ?? 'unknown'),
            ];

            if ($includeDetails) {
                $agent['has_custom_prompt'] = isset($settings['prompt']);
                $agent['tools_enabled'] = ($settings['tools'] ?? null) !== false;

                if ($agent['tools_enabled'] && is_array($settings['tools'] ?? null)) {
                    $agent['tools'] = $this->parseTools($settings['tools']);
                }

                if (isset($settings['prompt'])) {
                    if (is_array($settings['prompt'])) {
                        $agent['prompt_type'] = isset($settings['prompt']['file']) ? 'file' : 'config';
                        if (isset($settings['prompt']['file'])) {
                            $agent['prompt_source'] = basename($settings['prompt']['file']);
                        }
                    } else {
                        $agent['prompt_type'] = 'inline';
                        $agent['prompt_length'] = strlen($settings['prompt']);
                    }
                }

                if (isset($settings['model']['options'])) {
                    $agent['model_options'] = $settings['model']['options'];
                }

                $agent['include_sources'] = $settings['include_sources'] ?? false;
            }

            $agents[] = $agent;
        }

        return $agents;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function detectStores(array $config, bool $includeDetails): array
    {
        $stores = [];
        $storeConfig = $config['store'] ?? [];

        foreach ($storeConfig as $type => $instances) {
            foreach ($instances as $name => $settings) {
                $store = [
                    'name' => $name,
                    'type' => $type,
                ];

                if ($includeDetails && isset($settings['collection'])) {
                    $store['collection'] = $settings['collection'];
                }

                $stores[] = $store;
            }
        }

        return $stores;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function detectTools(array $config, bool $includeDetails): array
    {
        $tools = [];
        $agentConfig = $config['agent'] ?? [];
        $toolsIndex = [];

        foreach ($agentConfig as $agentName => $settings) {
            if (isset($settings['tools']) && is_array($settings['tools'])) {
                foreach ($settings['tools'] as $tool) {
                    $toolInfo = $this->parseTool($tool);
                    $toolKey = $toolInfo['class'] ?? $toolInfo['agent'] ?? $toolInfo['name'] ?? 'unknown';

                    if (!isset($toolsIndex[$toolKey])) {
                        $toolInfo['used_by_agents'] = [$agentName];
                        $toolsIndex[$toolKey] = $toolInfo;
                    } else {
                        $toolsIndex[$toolKey]['used_by_agents'][] = $agentName;
                    }
                }
            }
        }

        return array_values($toolsIndex);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function detectMultiAgentSetups(array $config, bool $includeDetails): array
    {
        $setups = [];
        $multiAgentConfig = $config['multi_agent'] ?? [];

        foreach ($multiAgentConfig as $name => $settings) {
            $setup = [
                'name' => $name,
                'orchestrator' => $settings['orchestrator'] ?? null,
                'fallback' => $settings['fallback'] ?? null,
            ];

            if ($includeDetails && isset($settings['handoffs'])) {
                $setup['handoffs'] = $settings['handoffs'];
                $setup['handoff_count'] = count($settings['handoffs']);
            }

            $setups[] = $setup;
        }

        return $setups;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function detectIndexers(array $config, bool $includeDetails): array
    {
        $indexers = [];
        $indexerConfig = $config['indexer'] ?? [];

        foreach ($indexerConfig as $name => $settings) {
            $indexer = [
                'name' => $name,
                'loader' => $this->extractClassName($settings['loader'] ?? 'unknown'),
                'source' => $settings['source'] ?? null,
            ];

            if ($includeDetails) {
                $indexer['has_filters'] = !empty($settings['filters']);
                $indexer['has_transformers'] = !empty($settings['transformers']);
                $indexer['vectorizer'] = $settings['vectorizer'] ?? null;
                $indexer['store'] = $settings['store'] ?? null;

                if (!empty($settings['transformers'])) {
                    $indexer['transformers'] = array_map(
                        fn($t) => $this->extractClassName($t),
                        $settings['transformers']
                    );
                }
            }

            $indexers[] = $indexer;
        }

        return $indexers;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function detectRetrievers(array $config, bool $includeDetails): array
    {
        $retrievers = [];
        $retrieverConfig = $config['retriever'] ?? [];

        foreach ($retrieverConfig as $name => $settings) {
            $retriever = [
                'name' => $name,
                'vectorizer' => $settings['vectorizer'] ?? null,
                'store' => $settings['store'] ?? null,
            ];

            $retrievers[] = $retriever;
        }

        return $retrievers;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function detectVectorizers(array $config, bool $includeDetails): array
    {
        $vectorizers = [];
        $vectorizerConfig = $config['vectorizer'] ?? [];

        foreach ($vectorizerConfig as $name => $settings) {
            $vectorizer = [
                'name' => $name,
                'platform' => $settings['platform'] ?? 'unknown',
                'model' => $settings['model'] ?? 'unknown',
            ];

            $vectorizers[] = $vectorizer;
        }

        return $vectorizers;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function detectInstalledPackages(array $composer): array
    {
        $packages = [];
        $allDeps = array_merge(
            $composer['require'] ?? [],
            $composer['require-dev'] ?? []
        );

        foreach ($allDeps as $package => $version) {
            if (str_starts_with($package, 'symfony/ai-')) {
                $packages[] = [
                    'name' => $package,
                    'version' => $version,
                    'type' => $this->categorizePackage($package),
                ];
            }
        }

        return $packages;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseTools(array $tools): array
    {
        return array_map(fn($tool) => $this->parseTool($tool), $tools);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseTool(mixed $tool): array
    {
        if (is_string($tool)) {
            return [
                'type' => 'service',
                'class' => $this->extractClassName($tool),
                'full_class' => $tool,
            ];
        }

        if (is_array($tool)) {
            if (isset($tool['agent'])) {
                return [
                    'type' => 'sub_agent',
                    'agent' => $tool['agent'],
                    'name' => $tool['name'] ?? null,
                    'description' => $tool['description'] ?? null,
                ];
            }

            return [
                'type' => 'service',
                'service' => $tool['service'] ?? null,
                'name' => $tool['name'] ?? null,
                'description' => $tool['description'] ?? null,
                'method' => $tool['method'] ?? null,
            ];
        }

        return ['type' => 'unknown'];
    }

    private function extractClassName(string $classOrService): string
    {
        $parts = explode('\\', $classOrService);
        return end($parts);
    }

    private function extractEnvVar(string $value): ?string
    {
        if (preg_match('/%env\(([^)]+)\)%/', $value, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function categorizePackage(string $package): string
    {
        return match (true) {
            str_contains($package, 'platform') => 'platform',
            str_contains($package, 'store') => 'store',
            str_contains($package, 'tool') => 'tool',
            str_contains($package, 'bundle') => 'bundle',
            str_contains($package, 'mate') => 'development',
            default => 'other',
        };
    }

    /**
     * @return array<string, int>
     */
    private function generateSummary(array $features): array
    {
        return [
            'total_platforms' => count($features['platforms']),
            'total_agents' => count($features['agents']),
            'total_stores' => count($features['stores']),
            'total_tools' => count($features['tools']),
            'total_multi_agent_setups' => count($features['multi_agent_setups']),
            'total_indexers' => count($features['indexers']),
            'total_retrievers' => count($features['retrievers']),
            'total_vectorizers' => count($features['vectorizers']),
            'total_packages' => count($features['installed_packages']),
        ];
    }
}
