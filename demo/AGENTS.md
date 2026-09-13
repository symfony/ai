# AGENTS.md

AI agent guidance for the Symfony AI demo application.

## Project Overview

Symfony 8.0 demo application showcasing the Symfony AI components: RAG over the Symfony blog, streaming chat, multi-agent orchestration, voice/speech, image cropping, webcam video captioning, YouTube transcript Q&A, Wikipedia-grounded answers, a demo MCP server, and the AI Mate development CLI.

## Development Commands

### Setup
```bash
docker compose up -d                          # Start PostgreSQL (pgvector)
composer install
echo "OPENAI_API_KEY='sk-...'" > .env.local   # HUGGINGFACE_API_KEY only if you wire HF agents

# Vector store must be set up before indexing
symfony console ai:store:setup ai.store.postgres.symfony_blog
symfony console ai:store:index blog -vv
symfony console ai:store:retrieve blog "Week of Symfony"

symfony serve -d                              # https://localhost:8000/
```

### Testing and Quality
```bash
vendor/bin/phpunit                            # All tests (config in phpunit.xml)
vendor/bin/phpunit tests/SmokeTest.php        # Single test file
vendor/bin/phpunit --testsuite e2e            # Panther browser tests, local only (see README.md)
vendor/bin/phpstan analyse                    # Static analysis (phpstan.dist.neon)
# There is no enforced formatter in this package — php-cs-fixer is configured at the monorepo root.
```

The `e2e` suite drives all ten use cases in a real browser and asserts the Symfony AI panel of the
profiler (platform calls, tools, tool calls, token usage). It runs the app in the `dev` environment,
calls the AI platforms for real, and is excluded from the default suite — never add it to CI.

### MCP Server
- **Demo MCP server** (`symfony/mcp-bundle`): `symfony console mcp:server` exposes tools/prompts/resources/resource-templates from `src/Mcp/`. Start it manually when demoing. Test with `{"method":"tools/list","jsonrpc":"2.0","id":1}`.

### AI Mate
`symfony/ai-mate` (dev only) is a plain CLI, not a server: run `./vendor/bin/mate tools:list` and `./vendor/bin/mate tools:call <tool>`. Extensions are registered in `mate/extensions.php`, config in `mate/config.php`, custom tools in `mate/src/`.

### Example Console Commands
```bash
symfony console app:blog:stream               # Streams the blog agent to the terminal (src/Blog/Command/StreamCommand.php)
```

## Architecture

### AI Platforms
`config/packages/ai.yaml` registers two platforms: `openai` and `huggingface`. Every agent currently routes to OpenAI — `blog` and `stream` on `gpt-4.1`, everything else (`youtube`, `recipe`, `wikipedia`, `speech`, `orchestrator`, `technical`, `fallback`) on `gpt-5-mini`. Speech also uses OpenAI `whisper-1` for STT and `tts-1` for TTS; the vectorizer uses `text-embedding-ada-002`. Hugging Face is configured but unused by any agent — wire it in explicitly if needed.

The `Video` feature is the exception: `src/Video/TwigComponent.php` calls `PlatformInterface::invoke('gpt-5.2', ...)` directly (no agent in `ai.yaml`, no session, no tools) for one-shot webcam frame captioning.

### Chat Feature Pattern
Most user-facing features under `src/<Feature>/` are a trio:
1. `Chat.php` — loads/saves a `MessageBag` in the session under a feature-specific key (e.g. `blog-chat`), calls the injected agent via `#[Autowire(service: 'ai.agent.<name>')]`, appends the assistant reply.
2. `TwigComponent.php` — a Symfony UX LiveComponent that drives the UI with no custom JS.
3. Agent definition in `config/packages/ai.yaml` (prompt, tools, memory, platform, model).

Features following this trio: `Blog`, `Stream`, `YouTube`, `Recipe`, `Wikipedia`, `Speech`. Exceptions: `Video` (direct platform call, described above) and `Crop` (custom `CropForm` + `ImageCropper` rather than chat). Reset button clears the session key.

### RAG Pipeline (Blog)
Index: RSS feed loader → `TextContainsFilter` (keeps only "Week of Symfony" posts, wired as `app.filter.week_of_symfony`) → `TextSplitTransformer` + `TextTrimTransformer` → OpenAI `text-embedding-ada-002` → pgvector store (`symfony_blog` table, cosine distance).
Retrieval: `SimilaritySearch` is constructed with `$retriever: '@ai.retriever.blog'` in `ai.yaml` services and exposed to the `blog` agent as a tool — the agent decides when to call it.
The `blog` agent also attaches `App\Blog\SymfonyVersionsMemory` under `memory:` — memories are distinct from tools and are merged into context automatically.

### Multi-Agent Orchestration (`support`)
`ai.multi_agent.support` defines an `orchestrator` that hands off to `technical` on keyword match (`bug`, `problem`, `technical`, `error`, `code`, `debug`) and otherwise falls through to `fallback`. All three are OpenAI `gpt-5-mini` single agents under `ai.agent.*`. Routing changes go under `ai.multi_agent`, model/prompt changes under `ai.agent`.

### Speech Agent + Subagent Pattern
The `speech` agent demonstrates two composition features:
- **Speech I/O block** on the agent config (`speech.speech_to_text_platform`, `stt_model`, `text_to_speech_platform`, `tts_model`, `tts_options`) turns an agent into a voice assistant.
- **Subagent as tool**: the `blog` agent is registered on `speech` as a tool named `symfony_blog` via `agent: 'blog'`. Delegate by declaring the subagent in YAML, not by calling another `Chat` class.

### Tools vs. Memory vs. Subagents (all three appear in `ai.yaml`)
- **Tool**: FQCN (e.g. `Symfony\AI\Agent\Bridge\Wikipedia\Wikipedia`), or a `service:` + `method:` entry with `name`/`description`, or an `agent:` entry for a subagent.
- **Memory**: `memory.service: 'App\...'` — a class implementing the memory contract; contributes context, not callable by the model.
- **`tools: false`** disables tool use entirely for the agent.

## Configuration Notes

- `composer.json` pins Symfony recipes to `8.0.*` — new bundles installed via Flex will target 8.0.
- `config/packages/ai.yaml` is the source of truth for agents, models, platforms, and the vector store — trust it over any prose docs if they diverge.
- AI Mate exposes monolog/profiler/container tools. Prefer those over raw `bin/console debug:container`, `tail`, `grep` on `var/log/*`, see `mate/AGENT_INSTRUCTIONS.md`.

<!-- BEGIN AI_MATE_INSTRUCTIONS -->
AI Mate: project diagnostic tools, exposed through the `symfony php vendor/bin/mate` CLI.

- Provenance: this block is generated by `symfony php vendor/bin/mate discover`. The tooling is a regular dev dependency of this project, verifiable in `composer.json` (`symfony/ai-mate`); the binary lives at `vendor/bin/mate`.
- Invocation: always run Mate as `symfony php vendor/bin/mate`; another interpreter reports on a runtime that is not this application's, and Mate refuses to start under one.
- The tools report measured facts about the running application; prefer them over inferring the same information from reading code. They are described in `mate/AGENT_INSTRUCTIONS.md`.
- Discover the tool surface with `symfony php vendor/bin/mate tools:list`, inspect parameters with `tools:inspect <tool>`, and run a tool with `tools:call <tool> --<param>=<value>` (add `--format=json` for machine-readable output).
- Installed extensions: symfony/ai-mate, symfony/ai-monolog-mate-extension, symfony/ai-symfony-mate-extension.
<!-- END AI_MATE_INSTRUCTIONS -->
