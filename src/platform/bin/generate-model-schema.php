#!/usr/bin/env php
<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Generates models.schema.json from models.php for OpenAI bridge.
 *
 * This script extracts model definitions and generates a JSON Schema
 * that can be used for validation and IDE support.
 */

// Bootstrap autoloader
$autoloadFiles = [
    __DIR__.'/../vendor/autoload.php',
    __DIR__.'/../../../../vendor/autoload.php',
];

$autoloaded = false;
foreach ($autoloadFiles as $autoloadFile) {
    if (file_exists($autoloadFile)) {
        require $autoloadFile;
        $autoloaded = true;
        break;
    }
}

if (!$autoloaded) {
    fwrite(STDERR, "Error: Could not find vendor/autoload.php\n");
    exit(1);
}

$modelsFile = __DIR__.'/../src/Bridge/OpenAi/Resources/models.php';
$schemaFile = __DIR__.'/../src/Bridge/OpenAi/Resources/models.schema.json';

if (!file_exists($modelsFile)) {
    fwrite(STDERR, "Error: models.php not found at: {$modelsFile}\n");
    exit(1);
}

// Load the models array
$models = require $modelsFile;

if (!is_array($models)) {
    fwrite(STDERR, "Error: models.php must return an array\n");
    exit(1);
}

// Extract unique classes
$classes = [];
foreach ($models as $model) {
    if (isset($model['class'])) {
        $classes[$model['class']] = true;
    }
}
$classes = array_keys($classes);
sort($classes);

// Define capability enum values
$capabilities = [
    'INPUT_MESSAGES',
    'INPUT_TEXT',
    'INPUT_IMAGE',
    'INPUT_AUDIO',
    'OUTPUT_TEXT',
    'OUTPUT_IMAGE',
    'OUTPUT_STREAMING',
    'OUTPUT_STRUCTURED',
    'TOOL_CALLING',
];

// Create example from first few models
$exampleModels = array_slice($models, 0, 2, true);
$examples = [];
foreach ($exampleModels as $name => $config) {
    $capabilityNames = [];
    foreach ($config['capabilities'] as $capability) {
        $capabilityNames[] = $capability->name;
    }
    $examples[$name] = [
        'class' => $config['class'],
        'capabilities' => $capabilityNames,
    ];
}

// Build the schema
$schema = [
    '$schema' => 'http://json-schema.org/draft-07/schema#',
    '$id' => 'https://symfony.com/schema/ai/openai-models.json',
    'title' => 'OpenAI Model Catalog Schema',
    'description' => 'JSON Schema for OpenAI model definitions in models.php',
    'type' => 'object',
    'patternProperties' => [
        '^[a-z0-9.-]+$' => [
            '$ref' => '#/definitions/model',
        ],
    ],
    'additionalProperties' => false,
    'definitions' => [
        'model' => [
            'type' => 'object',
            'required' => ['class', 'capabilities'],
            'properties' => [
                'class' => [
                    'type' => 'string',
                    'enum' => $classes,
                    'description' => 'The fully qualified class name that handles this model type',
                ],
                'capabilities' => [
                    'type' => 'array',
                    'items' => [
                        '$ref' => '#/definitions/capability',
                    ],
                    'minItems' => 1,
                    'uniqueItems' => true,
                    'description' => 'Array of capabilities supported by this model',
                ],
            ],
            'additionalProperties' => false,
        ],
        'capability' => [
            'type' => 'string',
            'enum' => $capabilities,
            'description' => 'A capability that the model supports',
        ],
    ],
    'examples' => [$examples],
];

// Generate JSON with pretty printing
$json = json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

if (false === $json) {
    fwrite(STDERR, "Error: Failed to encode JSON schema\n");
    exit(1);
}

// Write to file
if (false === file_put_contents($schemaFile, $json."\n")) {
    fwrite(STDERR, "Error: Failed to write schema file to: {$schemaFile}\n");
    exit(1);
}

echo "✓ Successfully generated models.schema.json\n";
echo "  Models: ".count($models)."\n";
echo "  Classes: ".count($classes)."\n";
echo "  Output: {$schemaFile}\n";

exit(0);
