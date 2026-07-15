Bedrock Mantle Platform
=======================

AWS Bedrock Mantle platform bridge for Symfony AI.

The [Mantle endpoint](https://docs.aws.amazon.com/bedrock/latest/userguide/bedrock-mantle.html)
exposes OpenAI-compatible APIs (Chat Completions and Responses) for models served through AWS
Bedrock. Unlike the SigV4/SDK-based [Bedrock bridge](../Bedrock) (Nova, Claude, Llama), it speaks
the plain OpenAI wire protocol, so this bridge reuses the [Generic](../Generic) and
[OpenResponses](../OpenResponses) bridges instead of the AWS SDK. The base URL is derived from the
AWS region: `https://bedrock-mantle.<region>.api.aws`.

Chat Completions
----------------

```php
use Symfony\AI\Platform\Bridge\BedrockMantle\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

$platform = Factory::createPlatform(
    apiKey: $_ENV['AWS_BEARER_TOKEN_BEDROCK'],
    region: 'us-west-2',
);

$result = $platform->invoke('openai.gpt-oss-120b', new MessageBag(
    Message::ofUser('What is the capital of France?'),
));

echo $result->asText();
```

When no API key is provided, the bridge falls back to AWS SigV4 authentication using the standard
credential chain (environment variables, shared config/credentials files, instance metadata, etc.):

```php
$platform = Factory::createPlatform(region: 'us-west-2');
```

A custom `AsyncAws\Core\Credentials\CredentialProvider` can also be passed explicitly. Additional
models can be registered by passing them to the `ModelCatalog`.

Responses API
-------------

The Mantle endpoint also exposes the OpenAI-compatible Responses API, which AWS recommends for new
applications and, unlike Chat Completions, surfaces the model's reasoning trace. Use
`Responses\Factory`; it accepts the same authentication options and reuses the
[OpenResponses](../OpenResponses) wire protocol:

```php
use Symfony\AI\Platform\Bridge\BedrockMantle\Responses\Factory;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

$platform = Factory::createPlatform(
    apiKey: $_ENV['AWS_BEARER_TOKEN_BEDROCK'],
    region: 'eu-central-1',
);

$result = $platform->invoke('google.gemma-4-31b', new MessageBag(
    Message::ofUser('What is the capital of France?'),
));

echo $result->asText();
```

The Chat Completions and Responses routes serve different model families; register additional models
through the respective `ModelCatalog`.

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/ai/issues) and
   [send Pull Requests](https://github.com/symfony/ai/pulls)
   in the [main Symfony AI repository](https://github.com/symfony/ai)
