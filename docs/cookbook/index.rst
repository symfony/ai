Cookbook
========

The cookbook contains practical guides and complete examples for common use cases
with Symfony AI. Each guide includes working code, explanations, and best practices.

Getting Started Guides
----------------------

.. toctree::
    :maxdepth: 1

    ai-bundle-fast-track
    tool-calling-with-agents
    dynamic-tools
    human-in-the-loop
    multi-agent-orchestration
    chatbot-with-memory
    context-compression
    rag-implementation
    build-an-mcp-server

Symfony Integration
-------------------

* :doc:`ai-bundle-fast-track` - Wire platforms, agents, tools, and a vector store with the AI Bundle

Agents & Tools
--------------

* :doc:`tool-calling-with-agents` - Let agents call your PHP functions to fetch data or trigger actions
* :doc:`dynamic-tools` - Build a dynamic Toolbox for flexible tool management at runtime
* :doc:`human-in-the-loop` - Implement human-in-the-loop confirmation for tool execution
* :doc:`multi-agent-orchestration` - Route questions to specialist agents with an orchestrator

Memory & Context Management
---------------------------

* :doc:`chatbot-with-memory` - Build chatbots that remember user preferences and conversation history
* :doc:`context-compression` - Manage long conversations with automatic context compression

Platform Features
-----------------

Streaming, multi-modal input, structured output, and model catalogs are documented in the
:doc:`Platform component </components/platform>` reference.

Retrieval Augmented Generation
------------------------------

* :doc:`rag-implementation` - Implement complete RAG systems with vector stores and semantic search

Model Context Protocol
----------------------

* :doc:`build-an-mcp-server` - Expose tools, prompts, and resources to AI assistants over MCP
