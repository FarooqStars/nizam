# Phase 4b — `3-final` → live server, automatically · blueprint v0.1 · 13 Sep 2026

**Folder law (owner's words):** `1-working` = the mess · `2-source` = clean source only · `3-final` = the deliverable that is LINKED to the live server. Nobody edits `3-final` by hand. When it changes, the server updates itself. No unnecessary file may ever land in `3-final`.

```
 PC (Y:\FA-00x-…\3-final)  ═══ Samba ═══▶  EX4  /data/Nizam/projects/FA-00x-…/3-final
                                              │
                                              │  nizam-deploy.sh  (cron every 5 min, only if 3-final changed)
                                              │  reads  _nizam/DEPLOY.json  (what kind of server, where)
                                              ▼
              ┌──────────────────┬──────────────────────┬─────────────────────────┐
              │  kind = ssh      │  kind = git           │  kind = http            │
              │  rsync over      │  commit + push to the │  zip → POST to the      │
              │  Tailscale SSH   │  site's GitHub repo;  │  site's deploy.php      │
              │  (Oracle         │  Hostinger git-deploy │  (farooqstars.com,      │
              │   fm2-stream)    │  pulls it             │   already has deploy.php)│
              └──────────────────┴──────────────────────┴─────────────────────────┘
                                              │
                                              ▼
                                   LOG line  "DEPLOY · FA-00x · n files · ok/fail"
                                   (nizam-deploy.sh appends to _nizam/LOG.md → cron pushes)
```

## Rules that keep `3-final` clean

1. `3-final` is written only by a **release step** (`tools/release.py FA-002` copies from `2-source`, applying `_nizam/DEPLOY.json → "include"/"exclude"`) — never by hand, never by an AI editing files inside it.
2. `nizam-deploy.sh` refuses to deploy if `3-final` contains anything from the exclude list (`.git`, `node_modules`, `*.bak`, `*.tmp`, `Thumbs.db`, `.DS_Store`, `1-working` leftovers).
3. Dry-run first: `DEPLOY.json → "enabled": false` shows what *would* go, writes it to LOG, deploys nothing. The owner flips `enabled` to `true` per site, one site at a time.
4. Secrets: SSH keys live on the EX4 in `/root/.ssh/` only; deploy.php uses the token already in `fs-var/`. `DEPLOY.json` holds host names and paths, never keys (rule 9).
5. Every deploy = one LOG line with file count + result. A failed deploy never retries blindly; it marks the project 🔴 in `stats.json` so the Console shows it.

## `_nizam/DEPLOY.json` (template in `templates/project/_nizam/DEPLOY.json`)

```json
{ "schema": 1, "enabled": false,
  "targets": [
    { "kind": "ssh",  "name": "fm2-stream (Oracle)", "host": "100.107.73.115", "user": "deploy",
      "path": "/var/www/fm2/", "rsync_delete": false },
    { "kind": "git",  "name": "Hostinger git-deploy", "repo": "git@github.com:farooqmusicai/<site>.git", "branch": "main" },
    { "kind": "http", "name": "farooqstars deploy.php", "url": "https://farooqstars.com/api/deploy.php", "token_file": "/root/.nizam/deploy-<slug>.token" }
  ],
  "include": ["**"],
  "exclude": [".git/**", "node_modules/**", "*.bak", "*.tmp", "Thumbs.db", ".DS_Store", "desktop.ini"]
}
```

## Order of work (one site at a time, gate before the next)

| # | Site | Kind | Gate |
|---|---|---|---|
| 1 | FA-001 farooqstars.com | http → deploy.php (exists) | one test file appears on the site, LOG line written, dry-run first |
| 2 | FA-002 Farooq Music 2.0 (Oracle) | ssh/rsync over Tailscale (EX4 key added to `deploy@` — owner's step) | `rsync --dry-run` lists files, then a real run of one folder |
| 3 | FA-004 urduzaban.com, FA-013, FA-014 … (Hostinger) | git push from EX4 to that site's repo | Hostinger auto-deploy log shows the commit |

## What the owner has to do (only once per site)

- Oracle: add the EX4 public key (`/root/.ssh/nizam_deploy.pub`, printed by `EX4-NIZAM.bat`) to `deploy@fm2-stream` `authorized_keys` — the existing `FM2-*.bat` on HOME-01 can do it (never a new VM, never reset).
- farooqstars.com: confirm `deploy.php` accepts a zip + token (Claude checks the existing file in `FarooqStars-2.0\_deploy\api\` before writing anything).
- Hostinger sites: the EX4 deploy key must be added to each site's repo (Deploy keys → allow write).

Nothing deploys until `enabled: true` — the dry-run LOG lines are the proof step.
