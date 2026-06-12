<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/*
 * Regenerates the static model lists of the platform ModelCatalog classes.
 *
 * Per-provider bridges are sourced from models.dev (https://models.dev/api.json), whose model ids
 * match the bridge catalog keys and which exposes reasoning/tool_call/modalities. The mapping reuses
 * Symfony\AI\Platform\Bridge\ModelsDev\CapabilityMapper.
 *
 * The OpenRouter bridge is sourced from the OpenRouter API, reusing the modality mapping already in
 * Symfony\AI\Platform\Bridge\OpenRouter\ModelApiCatalog.
 *
 * Add-only: hand-curated entries win, so only genuinely new models are appended. Removals and
 * capability corrections stay manual. The content between the "// STATIC LIST START" and
 * "// STATIC LIST END" markers is rewritten; everything else in the file is left untouched.
 *
 * Usage:
 *   php dev/update-model-catalogs.php [--dry-run] [<bridge>]
 *
 * @author Kévin Dunglas <kevin@les-tilleuls.coop>
 */

use Symfony\AI\Platform\Bridge\ModelsDev\CapabilityMapper;
use Symfony\AI\Platform\Bridge\OpenRouter\ModelApiCatalog;
use Symfony\AI\Platform\Capability;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\SingleCommandApplication;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

require __DIR__.'/../vendor/autoload.php';

const PLATFORM_DIR = __DIR__.'/..';
const BRIDGE_NS = 'Symfony\\AI\\Platform\\Bridge\\';

// Routing models injected at runtime by AbstractOpenRouterModelCatalog; they must stay out of the
// static list.
const OPENROUTER_ROUTING_KEYS = ['openrouter/auto', 'openrouter/bodybuilder', 'openrouter/free', '@preset'];

/**
 * Per-provider bridges sourced from models.dev.
 *
 * "provider" is the models.dev top-level key. "default" is the model class used for chat/completion
 * models. "rules" pick a different class for matching models (evaluated in order). When "single" is
 * true the bridge has only one class, so embedding models from the source are skipped (they cannot
 * be represented).
 *
 * @var array<string, array{provider: string, default: class-string, single: bool, rules: list<array{0: callable(array): bool, 1: class-string}>}>
 */
$modelsDevBridges = [
    'Anthropic' => [
        'provider' => 'anthropic',
        'default' => BRIDGE_NS.'Anthropic\\Claude',
        'single' => true,
        'rules' => [],
    ],
    'OpenAi' => [
        'provider' => 'openai',
        'default' => BRIDGE_NS.'OpenAi\\Gpt',
        'single' => false,
        'rules' => [
            [static fn (array $m): bool => CapabilityMapper::isEmbeddingModel($m), BRIDGE_NS.'OpenAi\\Embeddings'],
        ],
    ],
    'Gemini' => [
        'provider' => 'google',
        'default' => BRIDGE_NS.'Gemini\\Gemini',
        'single' => false,
        'rules' => [
            [static fn (array $m): bool => CapabilityMapper::isEmbeddingModel($m), BRIDGE_NS.'Gemini\\Embeddings'],
        ],
    ],
    'Mistral' => [
        'provider' => 'mistral',
        'default' => BRIDGE_NS.'Mistral\\Mistral',
        'single' => false,
        'rules' => [
            [static fn (array $m): bool => str_contains($m['id'] ?? '', 'ocr'), BRIDGE_NS.'Mistral\\Ocr'],
            [static fn (array $m): bool => CapabilityMapper::isEmbeddingModel($m), BRIDGE_NS.'Mistral\\Embeddings'],
        ],
    ],
    'DeepSeek' => [
        'provider' => 'deepseek',
        'default' => BRIDGE_NS.'DeepSeek\\DeepSeek',
        'single' => true,
        'rules' => [],
    ],
    'Cohere' => [
        'provider' => 'cohere',
        'default' => BRIDGE_NS.'Cohere\\Cohere',
        'single' => false,
        'rules' => [
            [static fn (array $m): bool => CapabilityMapper::isEmbeddingModel($m), BRIDGE_NS.'Cohere\\Embeddings'],
            [static fn (array $m): bool => str_contains($m['id'], 'rerank'), BRIDGE_NS.'Cohere\\Reranker'],
        ],
    ],
    'Perplexity' => [
        'provider' => 'perplexity',
        'default' => BRIDGE_NS.'Perplexity\\Perplexity',
        'single' => true,
        'rules' => [],
    ],
    'Cerebras' => [
        'provider' => 'cerebras',
        'default' => BRIDGE_NS.'Cerebras\\Model',
        'single' => true,
        'rules' => [],
    ],
];

(new SingleCommandApplication())
    ->setName('Update Model Catalogs')
    ->addArgument('bridge', InputArgument::OPTIONAL, 'Limit the update to a single bridge (e.g. "Anthropic", "OpenRouter")')
    ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Print what would change without writing any file')
    ->setCode(static function (InputInterface $input, OutputInterface $output) use ($modelsDevBridges): int {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $filter = $input->getArgument('bridge');

        // Retries transient 5xx/network failures: this runs unattended on a schedule.
        $http = new RetryableHttpClient(HttpClient::create());
        $modelsDevData = null;
        $failed = false;

        foreach ($modelsDevBridges as $bridge => $config) {
            if (null !== $filter && strtolower($filter) !== strtolower($bridge)) {
                continue;
            }

            $modelsDevData ??= fetchModelsDev($http);
            $providerData = $modelsDevData[$config['provider']]['models'] ?? null;
            if (null === $providerData) {
                $io->warning(sprintf('%s: provider "%s" absent from models.dev, skipped.', $bridge, $config['provider']));
                continue;
            }

            $fresh = [];
            foreach ($providerData as $id => $model) {
                $model = normalizeModelData((string) $id, $model);
                if ($config['single'] && CapabilityMapper::isEmbeddingModel($model)) {
                    continue;
                }

                $fresh[(string) $id] = [
                    'class' => pickClass($model, $config),
                    'capabilities' => CapabilityMapper::map($model),
                ];
            }

            $failed = !writeCatalog($io, $bridge, $fresh, true, $dryRun) || $failed;
        }

        if (null === $filter || 'openrouter' === strtolower($filter)) {
            $openRouter = fetchOpenRouter($http);
            $failed = !writeCatalog($io, 'OpenRouter', $openRouter, true, $dryRun, OPENROUTER_ROUTING_KEYS) || $failed;
        }

        if ($failed) {
            $io->error('One or more catalogs could not be updated.');

            return Command::FAILURE;
        }

        $io->success($dryRun ? 'Dry-run complete.' : 'Catalogs updated.');

        return Command::SUCCESS;
    })
    ->run();

/**
 * @return array<string, mixed>
 */
function fetchModelsDev(HttpClientInterface $http): array
{
    return $http->request('GET', 'https://models.dev/api.json')->toArray();
}

/**
 * Reuses the OpenRouter API → capability mapping by exposing the protected fetch methods.
 *
 * @return array<string, array{class: class-string, capabilities: list<Capability>}>
 */
function fetchOpenRouter(HttpClientInterface $http): array
{
    // ModelApiCatalog is final; reflection reuses its protected modality→capability mapping without
    // duplicating it. Its public getModels() also injects the parent routing models (openrouter/auto
    // etc.) which must stay out of the static list, hence calling the fetch methods directly.
    $catalog = new ModelApiCatalog($http);

    $models = [];
    foreach (['fetchRemoteModels', 'fetchRemoteEmbeddings'] as $method) {
        $ref = new ReflectionMethod($catalog, $method);
        foreach ($ref->invoke($catalog) as $id => $config) {
            $models[$id] = $config;
        }
    }

    return $models;
}

/**
 * Fills in defaults so CapabilityMapper never trips on a missing key.
 *
 * @param array<string, mixed> $model
 *
 * @return array<string, mixed>
 */
function normalizeModelData(string $id, array $model): array
{
    $model['id'] = $id;
    $model['tool_call'] ??= false;
    $model['reasoning'] ??= false;
    $model['attachment'] ??= false;
    $model['modalities'] ??= [];
    $model['modalities']['input'] ??= [];
    $model['modalities']['output'] ??= [];

    return $model;
}

/**
 * @param array<string, mixed>                                                                                      $model
 * @param array{default: class-string, single: bool, rules: list<array{0: callable(array): bool, 1: class-string}>} $config
 *
 * @return class-string
 */
function pickClass(array $model, array $config): string
{
    if (!$config['single']) {
        foreach ($config['rules'] as [$matches, $class]) {
            if ($matches($model)) {
                return $class;
            }
        }
    }

    return $config['default'];
}

/**
 * Merges fresh entries with the curated baseline (when $merge), renders the PHP array literal and
 * rewrites it between the STATIC LIST markers.
 *
 * @param array<string, array{class: class-string, capabilities: list<Capability>}> $fresh
 * @param list<string>                                                              $excludeKeys
 */
function writeCatalog(SymfonyStyle $io, string $bridge, array $fresh, bool $merge, bool $dryRun, array $excludeKeys = []): bool
{
    $file = PLATFORM_DIR.'/src/Bridge/'.$bridge.'/ModelCatalog.php';
    $source = file_get_contents($file);
    if (false === $source) {
        $io->error(sprintf('%s: cannot read %s', $bridge, $file));

        return false;
    }

    $catalogClass = BRIDGE_NS.$bridge.'\\ModelCatalog';
    $existing = [];
    if ($merge) {
        /** @var Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog $catalog */
        $catalog = new $catalogClass();
        $existing = $catalog->getModels();
    }

    // Add-only: curated entries win, so hand-tuned capabilities/classes are never overwritten and
    // only genuinely new models are appended.
    $models = $merge ? [...$fresh, ...$existing] : $fresh;
    foreach ($excludeKeys as $key) {
        unset($models[$key]);
    }
    ksort($models);

    $namespace = '';
    if (preg_match('/^namespace (.+);$/m', $source, $m)) {
        $namespace = $m[1];
    }

    $source = ensureUseStatements($source, $namespace, $models);
    $block = renderBlock($models);

    $marked = '~[ ]{8}// STATIC LIST START\n.*?\n[ ]{8}// STATIC LIST END~s';
    $bare = '~[ ]{8}\$defaultModels = \[.*?\n[ ]{8}\];~s';

    if (preg_match($marked, $source)) {
        $new = preg_replace($marked, $block, $source, 1);
    } elseif (preg_match($bare, $source)) {
        $new = preg_replace($bare, $block, $source, 1);
    } else {
        $io->error(sprintf('%s: no $defaultModels block found.', $bridge));

        return false;
    }

    $added = array_diff(array_keys($models), array_keys($existing));
    $summary = sprintf('%s: %d models (%d new)', $bridge, count($models), count($added));

    if ($dryRun) {
        $io->writeln('<comment>[dry-run]</comment> '.$summary);
        $io->listing(array_values($added));

        return true;
    }

    if ($new === $source) {
        $io->writeln('<info>[unchanged]</info> '.$summary);

        return true;
    }

    file_put_contents($file, $new);
    $io->writeln('<info>[written]</info> '.$summary);

    return true;
}

/**
 * Ensures every model class has a matching use statement (or is in the file namespace).
 *
 * @param array<string, array{class: class-string, capabilities: list<Capability>}> $models
 */
function ensureUseStatements(string $source, string $namespace, array $models): string
{
    preg_match_all('/^use (.+);$/m', $source, $m);
    $imported = $m[1];

    $missing = [];
    foreach ($models as $config) {
        $fqcn = ltrim($config['class'], '\\');
        if (in_array($fqcn, $imported, true) || in_array($fqcn, $missing, true)) {
            continue;
        }
        if (substr($fqcn, 0, strrpos($fqcn, '\\')) === $namespace) {
            continue; // same namespace, no import needed
        }
        $missing[] = $fqcn;
    }

    if ([] === $missing) {
        return $source;
    }

    $lines = array_map(static fn (string $c): string => 'use '.$c.';', $missing);

    // Insert after the last existing use statement.
    return preg_replace('/^(use .+;\n)(?!use )/m', '$1'.implode("\n", $lines)."\n", $source, 1);
}

/**
 * @param array<string, array{class: class-string, capabilities: list<Capability>}> $models
 */
function renderBlock(array $models): string
{
    $out = "        // STATIC LIST START\n";
    $out .= "        // This list is generated from external metadata. Run dev/update-model-catalogs.php to refresh it.\n";
    $out .= "        \$defaultModels = [\n";

    foreach ($models as $id => $config) {
        $class = substr($config['class'], strrpos($config['class'], '\\') + 1);
        $out .= '            '.exportString((string) $id)." => [\n";
        $out .= '                '."'class' => ".$class."::class,\n";
        $out .= "                'capabilities' => [\n";
        foreach ($config['capabilities'] as $capability) {
            $name = $capability instanceof Capability ? $capability->name : (string) $capability;
            $out .= '                    Capability::'.$name.",\n";
        }
        $out .= "                ],\n";
        $out .= "            ],\n";
    }

    $out .= "        ];\n";
    $out .= '        // STATIC LIST END';

    return $out;
}

function exportString(string $value): string
{
    return "'".strtr($value, ['\\' => '\\\\', "'" => "\\'"])."'";
}
