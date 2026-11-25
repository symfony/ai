CHANGELOG
=========

1.0
---

 * Add the `DeepSeek-V4.1-Flash-1017`, `gemma-4-26b-a4b`, `laguna-xs-2.1`, `nemotron3-nano-30b`, `nemotron3-omni-30b`, `Olala-v1.0-mini-7A1B`, `Olala-v1.0-summarise`, `Olala-v1.0-translate`, `qwen3-vl-30b-a3b`, `Qwen3.5-397B-A17B`, `Qwen3.5-9B`, `Qwen3.6-27B`, `qwen3.6-35b-a3b`, `Qwen3.8-27B` and `Qwen3-Embedding-8B` models
 * Add the `thinking` capability to the models of the catalog that reason
 * Add speech-to-text support for the `whisper-large-v3` and `whisper-large-v3-turbo` models
 * Add text-to-speech support for the `nvr-tts-de-de`, `nvr-tts-en-us`, `nvr-tts-es-es` and `nvr-tts-it-it` NVIDIA Riva voices
 * Add image generation support for the `stable-diffusion-xl-base-v10` model
 * Add decision support for the `clef-flash` model, which requires the `symfony/ai-decision-platform` package
 * Remove the models OVHcloud no longer serves: `Qwen3-Coder-30B-A3B-Instruct`, `Qwen3-32B`, `Llama-3.1-8B-Instruct`, `Mistral-7B-Instruct-v0.3` and `Mistral-Nemo-Instruct-2407`

0.8
---

 * [BC BREAK] Rename `PlatformFactory` to `Factory` with explicit `createProvider()` and `createPlatform()` methods

0.4
---

 * Add the bridge
