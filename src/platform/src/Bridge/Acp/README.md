# ACP Platform Bridge

This bridge integrates the [Agent Client Protocol (ACP)](https://agentclientprotocol.com) into the Symfony AI Platform.

## Installation

```bash
composer require symfony/ai-acp-platform
```

## Usage

```php
use Symfony\AI\Platform\Bridge\Acp\Factory;
use Symfony\AI\Platform\Message\Message;

$platform = Factory::createPlatform(
    name: 'my-acp-agent',
    command: $_ENV['ACP_BINARY'] ?? 'opencode acp',
    onStatus: fn(string $status) => echo "[acp] $status\n",
);

$result = $platform->invoke('acp-default', Message::ofUser('List files'), ['stream' => true]);

foreach ($result->asStream() as $delta) {
    // Handle TextDelta, ThinkingDelta, ToolCallStart, ToolInputDelta, ToolCallComplete
}
```

## Configuration

- **`ACP_BINARY`** (env var): Path to the ACP CLI binary (default: `opencode acp`)
- **`ACP_ARGS`** (env var): Additional arguments for the ACP CLI
- **`workingDirectory`**: Working directory for the ACP process
- **`environment`**: Environment variables for the ACP process
- **`protocolVersion`**: ACP protocol version (default: 1)

## Features

- ✅ Text and thinking streaming
- ✅ Tool call support (start, input, complete)
- ✅ Multi-version protocol support (v1, v2 ready)
- ✅ Configurable binary path
- ✅ Session management

## Notes

- ACP v2 is still in draft. This bridge supports v1 and prepares for v2, but v2-specific features are not yet implemented.
- Token usage extraction is not supported in v1 (waiting for stable spec).
- Advanced content types (image, audio, diffs, terminal) are preserved in raw results but not converted to deltas yet.
