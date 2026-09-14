# نظام 2.0 — AI سے آزاد یادداشت اور کمانڈ کا نظام

**ہر پروجیکٹ کا ایک سچ۔ کوئی بھی AI، کوئی بھی اکاؤنٹ، کوئی بھی دن: «START پڑھو اور آگے چلو۔»**

نظام ایک سادہ فائلوں کا قانون اور چند چھوٹے اوزار ہیں جن سے ایک شخص کئی AI معاونین (Claude، ChatGPT، Gemini، مقامی ماڈل…) کے ساتھ کئی پروجیکٹس پر کام کرتا ہے **اور دھاگا کبھی نہیں ٹوٹتا**۔ AI بدلا جا سکتا ہے؛ یادداشت نہیں۔

- 📁 **فولڈر کا قانون** — ہر پروجیکٹ میں بالکل 4 فولڈر اور ریکارڈ: `0-docs/` (**ہر پروجیکٹ کا پہلا پڑاؤ** — اُس کی ساری دستاویزات: .md، .txt، .docx، .pdf؛ پروجیکٹ سے متعلق کوئی فائل کہیں اور نہیں رکھی جاتی؛ NAS ہر فائل کو `0001-`، `0002-`… نمبر دیتا ہے تاکہ سب سے بڑا نمبر ہمیشہ تازہ ترین ہو)، `1-working/` (ساری آزمائش)، `2-source/` (آئندہ releases کے لیے صاف code)، `3-final/` (تیار product، live server سے جڑا) اور `_nizam/` (ریکارڈ: STATUS، LOG، IDEAS، PLAN، TASKS، MAINTENANCE، SCHEDULE)۔
- 🧭 **START صفحہ** — سارے `_nizam/` ریکارڈ سے بنتا ہے؛ AI کو آگے چلنے کے لیے *صرف* یہی پڑھنا ہے۔
- 🏷️ **AI badges** — ہر لکھائی پر نام (`AI1-Claude1`، `AI-Zain`…)؛ ہر کام کا ذمہ دار اور escalation chain، تاکہ ایک AI نہ کر سکے (یا credits ختم ہوں) تو اگلا اٹھا لے۔
- 🔁 **Session کا چکر** — READ → CLAIM → WORK → WRITE → HANDOFF۔ LOG صرف آگے بڑھتا ہے، ہر پروجیکٹ کی ایک زندہ STATUS۔
- 🔐 **راز کبھی اندر نہیں** — keys، passwords، tokens vault میں رہتے ہیں، ریکارڈ میں کبھی نہیں۔ ریکارڈ کوئی بھی AI محفوظ طریقے سے پڑھ سکتا ہے۔
- 🖥️ **گھر کا مرکز** — پروجیکٹ ایک NAS پر رہتے ہیں (واحد سچ)؛ ایک چھوٹا token والا files gateway اور login والا console ہر AI account کو cloud سے NAS پڑھنے، لکھنے اور جانچنے دیتا ہے — کوئی PC چلتا رکھنا ضروری نہیں۔
- 🌍 **دو زبانیں** — English اور اردو، الگ الگ؛ اعداد ہر جگہ English digits۔
- 🔌 **کھلا** — کوئی بھی نیا AI tool یا agent ایک badge دے کر اور START کی طرف موڑ کر شامل کریں۔

English README: [README.md](README.md) · قانون: [PROTOCOL.md](PROTOCOL.md) · خاکہ: [docs/BLUEPRINT.html](docs/BLUEPRINT.html)

## ترتیب

```
nizam/                     ← یہ عوامی repo (قانون + اوزار + دستاویزات؛ کوئی ذاتی data نہیں)
  PROTOCOL.md              وہ اصول جن پر ہر AI چلتا ہے
  AGENTS.md                اُن tools کے لیے دروازہ جو AGENTS.md خود پڑھتے ہیں
  registry.schema.json     registry.json کی شکل
  templates/project/       نیا پروجیکٹ شروع کرنے کے لیے یہ copy کریں (0-docs · 1-working · 2-source · 3-final · _nizam)
  tools/build_start.py     registry + ریکارڈ → START.md
nizam-data/                ← ایک PRIVATE repo: آپ کی registry، پروجیکٹس کے _nizam/ ریکارڈ، START.md
```

License: MIT۔ محمد فاروق (دوحہ) نے Claude اور ChatGPT کے ساتھ ستمبر 2026 میں بنایا۔
