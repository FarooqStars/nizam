#!/usr/bin/env python3
"""nizam_folders.py — the folder law on the NAS.
For every project in registry.json make sure the REAL data folders exist under DATA_ROOT
(1-working / 2-source / 3-final / _archive) and write stats.json (files, bytes, newest change per folder)
into the nizam-data repo so the console and START can show them. Big files never go into git.
Run inside the nizam-data folder:  python3 ../nizam/tools/nizam_folders.py [DATA_ROOT]
Default DATA_ROOT = /data/Nizam/projects
"""
import json, os, sys, datetime
ROOT = os.getcwd()
# folders must belong to the Samba user, not root (cron runs as root): farooq:nasusers, dirs 2770
try:
    import pwd, grp; UID = pwd.getpwnam("farooq").pw_uid; GID = grp.getgrnam("nasusers").gr_gid
except Exception: UID = GID = None
def mk(d):
    new = not os.path.isdir(d); os.makedirs(d, exist_ok=True)
    if new and UID is not None and os.geteuid() == 0:
        try: os.chown(d, UID, GID); os.chmod(d, 0o2770)
        except OSError: pass
DATA = sys.argv[1] if len(sys.argv) > 1 else "/data/Nizam/projects"
reg = json.load(open(os.path.join(ROOT, "registry.json"), encoding="utf-8"))
FOLDERS = ("1-working", "2-source", "3-final", "_archive")
stats = {"generated": datetime.datetime.now().strftime("%Y-%m-%d %H:%M"), "data_root": DATA, "projects": {}}
for pr in reg["projects"]:
    base = os.path.join(DATA, pr["slug"]); mk(base)
    ps = {}
    for f in FOLDERS:
        d = os.path.join(base, f); mk(d)
        files = 0; size = 0; newest = 0
        for r, _, fs in os.walk(d):
            for x in fs:
                p = os.path.join(r, x)
                try: st = os.stat(p)
                except OSError: continue
                files += 1; size += st.st_size; newest = max(newest, st.st_mtime)
        ps[f] = {"files": files, "bytes": size, "last_change": datetime.datetime.fromtimestamp(newest).strftime("%Y-%m-%d %H:%M") if newest else ""}
    # link the git record folder for convenience (record lives in the repo, data lives here)
    rec = os.path.join(base, "_nizam")
    src = os.path.join(ROOT, "projects", pr["slug"], "_nizam")
    if not os.path.lexists(rec) and os.path.isdir(src):
        try: os.symlink(src, rec)
        except OSError: pass
    stats["projects"][pr["id"]] = {"slug": pr["slug"], "folders": ps, "path": base}
json.dump(stats, open(os.path.join(ROOT, "stats.json"), "w", encoding="utf-8"), ensure_ascii=False, indent=2)
print("stats.json:", len(stats["projects"]), "projects under", DATA)
