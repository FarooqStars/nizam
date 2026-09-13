# PROTOCOL — how every AI works inside Nizam

## 0. Identity
At the start of a session the AI states its badge (from `agents.json`), vendor, and capabilities
(`files-read`, `files-write`, `web-fetch`, `shell`, `browser`), e.g. `AI1-Claude1 · Claude · files-read files-write browser`.
All badges belong to ONE person (the owner). Different accounts are not different people.

## 1. Reading order (never skip)
1. `START.md` (or the hosted `/start`) — rules, every project's STATUS summary, open handoffs.
2. The project's `_nizam/STATUS.md` — the one live file.
3. Tail of `_nizam/LOG.md` — last 30 lines.
Never assume something does not exist. Check. If a fact is complicated or unknown — ask the owner, do not guess.

## 2. Claim
Before working, write one LOG line: `2026-09-13 10:05 · AI1-Claude1 · CLAIM · task T-014 "…"`.
If the task has another owner and no handoff, ask first.

## 3. Work
One step at a time; evidence for every step (screenshot, output file, URL). All scratch goes to `1-working/`.
Never delete owner files — move to `_archive/` or `_to_delete/`. Never touch a live storage volume or a live server
without an explicit instruction. Never spend money without an explicit yes.

## 4. Write (end of every session, always)
- `LOG.md` — append: date · badge · DONE/BLOCKED/HANDOFF · what · evidence.
- `STATUS.md` — rewrite the "Now" and "Next" sections; keep decisions with their reasons.
- `TASKS.json` — update owner/state; add new tasks.
- `IDEAS.md` / `PLAN.md` / `MAINTENANCE.md` — when touched.
Then the START page regenerates (`tools/build_start.py`, cron or manual).

## 5. Handoff
When the AI cannot continue (capability, error, or the owner's credits/limits end):
`LOG: … · HANDOFF → AI2-Claude2 · reason · exact next step`. Set `TASKS.json` state to `handoff`.
The next badge in the task's `escalation` chain sees it at the top of START.

## 6. Secrets boundary
Nothing that opens a door goes into the record: no passwords, API keys, tokens, private keys, auth links.
Refer to them by name and location only (`fs-config.php on the server`, `Vault → EX4 login`).
If a secret is pasted by mistake, replace it with `[REDACTED]` and note it in LOG.

## 7. Language & format
Two complete languages (English / Urdu), never mixed in one view. English digits (1, 2, 3) always.
Full file replacements, never diffs. Owner works browser-first; give click paths, not shell lore.

## 8. Log format
`YYYY-MM-DD HH:MM · BADGE · TYPE · text · evidence`
TYPE ∈ CLAIM · DONE · BLOCKED · HANDOFF · NOTE · IDEA · DECISION · MAINT
Append-only. Rotate to `_nizam/archive/LOG-YYYY-MM.md` when LOG.md passes 500 lines.
