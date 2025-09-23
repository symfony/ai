# Symfony Ollama Examples

This directory contains various examples of how to use the Symfony AI with [Ollama](https://ollama.com/).

## Getting started

To get started with Ollama please check their [Quickstart guide](https://github.com/ollama/ollama/blob/main/README.md#quickstart).

## Running the examples

To run the examples you will need to download [Llama 3.2](https://ollama.com/library/llama3.2)
and [nomic-embed-text](https://ollama.com/library/nomic-embed-text) models.

You can do this by running the following commands:
```bash
ollama pull llama3.2
ollama pull nomic-embed-text
```

Then you can start the Ollama server by running:
```bash
ollama serve
```

### Configuration
By default, the examples expect Ollama to be run on `localhost:11434`, but you can customize this in your `.env.local`
file - as well as the models to be used:

For example
```bash
OLLAMA_HOST_URL=http://localhost:11434
OLLAMA_LLM=llama3.2
OLLAMA_EMBEDDINGS=nomic-embed-text
```

You can find more models in the [Ollama model library](https://ollama.com/library).
