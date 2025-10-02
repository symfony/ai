# Platform as Tool

This example demonstrates how to use one AI platform as a tool within an agent powered by another platform.

## Concept

Just like agents can use other agents as tools, platforms can also be wrapped as tools. This enables powerful compositions where:

- An OpenAI-based agent can use ElevenLabs for speech-to-text
- An Anthropic-based agent can use OpenAI's DALL-E for image generation
- Any platform's specialized capabilities can be leveraged by agents using different platforms

## Example: Speech-to-Text with ElevenLabs

The `platform-as-tool.php` example shows an OpenAI agent that uses ElevenLabs' speech-to-text capabilities:

```php
use Symfony\AI\Agent\Toolbox\Tool\Platform;

// Create ElevenLabs platform
$elevenLabsPlatform = ElevenLabsPlatformFactory::create(
    apiKey: env('ELEVEN_LABS_API_KEY'),
    httpClient: http_client()
);

// Wrap it as a tool
$speechToText = new Platform($elevenLabsPlatform, 'scribe_v1');

// Add to agent's toolbox
$toolbox = new Toolbox([$speechToText]);
```

## AI Bundle Configuration

In a Symfony application using the AI Bundle, you can configure platforms as tools:

```yaml
ai:
    platform:
        openai:
            api_key: '%env(OPENAI_API_KEY)%'
        elevenlabs:
            api_key: '%env(ELEVEN_LABS_API_KEY)%'

    agent:
        my_agent:
            model: 'gpt-4o-mini'
            tools:
                # Use platform as tool via configuration
                - platform: 'elevenlabs'
                  model: 'scribe_v1'
                  name: 'transcribe_audio'
                  description: 'Transcribes audio files to text'
```

Or define it as a service:

```yaml
services:
    app.tool.elevenlabs_transcription:
        class: 'Symfony\AI\Agent\Toolbox\Tool\Platform'
        arguments:
            $platform: '@ai.platform.elevenlabs'
            $model: 'scribe_v1'
            $options: []
```

## Use Cases

1. **Speech-to-Text**: Use ElevenLabs or Whisper for transcription while using another platform for reasoning
2. **Image Generation**: Use DALL-E or Stable Diffusion for images while using another platform for chat
3. **Specialized Models**: Leverage platform-specific models (e.g., code generation, embeddings) from any agent
4. **Multi-Modal Workflows**: Combine different platforms' strengths in a single agent workflow

## Benefits

- **Best Tool for the Job**: Choose the best platform for each specific task
- **Flexibility**: Mix and match platforms based on cost, performance, or features
- **Composability**: Build complex AI systems by combining multiple platforms
- **Simplicity**: Use the same tool interface whether wrapping agents or platforms
