# AI Mate Agent Instructions

This file is managed by `mate discover`.
Run `vendor/bin/mate discover` after installing, removing, or updating Mate extensions.

Mate exposes project-aware development tools through the `vendor/bin/mate` CLI. Prefer these
tools over raw shell commands when they cover what you need:

- `vendor/bin/mate tools:list` — list the available tools
- `vendor/bin/mate tools:inspect <tool>` — show a tool's parameters and JSON input schema
- `vendor/bin/mate tools:call <tool> --<param>=<value>` — run a tool
- `vendor/bin/mate resources:read <uri>` — read a resource by URI

Add `--format=json` to any command for machine-readable output.
