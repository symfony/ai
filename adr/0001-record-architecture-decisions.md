---
status: proposed
date: 2026-10-25
---

# 0001. Record architecture decisions in ADRs

## Context and Problem Statement

Symfony AI is a monorepo of independently released components and bundles that grew
quickly. The reasons behind its central design choices (the platform contracts, the
agent processor pipeline, the store abstraction, the bundle configuration) are spread
over pull request discussions, issues and the heads of a few maintainers. New
contributors and coding agents have to reconstruct them from the code, and decisions get
reopened or accidentally undone because nobody can point to where they were made.

## Decision

We record architecturally significant decisions as Architecture Decision Records in a
lean [MADR](https://adr.github.io/madr/) format (`adr/0000-template.md`).

- Every component and bundle keeps its ADRs in its own `src/<package>/adr/` directory,
  numbered from `0001`, with an index table in that directory's `README.md`.
- Decisions that span the whole monorepo live in the root `adr/` directory.
- Bridges do not get their own ADR directory; decisions about how bridges plug in belong
  to the component.
- An ADR is added in the same pull request as the decision it records. Accepted ADRs
  are immutable; a changed decision gets a new ADR that supersedes the old one.
- The `adr/` directories are marked `export-ignore`, so they are not part of the
  distributed packages.
- `AGENTS.md` asks contributors and coding agents to read the relevant ADRs before
  changing a package and to add one for every new decision.

The initial ADRs of each package document decisions that already exist in the code.

## Consequences

- **Good:** the reasoning behind the architecture is versioned next to the code, is
  reviewed like code, and can be linked from issues and pull requests.
- **Good:** coding agents get explicit design constraints instead of inferring them
  from neighboring code.
- **Trade-off:** a pull request that makes a design decision carries an extra file, and
  reviewers have to judge whether a change is significant enough to need one.

## Alternatives Considered

- **One central ADR directory for the whole monorepo**: not chosen because the
  packages are released and evolve independently; a per-package directory keeps the
  decisions next to the code they constrain.
- **ADRs as RST pages under `docs/`**: not chosen because `docs/` is the user-facing
  documentation published on symfony.com, while ADRs are contributor-facing history.
- **Keep decisions in pull request descriptions only**: not chosen because they are hard
  to find, are never updated when a decision is reversed, and do not travel with the code.

## References

- `adr/README.md`: the process, the template and the per-package index.
- [MADR](https://adr.github.io/madr/): the template format this follows.
