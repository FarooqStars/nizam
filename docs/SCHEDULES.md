# Schedules (alarms) in Nizam 2.0 — v1 · 13 Sep 2026

**Problem.** An alarm (scheduled task) lives inside ONE AI account. If that account is paused or out of credits, the alarm silently stops. Nobody else knows.

**Answer.** The alarm's *definition* lives in Nizam (`projects/<slug>/_nizam/SCHEDULE.json`), the *same prompt* is installed in every account (badge differs), and every run goes through one door: `nizam.php?action=schedule`.

```
04:30  AI1-Claude1  ──claim──▶ nizam.php ──▶ SCHEDULE.json (claimed by AI1) + LOG "CLAIM"
                        work …
                    ──done───▶ SCHEDULE.json (done) + LOG "DONE"

05:00  AI2-Claude2  ──claim──▶ nizam.php ──▶ 409 already-done → AI2 stops (nothing to do)
       (watchdog, 30 min later)

— if AI1 never ran (paused / out of credits):
05:00  AI2-Claude2  ──claim──▶ 200 claimed → AI2 does the SAME work, same prompt
05:30  AI-Zain      ──claim──▶ 409 already-claimed → stops
```

## Files

| Where | What |
|---|---|
| `projects/<slug>/_nizam/SCHEDULE.json` | the alarm: id `S-001…`, name EN/UR, what, when (cron_utc + doha), owner, escalation, `paused_until`, `last_run`, counters. **No keys.** |
| `projects/<slug>/_nizam/prompts/S-001.md` | the alarm prompt — identical in every account; only `{{BADGE}}` and `{{NIZAM_TOKEN}}` differ |
| `schedules.json` (repo root, cron-built) | all alarms in one file → START page block + Console "Schedules" |

## Gateway ops (`action=schedule`, GET or POST, badge token or owner login)

| op | who | effect |
|---|---|---|
| `claim` | badge | 200 → slot is yours today. **409** `already-claimed` / `already-done` / `paused` → stop immediately |
| `done` | badge | run finished; `runs_done`+1, `last_done` = today, LOG `DONE` |
| `failed` | badge | run failed (note why); `fails`+1, LOG `BLOCKED`; the next badge may claim |
| `skip` | badge | nothing to do today (e.g. all 24 briefs already present); LOG `NOTE` |
| `pause&days=N` | owner | every account skips until that date (Console buttons ⏸ 1/2/7) |
| `resume` | owner | clears the pause |

GET form (for cloud alarms that only have WebFetch, URL ≤ 229 chars):

```
https://farooqstars.com/api/nizam.php?action=schedule&project=FA-001&id=S-001&op=claim&token=<badge token>
```

## The "Nizam header" — first block of EVERY alarm prompt

See `docs/prompts/nizam-header.md`. Order inside a run: **claim → work → done/failed/skip**. Never work before a successful claim.

## Where the token lives

The badge token is NOT a Nizam file and is NOT in memory: it lives only inside the account's own scheduled-task prompt (the account's private "vault"), exactly like a password manager entry. The owner pastes it there once when installing the alarm. Rule 9 stays intact: no key in any repo file, LOG, STATUS or message.

## Watchdog rule (account 2, 3 …)

Same prompt, same cron + 30 min (`watchdog_delay_min`). It acts only when `claim` returns 200. If the master account ran, the watchdog costs one API call and stops.

## Current alarms (13 Sep 2026)

| Project | ID | Alarm | Master | State |
|---|---|---|---|---|
| FA-001 | S-001 | Stars daily brief 04:30 Doha | AI1-Claude1 `trig_01WmD6rybbZHwEAAaanoX7SK` | disabled — owner enables after the token is pasted |
| FA-012 | S-001 | Kitab nightly writing | — | PENDING owner's details (3 books, time, where it runs) |

Urduzaban daily schedule: **deleted 13 Sep 2026** (on demand only).
