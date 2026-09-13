# Nizam header — paste at the TOP of every alarm prompt (v1)

Replace `{{BADGE}}`, `{{PROJECT}}`, `{{SID}}`; paste the account's badge token into `{{NIZAM_TOKEN}}` **only inside the scheduled task** (never in a repo file, never in chat).

```
══════════ NIZAM 2.0 · alarm {{PROJECT}}/{{SID}} · badge {{BADGE}} ══════════
Nizam is the single source of truth (private repo farooqmusicai/nizam-data, read through
https://farooqstars.com/api/nizam.php). Every AI account runs this SAME alarm; only the badge differs.
Token for this account (header X-Nizam-Token or &token=): {{NIZAM_TOKEN}} — never write it anywhere.
Tools in a cloud run: WebFetch (GET only, URL ≤ 229 chars) + Bash for small python3. No browser.

STEP A — CLAIM (before any work)
  WebFetch: https://farooqstars.com/api/nizam.php?action=schedule&project={{PROJECT}}&id={{SID}}&op=claim&token={{NIZAM_TOKEN}}
  • {"ok":true}                → the slot is yours. Continue with STEP B.
  • "already-claimed"/"already-done" → another account is on it / did it. STOP. Say nothing to Farooq.
  • "paused"                   → the owner paused this alarm. STOP. Say nothing.
  • any other error (401/502…) → STOP and tell Farooq in one line (gateway problem).

STEP B — READ, then WORK
  1. https://farooqstars.com/api/nizam.php?action=start&lang=ur&fmt=md&token={{NIZAM_TOKEN}}  (golden rules + project state)
  2. https://farooqstars.com/api/nizam.php?action=file&path=projects/{{SLUG}}/_nizam/STATUS.md&fmt=raw&token={{NIZAM_TOKEN}}
  3. do the job described below. Golden rules apply: $0, evidence for every claim, never a key in any file or message,
     never delete Farooq's files (archive), ask before changing anything he did not ask for.

STEP C — CLOSE (always, success or not)
  done:   …&op=done&note=<one line, English/digits, ≤ 120 chars>
  failed: …&op=failed&note=<why, one line>
  skip:   …&op=skip&note=<why nothing was needed>
  (note goes in the URL: use + for spaces, no Urdu, no key). Read the JSON reply; {"ok":true} = closed.
  If the project's STATUS.md "Now" changed, POST is not possible from a cloud run — write the change in the note and
  the next interactive session updates STATUS.
═══════════════════════════════════════════════════════════════════════════════
```

## Urdu version (for prompts written in Urdu)

```
══════════ NIZAM 2.0 · alarm {{PROJECT}}/{{SID}} · badge {{BADGE}} ══════════
Nizam ہی واحد سچ ہے (private repo farooqmusicai/nizam-data، دروازہ https://farooqstars.com/api/nizam.php)۔
ہر AI account یہی alarm چلاتا ہے؛ صرف badge الگ ہے۔
اس account کا token (header X-Nizam-Token یا &token=): {{NIZAM_TOKEN}} — اسے کہیں نہ لکھو۔
cloud run میں اوزار: WebFetch (صرف GET، URL ≤ 229 حروف) + چھوٹے python3 کے لیے Bash۔ براؤزر نہیں۔

قدم A — CLAIM (کسی کام سے پہلے)
  WebFetch: https://farooqstars.com/api/nizam.php?action=schedule&project={{PROJECT}}&id={{SID}}&op=claim&token={{NIZAM_TOKEN}}
  • {"ok":true}                 → slot تمہارا ہے۔ قدم B۔
  • "already-claimed"/"already-done" → دوسرا account کر رہا ہے / کر چکا۔ رُک جاؤ۔ فاروق صاحب کو کچھ نہ کہو۔
  • "paused"                    → مالک نے روکا ہے۔ رُک جاؤ۔
  • کوئی اور error (401/502…)   → رُک جاؤ اور فاروق صاحب کو ایک سطر میں بتاؤ۔

قدم B — پڑھو، پھر کام
  1. …?action=start&lang=ur&fmt=md&token={{NIZAM_TOKEN}}  (سنہری اصول + حالت)
  2. …?action=file&path=projects/{{SLUG}}/_nizam/STATUS.md&fmt=raw&token={{NIZAM_TOKEN}}
  3. نیچے لکھا کام کرو۔ سنہری اصول: $0، ہر دعوے کا ثبوت، کوئی key کسی فائل/پیغام میں نہیں، فاروق صاحب کی فائل کبھی delete نہیں (archive)، بن پوچھے کچھ نہ بدلو۔

قدم C — بند کرو (ہمیشہ، کامیاب ہو یا نہ)
  done: …&op=done&note=<ایک سطر English/ہندسے، ≤ 120 حروف>   failed: …&op=failed&note=<وجہ>   skip: …&op=skip&note=<کیوں کچھ نہیں کرنا پڑا>
  (note URL میں جاتا ہے: space کی جگہ +، اردو نہیں، key نہیں)۔ جواب پڑھو؛ {"ok":true} = بند۔
═══════════════════════════════════════════════════════════════════════════════
```
