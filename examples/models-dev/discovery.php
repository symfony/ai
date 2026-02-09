<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\AI\Platform\Bridge\ModelsDev\ModelCatalog;
use Symfony\AI\Platform\Bridge\ModelsDev\ProviderRegistry;
use Symfony\AI\Platform\Capability;

require_once dirname(__DIR__).'/bootstrap.php';

$registry = new ProviderRegistry();

// Discover all available providers
$providers = $registry->getProviderIds();
echo 'Total providers: '.count($providers)."\n\n";

// Sample some popular providers
$samples = ['openai', 'anthropic', 'deepseek', 'groq', 'mistral', 'cerebras'];
echo "Sample providers:\n";
foreach ($samples as $providerId) {
    if ($registry->has($providerId)) {
        $name = $registry->getProviderName($providerId);
        $api = $registry->getApiBaseUrl($providerId);
        $apiInfo = $api ?: '(requires baseUrl parameter)';
        echo sprintf("  - %-15s %-30s %s\n", $providerId, $name, $apiInfo);
    }
}

// Explore models from a specific provider
$catalog = new ModelCatalog('anthropic');
$models = $catalog->getModels();

echo 'Anthropic models ('.count($models)." total):\n";
foreach (array_slice(array_keys($models), 0, 5) as $modelId) {
    $model = $catalog->getModel($modelId);
    echo "  - $modelId\n";
    echo '    Capabilities: ';
    $caps = [];
    if ($model->supports(Capability::INPUT_MESSAGES)) {
        $caps[] = 'chat';
    }
    if ($model->supports(Capability::EMBEDDINGS)) {
        $caps[] = 'embeddings';
    }
    if ($model->supports(Capability::TOOL_CALLING)) {
        $caps[] = 'tools';
    }
    echo implode(', ', $caps)."\n";
}

echo "All model information comes from the community-maintained models.dev registry!\n";
