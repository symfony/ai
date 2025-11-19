# HuggingFace Bridge

The HuggingFace bridge provides integration with the [HuggingFace Inference API](https://huggingface.co/docs/inference-api/index),
enabling access to thousands of pre-trained models for various AI tasks including natural language processing, computer
vision, audio processing, and more.

## Features

- **Multi-Provider Support**: Access models through multiple inference providers (HuggingFace Inference, Cerebras, Cohere, Groq, Together, and others)
- **Comprehensive Task Support**: Support for 40+ different AI tasks including:
  - Chat completion and text generation
  - Image classification, object detection, and segmentation
  - Automatic speech recognition and audio classification
  - Text classification, translation, summarization
  - Feature extraction and embeddings
  - And many more...
- **Model Discovery**: Built-in API client to discover and query available models
- **Flexible Input/Output**: Support for text, images, audio, and binary data
- **Type-Safe Results**: Structured result objects for each task type

## Installation

The bridge is included in the `symfony/ai-platform` package. Ensure you have the required dependencies:

```bash
composer require symfony/ai-platform
```

## Quick Start

### Initialize the Platform

```php
use Symfony\AI\Platform\Bridge\HuggingFace\PlatformFactory;
use Symfony\AI\Platform\Bridge\HuggingFace\Provider;

$platform = PlatformFactory::create(
    apiKey: 'hf_your_api_key_here',
    provider: Provider::CEREBRAS, // or other providers
    httpClient: $httpClient  // optional, uses default if not provided
);
```

### Chat Completion Example

```php
use Symfony\AI\Platform\Bridge\HuggingFace\Task;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

$messages = new MessageBag(
    Message::ofUser('Hello, how are you doing today?')
);

$result = $platform->invoke('HuggingFaceH4/zephyr-7b-beta', $messages, [
    'task' => Task::CHAT_COMPLETION,
]);

echo $result->asText();
```

### Image Classification Example

```php
use Symfony\AI\Platform\Bridge\HuggingFace\Task;
use Symfony\AI\Platform\Message\Content\Image;

$image = Image::fromFile('/path/to/image.jpg');

$result = $platform->invoke('google/vit-base-patch16-224', $image, [
    'task' => Task::IMAGE_CLASSIFICATION,
]);

$classifications = $result->asObject();
foreach ($classifications as $classification) {
    echo $classification->label . ': ' . $classification->score . PHP_EOL;
}
```

### Feature Extraction (Embeddings)

```php
$result = $platform->invoke('sentence-transformers/all-MiniLM-L6-v2', 'Hello world', [
    'task' => Task::FEATURE_EXTRACTION,
]);

$vector = $result->asVector();
```

## Providers

The bridge supports multiple inference providers. For a complete list of available providers and their constants, see
the [`Provider` interface](./Provider.php).

Specify a provider when creating the platform:

```php
use Symfony\AI\Platform\Bridge\HuggingFace\Provider;

$platform = PlatformFactory::create(
    apiKey: 'your_api_key',
    provider: Provider::GROQ,
);
```

## Supported Tasks

The bridge supports 40+ AI tasks across multiple categories including:

- **Natural Language Processing**: Chat completion, text generation, classification, translation, summarization, embeddings, and more
- **Computer Vision**: Image classification, object detection, segmentation, depth estimation, and more
- **Audio**: Speech recognition, audio classification, text-to-speech, and more
- **Multimodal**: Visual question answering, document understanding, and more

For the complete list of supported tasks and their constants, see the [`Task` interface](./Task.php).

## Model Discovery

The bridge includes commands to discover available models without using inference credits. They help to research and use
models, and can be registered in a Symfony Console application.

### Command-Line Interface

The HuggingFace bridge provides two console commands for model discovery:

#### List Models

List available models with optional filtering:

```bash
ai:huggingface:model-list [options]
```

Options:
- `--provider, -p`: Filter by inference provider (e.g., `inference`)
- `--task, -t`: Filter by task type (e.g., `text-generation`)
- `--search, -s`: Search term to filter models
- `--warm, -w`: Only list models with warm inference (ready without cold start)

Examples:
```bash
# List all text generation models
ai:huggingface:model-list --task=text-generation

# List warm models for a specific provider
ai:huggingface:model-list --provider=hf-inference --warm

# Search for specific models
ai:huggingface:model-list --search=llama
```

#### Get Model Information

Retrieve detailed information about a specific model:

```bash
ai:huggingface:model-info <model-name>
```

Examples:
```bash
# Get information about a specific model
ai:huggingface:model-info meta-llama/Llama-2-7b-chat
ai:huggingface:model-info gpt2
```

Output includes:
- Model ID, downloads, and community likes
- Task type (pipeline tag)
- Inference status (warm/cold)
- Inference provider mappings and availability

## Options and Configuration

When invoking the platform, you can pass task-specific options:

```php
$result = $platform->invoke('model/id', $input, [
    'task' => Task::TEXT_GENERATION,
    'temperature' => 0.7,
    'top_p' => 0.9,
    'top_k' => 50,
    'max_new_tokens' => 256,
    'repetition_penalty' => 1.2,
    // ... other provider-specific parameters
]);
```

You can also override the provider per request:

```php
$result = $platform->invoke('model/id', $input, [
    'task' => Task::CHAT_COMPLETION,
    'provider' => Provider::GROQ,  // Override default provider
]);
```
