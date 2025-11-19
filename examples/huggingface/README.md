# Symfony Hugging Face Examples

This directory contains various examples of how to use the Symfony AI with [Hugging Face](https://huggingface.co/)
and sits on top of the [Hugging Face Inference API](https://huggingface.co/inference-api).

The Hugging Face Hub provides access to a wide range of pre-trained open source models for various AI tasks, which you
can directly use via Symfony AI's Hugging Face Platform Bridge.

## Getting Started

Hugging Face offers a free tier for their Inference API, which you can use to get started. Therefore, you need to create
an account on [Hugging Face](https://huggingface.co/join), generate an
[access token](https://huggingface.co/settings/tokens), and add it to your `.env.local` file in the root of the
examples' directory as `HUGGINGFACE_KEY`.

```bash
echo 'HUGGINGFACE_KEY=hf_your_access_key' >> .env.local
```

Different to other platforms, Hugging Face provides close to 50.000 models for various AI tasks, which enables you to
easily try out different, specialized models for your use case. Common use cases can be found in this example directory.

## Running the Examples

You can run an example by executing the following command:

```bash
# Run all example with runner:
./runner huggingface

# Or run a specific example standalone, e.g., object detection:
php huggingface/object-detection.php
```

## Available Models

When running the examples, you might experience that some models are not available, and you encounter an error like:

```
Model, provider or task not found (404).
```

This can happen due to pre-selected models in the examples not being available anymore or not being "warmed up" on
Hugging Face's side. You can change the model used in the examples by updating the model name in the example script.

To find available models for a specific task, you can check out the [Hugging Face Model Hub](https://huggingface.co/models)
and filter by the desired task, or you can use the `huggingface/_model-listing.php` script.

### Listing Available Models

List _all_ models:

```bash
php huggingface/_model.php ai:huggingface:model-list
```
(This is limited to 1000 results by default.)

Limit models to a specific _task_, e.g., object-detection:

```bash
php huggingface/_model.php ai:huggingface:model-list --task=object-detection
```

Limit models to a specific _provider_, e.g., "hf-inference":

```bash
# Single provider:
php huggingface/_model.php ai:huggingface:model-list --provider=hf-inference

# Multiple providers:
php huggingface/_model.php ai:huggingface:model-list --provider=sambanova,novita
```

Search for models matching a specific term, e.g., "gpt":

```bash
php huggingface/_model.php ai:huggingface:model-list --search=gpt
```

Limit models to currently warm models:

```bash
php huggingface/_model.php ai:huggingface:model-list --warm
```

You can combine task and provider filters, task and warm filters, but not provider and warm filters.

```bash
# Combine provider and task:
php huggingface/_model.php ai:huggingface:model-list --provider=hf-inference --task=object-detection

# Combine task and warm:
php huggingface/_model.php ai:huggingface:model-list --task=object-detection --warm

# Search for warm gpt model for text-generation:
php huggingface/_model.php ai:huggingface:model-list --warm --task=text-generation --search=gpt
```

### Model Information

To get detailed information about a specific model, you can use the `huggingface/_model-info.php` script:

```bash
php huggingface/_model.php ai:huggingface:model-info google/vit-base-patch16-224

Hugging Face Model Information
==============================

 Model: google/vit-base-patch16-224
 ----------- -----------------------------
  ID          google/vit-base-patch16-224
  Downloads   2985836
  Likes       889
  Task        image-classification
  Warm        yes
 ----------- -----------------------------

 Inference Provider:
 ----------------- -----------------------------
  Provider          hf-inference
  Status            live
  Provider ID       google/vit-base-patch16-224
  Task              image-classification
  Is Model Author   no
 ----------------- -----------------------------
```

Important to understand is what you can use a model for and its availability on different providers.
