Hugging Face Platform
=====================

Hugging Face platform bridge for Symfony AI, built on [Hugging Face Inference Providers](https://huggingface.co/docs/inference-providers/index).

```bash
composer require symfony/ai-hugging-face-platform
```

```php
use Symfony\AI\Platform\Bridge\HuggingFace\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

$platform = Factory::createPlatform('hf_your_api_key_here');

$result = $platform->invoke('openai/gpt-oss-120b', new MessageBag(Message::ofUser('Hello!')));
echo $result->asText();
```

See the [Hugging Face documentation of Symfony AI](https://symfony.com/doc/current/ai/components/platform/huggingface.html)
for chat completion, tool calling, the other tasks and the model catalogs.

Hugging Face Documentation
--------------------------

 * [Inference Providers overview](https://huggingface.co/docs/inference-providers/index)
 * [Tasks reference](https://huggingface.co/docs/inference-providers/tasks/index)
 * [Chat completion](https://huggingface.co/docs/inference-providers/tasks/chat-completion)
 * [OpenAI-compatible chat completions endpoint](https://huggingface.co/docs/inference-providers/index#alternative-openai-compatible-chat-completions-endpoint-chat-only)
 * [Function calling](https://huggingface.co/docs/inference-providers/guides/function-calling)
 * [Hub API: listing models](https://huggingface.co/docs/hub/en/api#get-apimodels)
 * [Authentication / access tokens](https://huggingface.co/docs/hub/en/security-tokens)

Model Discovery
---------------

The `ModelListCommand` (`ai:huggingface:model-list`) lists models of the Hugging Face Hub, filtered by
`--provider`, `--task`, `--search` or `--warm`. The `ModelInfoCommand` (`ai:huggingface:model-info <model-name>`)
shows the inference providers of one model. Register both in a Symfony Console application, as in
[`examples/huggingface/_model.php`](https://github.com/symfony/ai/blob/main/examples/huggingface/_model.php).

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/ai/issues) and
   [send Pull Requests](https://github.com/symfony/ai/pulls)
   in the [main Symfony AI repository](https://github.com/symfony/ai)
