#!/usr/bin/env python3
"""new_project.py FA-011 my-slug — copies templates/project into nizam-data/projects/FA-011-my-slug and adds a registry entry."""
import sys, os, shutil, json
if len(sys.argv) < 3: sys.exit("usage: new_project.py FA-011 my-slug")
pid, slug = sys.argv[1], sys.argv[2]
here = os.path.dirname(os.path.abspath(__file__)); tpl = os.path.join(here, "..", "templates", "project")
data = os.getcwd(); dest = os.path.join(data, "projects", f"{pid}-{slug}")
if os.path.exists(dest): sys.exit("exists: " + dest)
shutil.copytree(tpl, dest)
rp = os.path.join(data, "registry.json"); reg = json.load(open(rp, encoding="utf-8"))
reg["projects"].append({"id": pid, "slug": f"{pid}-{slug}", "name": {"en": slug, "ur": slug}, "status": "yellow",
  "owner_ai": "AI1-Claude1", "escalation": ["AI1-Claude1", "AI2-Claude2", "AI-Zain"],
  "created": {"date": __import__("datetime").date.today().isoformat(), "by": "owner", "where": ""}, "links": []})
json.dump(reg, open(rp, "w", encoding="utf-8"), ensure_ascii=False, indent=2)
print("created", dest)
