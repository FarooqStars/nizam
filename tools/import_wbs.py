#!/usr/bin/env python3
"""import_wbs.py — bring a project plan (like the Farooq Music 2.0 telemetry dashboard's wbs-status.json)
into the Nizam record: TASKS.json (every task, with phase/day/gate/owner) + PLAN.md (phases, gates, progress).
Usage (inside nizam-data):  python3 ../nizam/tools/import_wbs.py FA-002 /path/to/wbs-status.json
Owner mapping: claude → AI1-Claude1 · farooq → owner · anything else → as given.
"""
import json, sys, os, collections, datetime
if len(sys.argv) < 3: sys.exit("usage: import_wbs.py FA-002 wbs-status.json")
pid, src = sys.argv[1], sys.argv[2]
ROOT = os.getcwd()
reg = json.load(open(os.path.join(ROOT, "registry.json"), encoding="utf-8"))
slug = next((p["slug"] for p in reg["projects"] if p["id"] == pid), None) or sys.exit("project not in registry: " + pid)
d = json.load(open(src, encoding="utf-8"))
OWNER = {"claude": "AI1-Claude1", "farooq": "owner", "zain": "AI-Zain", "chatgpt": "AI-Zain"}
STATE = {"Completed": "done", "Pending": "pending", "In Progress": "doing", "Blocked": "blocked"}
tasks = []; phases = collections.OrderedDict()
for x in d["tasks"]:
    ph = phases.setdefault(x["phase"], {"id": x["phase"], "name": x.get("phase_name", x["phase"]), "n": 0, "done": 0, "gate": None, "days": []})
    ph["n"] += 1; ph["done"] += x.get("status") == "Completed"; ph["days"].append(x.get("day", 0))
    if x.get("gate"): ph["gate"] = x["gate"]
    tasks.append({
        "id": x["id"], "title": {"en": x["title"], "ur": x["title"]},
        "state": STATE.get(x.get("status"), "pending"),
        "owner": OWNER.get(str(x.get("owner", "")).lower(), x.get("owner", "AI1-Claude1")),
        "escalation": ["AI1-Claude1", "AI2-Claude2", "AI-Zain"],
        "phase": x["phase"], "day": x.get("day"), "due": x.get("date", ""), "gate": x.get("gate"),
        "farooq_minutes": x.get("farooq_minutes", 0), "hours": x.get("estimated_hours"),
        "evidence_required": x.get("evidence_required", ""), "evidence": x.get("evidence", ""),
        "depends": x.get("dependencies", []), "completed_at": x.get("completed_at"),
    })
meta = d.get("meta", {})
out = {"schema": 2, "source": os.path.basename(src), "imported": datetime.datetime.now().strftime("%Y-%m-%d %H:%M"),
       "day0": meta.get("day0"), "total_days": meta.get("total_days"), "tasks": tasks}
nz = os.path.join(ROOT, "projects", slug, "_nizam"); os.makedirs(nz, exist_ok=True)
json.dump(out, open(os.path.join(nz, "TASKS.json"), "w", encoding="utf-8"), ensure_ascii=False, indent=1)
# PLAN.md from phases
L = ["# PLAN — " + meta.get("project", pid) + " (gate over date)",
     f"*Imported from {os.path.basename(src)} · day 0 = {meta.get('day0')} · {meta.get('total_days')} days · source of the plan: {meta.get('blueprint','')}*", "",
     "| # | Phase | Days | Tasks done | Gate | State |", "|---|---|---|---|---|---|"]
for k, p in phases.items():
    st = "✅" if p["done"] == p["n"] else ("🟡" if p["done"] else "⬜")
    L.append(f"| {k} | {p['name']} | {min(p['days'])}–{max(p['days'])} | {p['done']}/{p['n']} | {p['gate'] or '—'} | {st} |")
done = sum(1 for t in tasks if t["state"] == "done")
L += ["", f"Progress: {done}/{len(tasks)} tasks", f"Finish line: day {meta.get('total_days')} from {meta.get('day0')}"]
open(os.path.join(nz, "PLAN.md"), "w", encoding="utf-8").write("\n".join(L) + "\n")
print(f"{pid}: {len(tasks)} tasks, {len(phases)} phases → {nz}")
