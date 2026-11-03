# Symfony Ollama Examples

This directory contains various examples of how to use the Symfony AI with [Ollama](https://ollama.com/).

## Getting started

To get started with Ollama please check their [Quickstart guide](https://github.com/ollama/ollama/blob/main/README.md#quickstart).

## Running the examples

To run the examples you will need to download the following models:
- [Llama 3.2](https://ollama.com/library/llama3.2)
- [nomic-embed-text](https://ollama.com/library/nomic-embed-text)
- [Gemma 3](https://ollama.com/library/gemma3)
- [Phonon](https://ollama.com/library/phonon) (text-to-speech, Ollama >= 0.40.9)

You can do this by running the following commands:
```bash
ollama pull llama3.2
ollama pull nomic-embed-text
ollama pull gemma3
ollama pull phonon
```

Then you can start the Ollama server by running:
```bash
ollama serve
```

### Configuration
The examples expect Ollama to run on `localhost:11434` and use the `llama3.2`, `nomic-embed-text` and `gemma3` models.
If your Ollama instance requires authentication, set `OLLAMA_API_KEY` in your `.env.local` file.

You can find more models in the [Ollama model library](https://ollama.com/library).
