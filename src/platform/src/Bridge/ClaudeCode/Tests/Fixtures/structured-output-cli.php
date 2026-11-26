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

$arguments = $argv ?? [];
$option = array_search('--json-schema', $arguments, true);
if (false === $option || !isset($arguments[$option + 1]) || in_array('--response-format', $arguments, true)) {
    fwrite(\STDERR, "Expected an inline JSON Schema.\n");
    exit(1);
}

$schema = json_decode($arguments[$option + 1], true, flags: \JSON_THROW_ON_ERROR);
if ('string' !== ($schema['properties']['decision']['type'] ?? null)) {
    fwrite(\STDERR, "Unexpected JSON Schema.\n");
    exit(1);
}

echo <<<'EVENTS'
{"type":"stream_event","event":{"type":"message_start"}}
{"type":"stream_event","event":{"type":"content_block_delta","delta":{"type":"text_delta","text":"I finished the review."}}}
{"type":"stream_event","event":{"type":"message_stop"}}
EVENTS;
echo "\n", json_encode([
    'type' => 'result',
    'subtype' => 'success',
    'is_error' => false,
    'result' => 'I finished the review.',
    'structured_output' => isset($schema['properties']['details'])
        ? ['decision' => 'approve', 'details' => (object) [], 'scores' => []]
        : ['decision' => 'approve'],
], \JSON_THROW_ON_ERROR);
