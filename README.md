<p align="center"><a href="https://ai.symfony.com" target="_blank">
    <img src="logo.svg" alt="Symfony AI Logo" width="300">
</a></p>

Symfony AI is a set of components that integrate AI capabilities into PHP applications.

## Components & Bundles

Symfony AI consists of several lower and higher level **components** and the respective integration **bundles**:

* **Components**
  * **[Agent](src/agent/README.md)**: Framework for building AI agents that can interact with users and perform tasks.
  * **[Chat](src/chat/README.md)**: A unified interface to send messages to agents and store long-term context.
  * **[Mate](src/mate/README.md)**: MCP development server enabling AI assistants to interact with PHP applications through standardized tools.
  * **[Platform](src/platform/README.md)**: A unified interface to various AI platforms like OpenAI, Anthropic, Azure, Gemini, VertexAI, and more.
  * **[Store](src/store/README.md)**: Data storage abstraction with indexing and retrieval for AI applications.
* **Bundles**
  * **[AI Bundle](src/ai-bundle/README.md)**: Symfony integration for AI Platform, Store and Agent components.
  * **[MCP Bundle](src/mcp-bundle/README.md)**: Symfony integration for official MCP SDK, allowing them to act as MCP servers or clients.

## Examples & Demo

To get started with Symfony AI, you can either check out the [examples](./examples) to see how to use the
components in smaller snippets, or you can run the [demo application](./demo) to see the components work together in a
full Symfony web application.

## Resources

* [Documentation](https://symfony.com/doc/current/ai/index.html)
* [Website](https://ai.symfony.com)

## Sponsor

Help Symfony by [sponsoring](https://symfony.com/sponsor) its development!

## Contributing

Thank you for considering contributing to Symfony AI! You can find the [contribution guide here](CONTRIBUTING.md).
