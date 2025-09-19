<p align="center"><a href="https://symfony.com" target="_blank">
    <img src="https://symfony.com/logos/symfony_dynamic_01.svg" alt="Symfony Logo">
</a></p>

<h3 align="center">
    Symfony AI
</h3>

Symfony AI is a set of components that integrate AI capabilities into PHP applications.

## Components & Bundles

Symfony AI consists of several lower and higher level **components** and the respective integration **bundles**:

* **Components**
  * **[Platform](src/platform/README.md)**: A unified interface to various AI platforms like OpenAI, Anthropic, Azure, Gemini, VertexAI, and more.
  * **[Agent](src/agent/README.md)**: Framework for building AI agents that can interact with users and perform tasks.
  * **[Store](src/store/README.md)**: Data storage abstraction with indexing and retrieval for AI applications.
* **Bundles**
  * **[AI Bundle](src/ai-bundle/README.md)**: Symfony integration for AI Platform, Store and Agent components.
  * **[MCP Bundle](src/mcp-bundle/README.md)**: Symfony integration for official MCP SDK, allowing them to act as MCP servers or clients.

## Examples & Demo

To get started with Symfony AI, you can either check out the [examples](./examples) to see how to use the
components in smaller snippets, or you can run the [demo application](./demo) to see the components work together in a
full Symfony web application.

## Sponsor

Help Symfony by [sponsoring](https://symfony.com/sponsor) its development!

## Contributing

Thank you for considering contributing to Symfony AI! You can find the [contribution guide here](CONTRIBUTING.md).

## Fixture Licenses

For testing multi-modal features, the repository contains binary media content, with the following owners and licenses:

* `tests/Fixture/image.jpg`: Chris F., Creative Commons, see [pexels.com](https://www.pexels.com/photo/blauer-und-gruner-elefant-mit-licht-1680755/)
* `tests/Fixture/audio.mp3`: davidbain, Creative Commons, see [freesound.org](https://freesound.org/people/davidbain/sounds/136777/)
* `tests/Fixture/document.pdf`: Chem8240ja, Public Domain, see [Wikipedia](https://en.m.wikipedia.org/wiki/File:Re_example.pdf)
