# Nizam 2.0 — an AI-independent memory & command system

**One source of truth for every project. Any AI, any account, any day: "read START and continue."**

Nizam (Urdu: *system / order*) is a plain-files convention plus a few small tools that let a person work with
several AI assistants (Claude, ChatGPT, Gemini, local models…) on many projects **without ever losing the thread**.
The AI is replaceable; the memory is not.

- 📁 **Folder law** — every project has exactly `1-working/` (the mess), `2-source/` (code for future releases),
  `3-final/` (the delivered product) and `_nizam/` (the record: STATUS, LOG, IDEAS, PLAN, TASKS, MAINTENANCE).
- 🧭 **START page** — generated from all `_nizam/` records; the *only* thing an AI needs to read to continue.
- 🏷️ **AI badges** — every write is attributed (`AI1-Claude1`, `AI-Zain`…); a task has an owner and an escalation
  chain, so when one AI cannot (or its credits end) the next one picks it up.
- 🔁 **Session loop** — READ → CLAIM → WORK → WRITE → HANDOFF. Append-only LOG, one live STATUS per project.
- 🔐 **Secrets never enter** — keys, passwords and tokens live in a vault, never in the record. The record is safe to read by any AI.
- 🌍 **Two languages** — English and Urdu, kept separate, English digits everywhere.
- 🔌 **Open** — add any AI tool or agent by giving it a badge and pointing it at START.

Urdu README: [README.ur.md](README.ur.md) · Protocol: [PROTOCOL.md](PROTOCOL.md) · Blueprint: [docs/BLUEPRINT.html](docs/BLUEPRINT.html)

## Layout

```
nizam/                     ← this public repo (convention + tools + docs; no personal data)
  PROTOCOL.md              the rules every AI follows
  AGENTS.md                entry file for tools that auto-read AGENTS.md
  registry.schema.json     shape of registry.json
  templates/project/       copy this to start a project
  tools/build_start.py     registry + records → START.md
nizam-data/                ← a PRIVATE repo: your registry, your projects' _nizam/ records, START.md
```

## Quick start

1. Create a private repo `nizam-data` from `templates/` (or run `tools/new_project.py FA-011 my-project`).
2. Give each AI account a badge in `agents.json`.
3. Every session: the AI reads `START.md` (or your hosted `/start` URL), works, then appends to `LOG.md`
   and rewrites `STATUS.md`. Run `tools/build_start.py` (cron every 15 min) to regenerate START.

Inspired by [agent-work-mem](https://github.com/daystar7777/agent-work-mem) (tiered append-only log, capability
tags) and [ai-memory](https://github.com/akitaonrails/ai-memory) (markdown source of truth, typed handoffs,
privacy boundary). Built for and by Mohammad Farooq (Doha) with Claude and ChatGPT, September 2026.

License: MIT.
