# Brand logos

Logos of the platform bridges, referenced as `brands:<name>` by `config/platform_bridges.yaml`.
Brands and logos are trademarks of their respective owners.

* [Lobe Icons](https://github.com/lobehub/lobe-icons) 1.95.1, MIT licence (see `LobeIcons-LICENCE.txt`):
  anthropic, azure, bedrock, cerebras, claude-code, codex, cohere, decart, deepseek, elevenlabs,
  fireworks, gemini, hugging-face, lm-studio, meta, minimax, mistral, ollama, openai, openrouter,
  perplexity, replicate, together, venice, vertex-ai, voyage
* [SVG Logos](https://github.com/gilbarbara/logos) through Iconify, CC0 licence: cartesia, deepgram, docker
* [Simple Icons](https://simpleicons.org), CC0 licence, filled with the brand color: ovh, scaleway
* The mark of the logo in the header of the website of the provider: amazee-ai (amazee.ai, in its
  gradient version) and eden-ai (edenai.co, its white strokes turned into masks)

Files are trimmed of their size, style and title, and their ids are prefixed with the logo name
so that logos sharing a page never collide. Logos with `currentColor` follow the text color, and so
do the brand colors too dark for the dark theme, through `--platform-logo-dark-fill` (cohere,
fireworks, ovh, scaleway).
