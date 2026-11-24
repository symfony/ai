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
$option = array_search('--output-schema', $arguments, true);
if (false === $option || !isset($arguments[$option + 1]) || in_array('--response-format', $arguments, true)) {
    fwrite(\STDERR, "Expected a JSON Schema file.\n");
    exit(1);
}

$schema = json_decode(file_get_contents($arguments[$option + 1]), true, flags: \JSON_THROW_ON_ERROR);
if ('string' !== ($schema['properties']['name']['type'] ?? null) || ['name'] !== ($schema['required'] ?? null)) {
    fwrite(\STDERR, "Unexpected JSON Schema.\n");
    exit(1);
}

echo <<<'EVENTS'
{"type":"turn.started"}
{"type":"item.completed","item":{"type":"agent_message","text":"Let me check."}}
{"type":"item.completed","item":{"type":"agent_message","text":"{\"name\":\"Valencia\"}"}}
{"type":"turn.completed","usage":{"input_tokens":12,"output_tokens":7}}
EVENTS;
