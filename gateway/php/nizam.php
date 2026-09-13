<?php
/* ============================================================
   FarooqStars 2.0 — api/nizam.php        (Nizam 2.0 gateway · v0.1 · 13 Sep 2026)
   ------------------------------------------------------------
   The ONE door through which any AI (or Farooq on his phone) reads and writes
   the Nizam record that lives in the PRIVATE GitHub repo farooqmusicai/nizam-data.
   GitHub is the transport: every write here = one commit → EX4 / PCs pull it.

   Doors:
     • owner session (fs_sess cookie) — only NZ_OWNERS e-mails (same lock as agenda)
     • AI badge token: header  X-Nizam-Token: …   or  ?token=…   (minted by the owner)
   Secrets: the GitHub token and the badge tokens live ONLY in fs-var/ (outside
   public_html, chmod 600). Never in a reply, never in the record. Rule 9.

   GET  action=whoami
   GET  action=start&lang=en|ur[&fmt=md]          → START.md / START.ur.md (cache 5 min)
   GET  action=file&path=projects/…/_nizam/STATUS.md[&fmt=raw]
   GET  action=registry                            → registry.json
   POST {action:'log',    project, type, text, evidence?}   → append line to LOG.md
   POST {action:'status', project, content}                 → replace STATUS.md
   POST {action:'idea',   project, text}                    → append to IDEAS.md
   POST {action:'task',   project, id, state, owner?, evidence?} → TASKS.json
   GET  action=schedules                           → schedules.json (NAS cron builds it from every _nizam/SCHEDULE.json)
   GET|POST action=schedule&project=FA-001&id=S-001&op=claim|done|failed|skip|pause|resume[&days=2][&note=…]
                                                   → updates that project's _nizam/SCHEDULE.json + one LOG line.
                                                     GET is allowed so a cloud alarm can call it with WebFetch (short URL).
                                                     pause / resume = owner only; claim/done/failed/skip = any badge.
   POST {action:'doc',    project, name, content}          → drops a document (md/txt, ≤ 200 KB) into projects/<slug>/0-docs/ in git;
                                                       the NAS cron moves it to Y:\<slug>\0-docs\ within 15 min and numbers it (NNNN-name). For cloud AIs that cannot reach the NAS.
   POST {action:'setup',  github_token}   owner only → store token (fs-var)
   POST {action:'mint',   badge}          owner only → new badge token (shown once)
   POST {action:'revoke', badge}          owner only
   ============================================================ */
header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

$cfg = dirname(__DIR__, 2) . '/fs-config.php';
if (!is_file($cfg)) { echo json_encode(['ok'=>false,'err'=>'config-missing']); exit; }
require $cfg;
require __DIR__ . '/fs-db.php';

const NZ_VERSION = '0.3 (2026-09-13 · schedules + doc inbox)';
const NZ_OWNERS  = ['babaqatar@gmail.com', 'baba867@gmail.com'];
const NZ_REPO    = 'farooqmusicai/nizam-data';
const NZ_BRANCH  = 'main';
const NZ_TYPES   = ['CLAIM','DONE','BLOCKED','HANDOFF','NOTE','IDEA','DECISION','MAINT'];

function nz_out($o, $code = 200){ http_response_code($code); echo json_encode($o, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }
function nz_dir(){ $d = fs_home() . '/fs-var/nizam'; if (!is_dir($d)) @mkdir($d, 0700, true); return $d; }
function nz_doha($fmt = 'Y-m-d H:i'){ return gmdate($fmt, time() + 3 * 3600); }
function nz_secret(){ $f = nz_dir() . '/secret.json'; return is_file($f) ? (json_decode((string)@file_get_contents($f), true) ?: []) : []; }
function nz_secret_save($s){ $f = nz_dir() . '/secret.json'; @file_put_contents($f, json_encode($s), LOCK_EX); @chmod($f, 0600); }
function nz_tokens(){ $f = nz_dir() . '/tokens.json'; return is_file($f) ? (json_decode((string)@file_get_contents($f), true) ?: []) : []; }
function nz_tokens_save($t){ $f = nz_dir() . '/tokens.json'; @file_put_contents($f, json_encode($t), LOCK_EX); @chmod($f, 0600); }
function nz_audit($who, $what){ @file_put_contents(nz_dir() . '/audit.log', nz_doha('Y-m-d H:i:s') . " · $who · $what\n", FILE_APPEND | LOCK_EX); }

/* ---------- GitHub (contents API) ---------- */
function nz_gh($method, $path, $body = null){
  $s = nz_secret(); $tok = $s['github_token'] ?? '';
  if ($tok === '') return [0, ['err'=>'github-token-missing']];
  $ch = curl_init('https://api.github.com/repos/' . NZ_REPO . '/contents/' . str_replace('%2F', '/', rawurlencode($path)) . ($method === 'GET' ? '?ref=' . NZ_BRANCH : ''));
  $hdr = ['Authorization: Bearer ' . $tok, 'Accept: application/vnd.github+json', 'User-Agent: farooqstars-nizam', 'X-GitHub-Api-Version: 2022-11-28'];
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>25, CURLOPT_HTTPHEADER=>$hdr, CURLOPT_CUSTOMREQUEST=>$method]);
  if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
  $r = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
  return [$code, json_decode((string)$r, true) ?: []];
}
function nz_read($path){            /* [content|null, sha|null] */
  [$c, $j] = nz_gh('GET', $path);
  if ($c !== 200 || empty($j['content'])) return [null, null];
  return [base64_decode(str_replace("\n", '', $j['content'])), $j['sha'] ?? null];
}
function nz_write($path, $content, $msg, $sha = null){
  $b = ['message'=>$msg, 'content'=>base64_encode($content), 'branch'=>NZ_BRANCH];
  if ($sha) $b['sha'] = $sha;
  [$c, $j] = nz_gh('PUT', $path, $b);
  return $c === 200 || $c === 201 ? true : ($j['message'] ?? ('http ' . $c));
}
function nz_safe_path($p){
  $p = str_replace('\\', '/', (string)$p);
  if ($p === '' || strpos($p, '..') !== false) return null;
  $ok = preg_match('#^projects/FA-[0-9]{3}-[a-z0-9-]+/_nizam/(STATUS|LOG|IDEAS|PLAN|MAINTENANCE)\.md$#', $p)
     || preg_match('#^projects/FA-[0-9]{3}-[a-z0-9-]+/_nizam/(TASKS|SCHEDULE|DEPLOY)\.json$#', $p)
     || preg_match('#^projects/FA-[0-9]{3}-[a-z0-9-]+/_nizam/prompts/S-[0-9]{3}\.md$#', $p)
     || in_array($p, ['START.md','START.ur.md','RULES.md','RULES.ur.md','registry.json','agents.json','stats.json','schedules.json'], true);
  return $ok ? $p : null;
}
function nz_project_slug($id){
  [$reg] = nz_read('registry.json'); $r = json_decode((string)$reg, true);
  foreach (($r['projects'] ?? []) as $p) if (($p['id'] ?? '') === $id) return $p['slug'];
  return null;
}
function nz_cache_get($key, $ttl){ $f = nz_dir() . "/cache-$key"; return (is_file($f) && time() - filemtime($f) < $ttl) ? (string)file_get_contents($f) : null; }
function nz_cache_put($key, $v){ @file_put_contents(nz_dir() . "/cache-$key", $v, LOCK_EX); }

/* ---------- who is knocking ---------- */
$in = $_SERVER['REQUEST_METHOD'] === 'POST' ? (json_decode((string)file_get_contents('php://input'), true) ?: $_POST) : $_GET;
$action = (string)($in['action'] ?? 'whoami');
$tokIn = (string)($_SERVER['HTTP_X_NIZAM_TOKEN'] ?? ($in['token'] ?? ''));
$badge = null; $me = null; $isOwner = false;
if ($tokIn !== '' && preg_match('/^[A-Za-z0-9]{32,64}$/', $tokIn)) {
  $h = hash('sha256', $tokIn);
  foreach (nz_tokens() as $b => $row) if (!empty($row['hash']) && hash_equals($row['hash'], $h) && empty($row['revoked'])) { $badge = $b; break; }
}
if (!$badge) {
  $me = fs_session_user();
  $isOwner = $me && in_array(strtolower(trim((string)($me['email'] ?? ''))), NZ_OWNERS, true);
}
if (!$badge && !$isOwner) nz_out(['ok'=>false, 'err'=>'auth', 'hint'=>'login (owner) or X-Nizam-Token'], 401);
$who = $badge ?: 'owner';

/* ============================ read ============================ */
if ($action === 'whoami') nz_out(['ok'=>true, 'v'=>NZ_VERSION, 'who'=>$who, 'repo'=>NZ_REPO, 'github'=>(nz_secret()['github_token'] ?? '') !== '' ? 'set' : 'missing', 'badges'=>array_keys(array_filter(nz_tokens(), fn($r)=>empty($r['revoked']))), 'now'=>['doha'=>nz_doha()]]);

if ($action === 'start') {
  $lang = ($in['lang'] ?? 'en') === 'ur' ? 'ur' : 'en';
  $file = $lang === 'ur' ? 'START.ur.md' : 'START.md';
  $md = nz_cache_get("start-$lang", 300);
  if ($md === null) { [$md] = nz_read($file); if ($md === null) nz_out(['ok'=>false, 'err'=>'github-read', 'file'=>$file], 502); nz_cache_put("start-$lang", $md); }
  if (($in['fmt'] ?? '') === 'md') { header('Content-Type: text/markdown; charset=utf-8'); echo $md; exit; }
  nz_out(['ok'=>true, 'who'=>$who, 'lang'=>$lang, 'md'=>$md]);
}
if ($action === 'schedules') { [$c] = nz_read('schedules.json'); nz_out(['ok'=>true, 'schedules'=>$c === null ? null : json_decode($c, true), 'note'=>$c === null ? 'schedules.json not built yet (NAS cron, every 15 min)' : '']); }
if ($action === 'stats') { [$c] = nz_read('stats.json'); nz_out(['ok'=>true, 'stats'=>$c === null ? null : json_decode($c, true)]); }
if ($action === 'registry') { [$c] = nz_read('registry.json'); if ($c === null) nz_out(['ok'=>false,'err'=>'github-read'], 502); nz_out(['ok'=>true, 'registry'=>json_decode($c, true)]); }
if ($action === 'file') {
  $p = nz_safe_path($in['path'] ?? ''); if (!$p) nz_out(['ok'=>false, 'err'=>'path'], 400);
  [$c] = nz_read($p); if ($c === null) nz_out(['ok'=>false, 'err'=>'github-read', 'path'=>$p], 502);
  if (($in['fmt'] ?? '') === 'raw') { header('Content-Type: text/plain; charset=utf-8'); echo $c; exit; }
  nz_out(['ok'=>true, 'path'=>$p, 'content'=>$c]);
}

/* ============================ owner-only admin ============================ */
if ($action === 'setup') {
  if (!$isOwner) nz_out(['ok'=>false, 'err'=>'owner-only'], 403);
  $t = trim((string)($in['github_token'] ?? '')); if (!preg_match('/^(ghp_|github_pat_)[A-Za-z0-9_]{20,}$/', $t)) nz_out(['ok'=>false, 'err'=>'token-format'], 400);
  $s = nz_secret(); $s['github_token'] = $t; $s['set_at'] = nz_doha(); nz_secret_save($s);
  [$c] = nz_read('registry.json'); nz_audit('owner', 'setup github token · test ' . ($c === null ? 'FAILED' : 'ok'));
  nz_out(['ok'=>$c !== null, 'test'=>$c === null ? 'cannot read registry.json — check token scope (Contents: read & write on nizam-data)' : 'registry.json read ok']);
}
if ($action === 'mint') {
  if (!$isOwner) nz_out(['ok'=>false, 'err'=>'owner-only'], 403);
  $b = (string)($in['badge'] ?? ''); if (!preg_match('/^AI[0-9]*-[A-Za-z0-9]+$/', $b)) nz_out(['ok'=>false, 'err'=>'badge (e.g. AI1-Claude1)'], 400);
  $plain = bin2hex(random_bytes(20));   /* 40 chars */
  $t = nz_tokens(); $t[$b] = ['hash'=>hash('sha256', $plain), 'minted'=>nz_doha(), 'revoked'=>false]; nz_tokens_save($t);
  nz_audit('owner', "mint $b");
  nz_out(['ok'=>true, 'badge'=>$b, 'token'=>$plain, 'note'=>'shown once — give it to that AI account only; never paste it into any Nizam file']);
}
if ($action === 'revoke') {
  if (!$isOwner) nz_out(['ok'=>false, 'err'=>'owner-only'], 403);
  $b = (string)($in['badge'] ?? ''); $t = nz_tokens(); if (!isset($t[$b])) nz_out(['ok'=>false, 'err'=>'no such badge'], 404);
  $t[$b]['revoked'] = true; nz_tokens_save($t); nz_audit('owner', "revoke $b"); nz_out(['ok'=>true]);
}

if ($action === 'project') {
  if (!$isOwner) nz_out(['ok'=>false, 'err'=>'owner-only'], 403);
  $id = strtoupper(trim((string)($in['id'] ?? ''))); $slugpart = strtolower(trim((string)($in['slug'] ?? '')));
  $en = trim((string)($in['name_en'] ?? '')); $ur = trim((string)($in['name_ur'] ?? ''));
  if (!preg_match('/^FA-[0-9]{3}$/', $id) || !preg_match('/^[a-z0-9-]{2,40}$/', $slugpart) || $en === '' || $ur === '') nz_out(['ok'=>false, 'err'=>'id FA-000, slug a-z0-9-, name_en, name_ur required'], 400);
  [$rc, $rsha] = nz_read('registry.json'); $reg = json_decode((string)$rc, true); if (!is_array($reg)) nz_out(['ok'=>false, 'err'=>'github-read registry'], 502);
  foreach ($reg['projects'] as $p) if ($p['id'] === $id) nz_out(['ok'=>false, 'err'=>'id exists'], 409);
  $slug = "$id-$slugpart"; $today = nz_doha('Y-m-d');
  $reg['projects'][] = ['id'=>$id, 'slug'=>$slug, 'name'=>['en'=>$en, 'ur'=>$ur], 'status'=>'yellow', 'owner_ai'=>'AI1-Claude1',
    'escalation'=>['AI1-Claude1','AI2-Claude2','AI-Zain'], 'created'=>['date'=>$today, 'by'=>'owner', 'where'=>'start.html'],
    'finish_line'=>'', 'next_step'=>['en'=>'', 'ur'=>''], 'links'=>[], 'maintenance'=>[]];
  $r = nz_write('registry.json', json_encode($reg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n", "PROJECT $id created · owner", $rsha);
  if ($r !== true) nz_out(['ok'=>false, 'err'=>$r], 502);
  $b = "projects/$slug/_nizam/";
  $files = [
    'STATUS.md' => "# STATUS — $id $en\n*One live file. Rewrite \"Now\" and \"Next\" at the end of every session.*\n\n## Now ($today)\n- 🟡 created from start.html\n\n## Next\n1. …\n\n## Decisions (owner's)\n\n## Never\n",
    'LOG.md' => "# LOG — append only · `YYYY-MM-DD HH:MM · BADGE · TYPE · text · evidence`\n" . nz_doha() . " · owner · NOTE · project created from start.html\n",
    'IDEAS.md' => "# IDEAS — parked for later · `YYYY-MM-DD · from · idea · why`\n",
    'PLAN.md' => "# PLAN — phases with gates (gate over date)\n| # | Phase | Delivers | Gate |\n|---|---|---|---|\n| 0 | … | … | … |\n\nFinish line: \n",
    'TASKS.json' => "{\"tasks\": []}\n",
    'MAINTENANCE.md' => "# MAINTENANCE — expiries, renewals, checks\n| What | Where | Due | Owner | Last done |\n|---|---|---|---|---|\n",
  ];
  foreach ($files as $f => $content) { $w = nz_write($b . $f, $content, "PROJECT $id · $f"); if ($w !== true) nz_out(['ok'=>false, 'err'=>"$f: $w"], 502); }
  nz_audit('owner', "project $id"); nz_out(['ok'=>true, 'id'=>$id, 'slug'=>$slug, 'note'=>'folders on the NAS appear within 15 min (cron)']);
}

/* ============================ schedules (GET or POST — alarms call this from the cloud) ============================ */
if ($action === 'schedule') {
  $pid = strtoupper(trim((string)($in['project'] ?? ''))); $slug = preg_match('/^FA-[0-9]{3}$/', $pid) ? nz_project_slug($pid) : null;
  if (!$slug) nz_out(['ok'=>false, 'err'=>'project (FA-000) not in registry'], 400);
  $sid = strtoupper(trim((string)($in['id'] ?? ''))); if (!preg_match('/^S-[0-9]{3}$/', $sid)) nz_out(['ok'=>false, 'err'=>'id S-000'], 400);
  $op = strtolower(trim((string)($in['op'] ?? ''))); if (!in_array($op, ['claim','done','failed','skip','pause','resume'], true)) nz_out(['ok'=>false, 'err'=>'op'], 400);
  if (in_array($op, ['pause','resume'], true) && !$isOwner) nz_out(['ok'=>false, 'err'=>'owner-only'], 403);
  $note = mb_substr(trim(preg_replace('/[\r\n]+/', ' ', (string)($in['note'] ?? ''))), 0, 300);
  if (preg_match('/(ghp_|github_pat_|sk-[A-Za-z0-9]{10}|tskey-|AGK-[0-9a-f]{8}|BEGIN [A-Z ]*PRIVATE KEY|password\s*[:=]\s*\S{6,})/i', $note)) nz_out(['ok'=>false, 'err'=>'secret-refused (rule 9)'], 400);
  $path = "projects/$slug/_nizam/SCHEDULE.json";
  [$c, $sha] = nz_read($path); $j = json_decode((string)$c, true); if (!is_array($j)) nz_out(['ok'=>false, 'err'=>'no SCHEDULE.json for ' . $pid], 404);
  $found = null; foreach ($j['schedules'] as $k => $s) if (($s['id'] ?? '') === $sid) $found = $k;
  if ($found === null) nz_out(['ok'=>false, 'err'=>'schedule not found'], 404);
  $s = &$j['schedules'][$found]; $today = nz_doha('Y-m-d'); $now = nz_doha();
  $last = $s['last_run'] ?? [];
  if ($op === 'claim') {
    /* another badge already claimed this slot today and is not failed → refuse (watchdog rule) */
    if (($last['date'] ?? '') === $today && in_array($last['state'] ?? '', ['claimed','done'], true) && ($last['badge'] ?? '') !== $who)
      nz_out(['ok'=>false, 'err'=>'already-' . $last['state'], 'by'=>$last['badge'], 'at'=>$last['at'] ?? '', 'hint'=>'stop — this slot is taken'], 409);
    if (!empty($s['paused_until']) && $s['paused_until'] >= $today) nz_out(['ok'=>false, 'err'=>'paused', 'until'=>$s['paused_until'], 'hint'=>'stop — owner paused this schedule'], 409);
    $s['last_run'] = ['date'=>$today, 'at'=>$now, 'badge'=>$who, 'state'=>'claimed', 'note'=>$note];
  } elseif ($op === 'done' || $op === 'failed' || $op === 'skip') {
    $s['last_run'] = ['date'=>$today, 'at'=>$now, 'badge'=>$who, 'state'=>$op === 'skip' ? 'skipped' : $op, 'note'=>$note];
    if ($op === 'done') { $s['runs_done'] = (int)($s['runs_done'] ?? 0) + 1; $s['last_done'] = $today; }
    if ($op === 'failed') $s['fails'] = (int)($s['fails'] ?? 0) + 1;
  } elseif ($op === 'pause') {
    $days = max(1, min(60, (int)($in['days'] ?? 1))); $s['paused_until'] = gmdate('Y-m-d', time() + 3 * 3600 + $days * 86400 - 86400); $s['paused_by'] = 'owner';
  } else { $s['paused_until'] = ''; $s['paused_by'] = ''; }
  unset($s); $j['updated'] = $now;
  $r = nz_write($path, json_encode($j, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n", "SCHEDULE $pid $sid $op · $who", $sha);
  if ($r !== true) nz_out(['ok'=>false, 'err'=>$r], 502);
  $type = ['claim'=>'CLAIM','done'=>'DONE','failed'=>'BLOCKED','skip'=>'NOTE','pause'=>'DECISION','resume'=>'DECISION'][$op];
  [$lc, $lsha] = nz_read("projects/$slug/_nizam/LOG.md");
  if ($lc !== null) nz_write("projects/$slug/_nizam/LOG.md", rtrim($lc, "\n") . "\n" . "$now · $who · $type · schedule $sid $op" . ($note !== '' ? " · $note" : '') . "\n", "LOG $pid · $who · schedule $op", $lsha);
  nz_audit($who, "schedule $pid $sid $op"); nz_out(['ok'=>true, 'project'=>$pid, 'id'=>$sid, 'op'=>$op, 'by'=>$who, 'at'=>$now, 'schedule'=>$j['schedules'][$found]]);
}

/* ============================ write (commit to GitHub) ============================ */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') nz_out(['ok'=>false, 'err'=>'POST'], 405);
$pid = strtoupper(trim((string)($in['project'] ?? ''))); $slug = preg_match('/^FA-[0-9]{3}$/', $pid) ? nz_project_slug($pid) : null;
if (!$slug) nz_out(['ok'=>false, 'err'=>'project (FA-000) not in registry'], 400);
$base = "projects/$slug/_nizam/";
$clean = fn($s, $n) => mb_substr(trim(preg_replace('/[\r\n]+/', ' ', (string)$s)), 0, $n);
/* rule 9 — refuse obvious secrets */
$looksSecret = fn($s) => (bool)preg_match('/(ghp_|github_pat_|sk-[A-Za-z0-9]{10}|tskey-|AGK-[0-9a-f]{8}|BEGIN [A-Z ]*PRIVATE KEY|password\s*[:=]\s*\S{6,})/i', (string)$s);

if ($action === 'log') {
  $type = strtoupper($clean($in['type'] ?? 'NOTE', 10)); if (!in_array($type, NZ_TYPES, true)) $type = 'NOTE';
  $text = $clean($in['text'] ?? '', 600); $ev = $clean($in['evidence'] ?? '', 300);
  if ($text === '') nz_out(['ok'=>false, 'err'=>'text'], 400);
  if ($looksSecret($text) || $looksSecret($ev)) nz_out(['ok'=>false, 'err'=>'secret-refused (rule 9)'], 400);
  [$c, $sha] = nz_read($base . 'LOG.md'); if ($c === null) nz_out(['ok'=>false, 'err'=>'github-read LOG.md'], 502);
  $line = nz_doha() . " · $who · $type · $text" . ($ev !== '' ? " · $ev" : '');
  $r = nz_write($base . 'LOG.md', rtrim($c, "\n") . "\n$line\n", "LOG $pid · $who · $type", $sha);
  nz_audit($who, "log $pid $type"); nz_out($r === true ? ['ok'=>true, 'line'=>$line] : ['ok'=>false, 'err'=>$r], $r === true ? 200 : 502);
}
if ($action === 'idea') {
  $text = $clean($in['text'] ?? '', 600); if ($text === '') nz_out(['ok'=>false, 'err'=>'text'], 400);
  if ($looksSecret($text)) nz_out(['ok'=>false, 'err'=>'secret-refused (rule 9)'], 400);
  [$c, $sha] = nz_read($base . 'IDEAS.md'); if ($c === null) nz_out(['ok'=>false, 'err'=>'github-read IDEAS.md'], 502);
  $line = nz_doha('Y-m-d') . " · $who · $text";
  $r = nz_write($base . 'IDEAS.md', rtrim($c, "\n") . "\n$line\n", "IDEA $pid · $who", $sha);
  nz_audit($who, "idea $pid"); nz_out($r === true ? ['ok'=>true, 'line'=>$line] : ['ok'=>false, 'err'=>$r], $r === true ? 200 : 502);
}
if ($action === 'status') {
  $content = (string)($in['content'] ?? ''); if (mb_strlen($content) < 20 || mb_strlen($content) > 60000) nz_out(['ok'=>false, 'err'=>'content size'], 400);
  if ($looksSecret($content)) nz_out(['ok'=>false, 'err'=>'secret-refused (rule 9)'], 400);
  [$c, $sha] = nz_read($base . 'STATUS.md');
  $r = nz_write($base . 'STATUS.md', rtrim($content, "\n") . "\n", "STATUS $pid · $who · " . nz_doha(), $sha);
  nz_audit($who, "status $pid"); nz_out($r === true ? ['ok'=>true] : ['ok'=>false, 'err'=>$r], $r === true ? 200 : 502);
}
if ($action === 'doc') {
  $name = trim((string)($in['name'] ?? '')); $content = (string)($in['content'] ?? '');
  if (!preg_match('/^[A-Za-z0-9._ \-\x{0600}-\x{06FF}]{1,80}\.(md|txt)$/u', $name) || strpos($name, '..') !== false) nz_out(['ok'=>false, 'err'=>'name (letters/digits/-_ .md|.txt)'], 400);
  if (strlen($content) < 10 || strlen($content) > 200000) nz_out(['ok'=>false, 'err'=>'content 10 B – 200 KB'], 400);
  if ($looksSecret($content)) nz_out(['ok'=>false, 'err'=>'secret-refused (rule 9)'], 400);
  $path = "projects/$slug/0-docs/" . nz_doha('Ymd-Hi') . "-$name";
  $r = nz_write($path, rtrim($content, "\n") . "\n", "DOC $pid · $who · $name");
  if ($r !== true) nz_out(['ok'=>false, 'err'=>$r], 502);
  [$lc, $lsha] = nz_read($base . 'LOG.md');
  if ($lc !== null) nz_write($base . 'LOG.md', rtrim($lc, "\n") . "\n" . nz_doha() . " · $who · NOTE · doc dropped → 0-docs · $name\n", "LOG $pid · $who · doc", $lsha);
  nz_audit($who, "doc $pid $name"); nz_out(['ok'=>true, 'path'=>$path, 'note'=>'appears in Y:\\' . $slug . '\\0-docs within 15 min, numbered by the NAS']);
}
if ($action === 'task') {
  $id = $clean($in['id'] ?? '', 12); $state = $clean($in['state'] ?? '', 12);
  if (!preg_match('/^T-[0-9]{3}$/', $id) || !in_array($state, ['pending','doing','done','blocked','handoff'], true)) nz_out(['ok'=>false, 'err'=>'id/state'], 400);
  [$c, $sha] = nz_read($base . 'TASKS.json'); $j = json_decode((string)$c, true); if (!is_array($j)) nz_out(['ok'=>false, 'err'=>'github-read TASKS.json'], 502);
  $found = false;
  foreach ($j['tasks'] as &$t) if (($t['id'] ?? '') === $id) { $found = true; $t['state'] = $state; if (!empty($in['owner'])) $t['owner'] = $clean($in['owner'], 20); if (isset($in['evidence'])) $t['evidence'] = $clean($in['evidence'], 300); $t['updated'] = nz_doha(); $t['by'] = $who; }
  unset($t); if (!$found) nz_out(['ok'=>false, 'err'=>'task not found'], 404);
  $r = nz_write($base . 'TASKS.json', json_encode($j, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n", "TASK $pid $id → $state · $who", $sha);
  nz_audit($who, "task $pid $id $state"); nz_out($r === true ? ['ok'=>true] : ['ok'=>false, 'err'=>$r], $r === true ? 200 : 502);
}
nz_out(['ok'=>false, 'err'=>'unknown action'], 400);
