# Architecture Decision Records (ADRs)

Architecture Decision Records capture the **why** behind the design of Symfony AI: one
short Markdown file per architecturally significant decision, with its context, the
decision itself, its consequences and the alternatives that were ruled out. The
documentation under `docs/` describes *what* a component does and *how* to use it; the
ADRs record the reasoning and trade-offs, so nobody has to reverse-engineer "why was it
built this way?" from the code or from old pull requests.

The format is a lean [MADR](https://adr.github.io/madr/): copy
[`0000-template.md`](0000-template.md).

## Where ADRs live

Each component and bundle keeps its own ADRs, numbered from `0001` per directory:

| Package | ADRs |
| ------- | ---- |
| Platform | [`src/platform/adr/`](../src/platform/adr/README.md) |
| Agent | [`src/agent/adr/`](../src/agent/adr/README.md) |
| Chat | [`src/chat/adr/`](../src/chat/adr/README.md) |
| Store | [`src/store/adr/`](../src/store/adr/README.md) |
| Mate | [`src/mate/adr/`](../src/mate/adr/README.md) |
| AI Bundle | [`src/ai-bundle/adr/`](../src/ai-bundle/adr/README.md) |
| MCP Bundle | [`src/mcp-bundle/adr/`](../src/mcp-bundle/adr/README.md) |

Decisions that span the whole monorepo live in this directory:

| ADR | Decision |
| --- | -------- |
| [0001](0001-record-architecture-decisions.md) | **Record architecture decisions in ADRs.** Every component and bundle keeps MADR-style ADRs in its own `adr/` directory; monorepo-wide decisions live in the root `adr/` |

A bridge does not get its own ADR directory. A decision that shapes how bridges of a
component work (for example how they are discovered or configured) belongs to the
component's ADRs.

## When to write one

Add an ADR in the **same pull request** that introduces an architecturally significant
decision:

- a new or changed core contract (an interface or value object other code builds on);
- a new extension point or a new way bridges plug into a component;
- a new cross-cutting pattern (events, processors, configuration, exceptions, ...);
- a new dependency that shapes the architecture (for example adopting an SDK);
- a reversal or replacement of an earlier decision.

Bug fixes, new bridges that follow the existing pattern, new options, refactorings
without a design change and CS-only changes do **not** need an ADR.

## How to add one

1. Copy [`0000-template.md`](0000-template.md) to the next free
   `NNNN-kebab-case-title.md` of the right ADR directory.
2. Fill it in, in English. Keep it concise and easy to understand: short, plain
   sentences, and name the real classes and files.
3. Add its row to the index table of that directory's `README.md`.
4. When an ADR records a decision after the fact, fill its **History** section: the
   pull requests that introduced and reshaped the decision, with date and author, so
   the reasoning can be traced back to the original discussion.
5. Use `status: proposed` while the pull request is open; it becomes `accepted` when the
   pull request is merged.

## Immutability

An accepted ADR is history and is not rewritten; fixing a typo or a broken link is
fine. To change or reverse a decision, write a **new** ADR that explains what changed and
why, and set the old one's status to `superseded by NNNN`.
