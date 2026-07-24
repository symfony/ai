ModelsLab Bridge for Symfony AI
================================

Provides [ModelsLab](https://modelslab.com) platform integration for Symfony AI,
enabling text-to-image generation with 200,000+ models including Flux, Stable Diffusion XL,
and thousands of community fine-tunes.

## Installation

```bash
composer require symfony/ai-modelslab-platform
```

Get an API key at [modelslab.com/dashboard/api-keys](https://modelslab.com/dashboard/api-keys).

## Usage

```php
use Symfony\AI\Platform\Bridge\ModelsLab\ModelCatalog;
use Symfony\AI\Platform\Bridge\ModelsLab\PlatformFactory;

$platform = PlatformFactory::create($_ENV['MODELSLAB_API_KEY']);
$catalog  = new ModelCatalog();

$model  = $catalog->get('flux');
$result = $platform->request($model, 'A serene mountain lake at sunset')->getResult();

file_put_contents('output.jpg', $result->asBinary());
```

## Available Models

| Model ID         | Description                           |
|-----------------|---------------------------------------|
| `flux`          | FLUX.1 — state-of-the-art quality     |
| `flux-pro`      | FLUX.1 Pro — higher fidelity          |
| `sdxl`          | Stable Diffusion XL                   |
| `juggernaut-xl` | Juggernaut XL (photorealistic)        |
| `realvisxl-v4.0`| RealVisXL v4.0 (hyperrealistic)       |
| `stable-diffusion` | Stable Diffusion 1.5               |
| `dreamshaper`   | DreamShaper (artistic)                |

Custom community models are supported via `model_id` — see [modelslab.com](https://modelslab.com).

## Async Pattern

ModelsLab uses an async generation pattern. The client automatically polls the fetch endpoint
when `status: "processing"` is returned, waiting up to 2 minutes.

## Authentication

ModelsLab uses key-in-body authentication:
```json
{ "key": "YOUR_API_KEY", "prompt": "..." }
```

The API key appears in request logs since it is part of the JSON body.
Use environment variables and avoid logging request bodies in production.
