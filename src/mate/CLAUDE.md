# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in the Mate component.

@AGENTS.md

## Claude Code Specifics

Mate is consumed as an MCP server. After running `bin/mate init` in a project, Mate generates an `mcp.json` (with a `.mcp.json` symlink) at the project root that Claude Code picks up automatically — no extra registration is needed in Claude Code.

When iterating on Mate itself, remember that the materialized `mate/AGENT_INSTRUCTIONS.md` and the managed block inside the consuming project's `AGENTS.md` are the sources of truth for available MCP tools — prefer reading those over hardcoding tool lists.
