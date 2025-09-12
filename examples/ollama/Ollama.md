# Symfony Ollama Examples

This directory contains various examples of how to use the Symfony AI with [Ollama](https://ollama.com/).

## Getting started

To get started with Ollama please check their [Quickstart guide](https://github.com/ollama/ollama/blob/main/README.md#quickstart).

## Running the examples

To run the examples you will need to download [Llama 3.2](https://ollama.com/library/llama3.2)
and [nomic-embed-text](https://ollama.com/library/nomic-embed-text) models.

Once models are downloaded you can run them with
```bash
ollama run <model-name>
```
for example

```bash
ollama run llama3.2
```

#### Configuration
To run Ollama examples you will need to provide a OLLAMA_HOST_URL key in your env.local file.

For example
```bash
OLLAMA_HOST_URL=http://localhost:11434
```
