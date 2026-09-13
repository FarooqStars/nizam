#!/usr/bin/env python3
"""build_start.py — registry.json + every projects/*/_nizam/ → START.md (EN) and START.ur.md (UR).
No dependencies. Run from the nizam-data folder:  python3 ../nizam/tools/build_start.py
Cron on the NAS: */15 * * * *  cd /data/Nizam/nizam-data && python3 ../nizam/tools/build_start.py
"""
import json, os, re, datetime, sys
ROOT = os.getcwd()
reg = json.load(open(os.path.join(ROOT, "registry.json"), encoding="utf-8"))
agents = json.load(open(os.path.join(ROOT, "agents.json"), encoding="utf-8")) if os.path.exists(os.path.join(ROOT, "agents.json")) else {"agents": []}
rules = open(os.path.join(ROOT, "RULES.md"), encoding="utf-8").read() if os.path.exists(os.path.join(ROOT, "RULES.md")) else ""
rules_ur = open(os.path.join(ROOT, "RULES.ur.md"), encoding="utf-8").read() if os.path.exists(os.path.join(ROOT, "RULES.ur.md")) else rules
now = datetime.datetime.now().strftime("%Y-%m-%d %H:%M")

def section(path, title):
    p = os.path.join(ROOT, "projects", path, "_nizam", "STATUS.md")
    if not os.path.exists(p): return "_(no STATUS.md yet)_"
    txt = open(p, encoding="utf-8").read()
    m = re.search(r"## Now.*?(?=\n## |\Z)", txt, re.S); n = re.search(r"## Next.*?(?=\n## |\Z)", txt, re.S)
    return ((m.group(0) if m else "") + "\n" + (n.group(0) if n else "")).strip()

def log_tail(path, n=8):
    p = os.path.join(ROOT, "projects", path, "_nizam", "LOG.md")
    if not os.path.exists(p): return ""
    lines = [l for l in open(p, encoding="utf-8").read().splitlines() if l.startswith("20")]
    return "\n".join(lines[-n:])

STATS = json.load(open(os.path.join(ROOT, "stats.json"), encoding="utf-8")) if os.path.exists(os.path.join(ROOT, "stats.json")) else None
def folder_stats(path):
    if STATS:
        for pid, v in STATS.get("projects", {}).items():
            if v.get("slug") == path:
                return {k: (f["files"], f["bytes"]) for k, f in v["folders"].items() if k != "_archive"}
    out = {}
    for f in ("1-working", "2-source", "3-final"):
        d = os.path.join(ROOT, "projects", path, f); files = 0; size = 0
        for r, _, fs in os.walk(d):
            for x in fs:
                if x == ".keep": continue
                files += 1
                try: size += os.path.getsize(os.path.join(r, x))
                except OSError: pass
        out[f] = (files, size)
    return out

def human(b):
    for u in ("B", "KB", "MB", "GB", "TB"):
        if b < 1024: return f"{b:.0f} {u}"
        b /= 1024
    return f"{b:.1f} PB"

def plan_block(slug, lang):
    tp = os.path.join(ROOT, "projects", slug, "_nizam", "TASKS.json")
    if not os.path.exists(tp): return ""
    j = json.load(open(tp, encoding="utf-8")); ts = j.get("tasks", [])
    if not ts or not any(t.get("phase") for t in ts): return ""
    today = datetime.date.today().isoformat()
    ph = {}
    for t in ts:
        p = ph.setdefault(t["phase"], [0, 0, t.get("gate")]); p[0] += 1; p[1] += t.get("state") == "done"
        if t.get("gate"): p[2] = t["gate"]
    cur = next((k for k, v in ph.items() if v[1] < v[0]), None)
    done = sum(1 for t in ts if t.get("state") == "done")
    L = [("**Plan:** " if lang == "en" else "**منصوبہ:** ") + f"{done}/{len(ts)} " + ("tasks" if lang == "en" else "کام") + " · " + ("phase" if lang == "en" else "مرحلہ") + f" **{cur or '—'}**" + (f" → {ph[cur][2]}" if cur and ph[cur][2] else "")]
    L.append(" ".join(("✅" if v[1] == v[0] else "🟡" if v[1] else "⬜") + k for k, v in ph.items()))
    td = [t for t in ts if t.get("state") != "done" and t.get("due") == today]
    nx = [t for t in ts if t.get("state") != "done"][:3]
    if td: L.append(("**Today:** " if lang == "en" else "**آج:** ") + " · ".join(f"{t['id']} {t['title'].get(lang, t['title'].get('en',''))} ({t.get('owner','')})" for t in td[:5]))
    if nx: L.append(("**Next:** " if lang == "en" else "**اگلا:** ") + " · ".join(f"{t['id']} {t['title'].get(lang, t['title'].get('en',''))} ({t.get('owner','')})" for t in nx))
    return "\n".join(L) + "\n"

handoffs = []
for pr in reg["projects"]:
    tp = os.path.join(ROOT, "projects", pr["slug"], "_nizam", "TASKS.json")
    if os.path.exists(tp):
        for t in json.load(open(tp, encoding="utf-8")).get("tasks", []):
            if t.get("state") == "handoff": handoffs.append((pr["id"], t))

def build(lang):
    L = (lambda d: d.get(lang, d.get("en", ""))) 
    o = []
    o.append(f"# START — {reg['owner']['name']} · Nizam 2.0 · {now}\n")
    o.append("> " + ("Read this whole page, then the project's STATUS.md, then the LOG tail. Claim before working. Write before leaving." if lang=="en" else "پہلے یہ پورا صفحہ، پھر پروجیکٹ کی STATUS.md، پھر LOG کا آخری حصہ پڑھیں۔ کام سے پہلے CLAIM، جانے سے پہلے لکھیں۔") + "\n")
    o.append(("## Golden rules" if lang=="en" else "## سنہری اصول") + "\n" + (rules if lang=="en" else rules_ur) + "\n")
    if handoffs:
        o.append(("## ⚠️ Open handoffs — take these first" if lang=="en" else "## ⚠️ کھلے handoff — پہلے یہ اٹھائیں") + "\n")
        for pid, t in handoffs:
            o.append(f"- **{pid}** · {t['id']} · {L(t['title'])} · → {t.get('owner','?')}")
        o.append("")
    o.append(("## AI badges" if lang=="en" else "## AI badges") + "\n" + ", ".join(a["badge"] for a in agents.get("agents", [])) + "\n")
    o.append(("## Projects" if lang=="en" else "## پروجیکٹس") + "\n")
    light = {"green": "🟢", "yellow": "🟡", "red": "🔴", "archived": "⚫"}
    for pr in reg["projects"]:
        st = folder_stats(pr["slug"])
        o.append(f"### {light.get(pr['status'],'⚪')} {pr['id']} — {L(pr['name'])}")
        o.append(f"owner: **{pr.get('owner_ai','')}** · escalation: {' → '.join(pr.get('escalation', []))} · finish line: {pr.get('finish_line','—')}")
        o.append(f"folders: " + " · ".join(f"{k} {v[0]} files / {human(v[1])}" for k, v in st.items()))
        if pr.get("links"):
            o.append("links: " + " · ".join(f"[{L(l['label'])}]({l['url']})" for l in pr["links"]))
        o.append("")
        o.append(plan_block(pr["slug"], lang))
        o.append(section(pr["slug"], pr["id"]))
        lt = log_tail(pr["slug"])
        if lt: o.append("\n```\n" + lt + "\n```")
        o.append("")
    o.append("---\n" + ("End of session: LOG + STATUS + TASKS. Then this page regenerates." if lang=="en" else "Session کے آخر میں: LOG + STATUS + TASKS۔ پھر یہ صفحہ خود بنتا ہے۔"))
    return "\n".join(o)

open(os.path.join(ROOT, "START.md"), "w", encoding="utf-8").write(build("en"))
open(os.path.join(ROOT, "START.ur.md"), "w", encoding="utf-8").write(build("ur"))
print("START.md + START.ur.md built", now, "projects:", len(reg["projects"]), "handoffs:", len(handoffs))
