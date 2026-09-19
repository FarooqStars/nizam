#!/usr/bin/env bash
# ex4-oracle-backup.sh v2.1 - nightly PULL copy of the Oracle VM fm2-stream (FA-002 Farooq Music 2.0) to the EX4.
# v2 (19 Sep 2026, owner-approved "safe route"): logs in at the server's PUBLIC address (OpenSSH + authorized_keys).
#   v1 used the Tailscale IP 100.107.73.115. There Tailscale SSH answers instead of OpenSSH and asks for a browser
#   "check" that a night job can never pass -> it waited 30 min and failed every night 14-19 Sep. Do NOT point it back there.
# Settings: /root/.nizam/oracle-backup.conf (HOST USER KEY SRC_LIST) - local to the EX4, never in a repo.
# Switch:   /data/Nizam/nizam-data/projects/FA-002-farooq-music/_nizam/ORACLE-BACKUP.json  {"enabled": true|false, "exclude": [..]}
#           file missing / unreadable / enabled not true  ->  DRY-RUN only, nothing is copied.
# Secrets are NEVER copied: the excludes below are fixed in this script; the switch file can only ADD excludes.
# Pull only - never writes to the server. Never deletes on the EX4: each good night = _archive/oracle/YYYY-MM-DD,
#   hard-linked against the last good night (unchanged files cost no space). A folder is made only after a good login.
# v2.1 (19 Sep 2026): every run first LISTS what would come (dry list); a real copy is REFUSED if any secret-looking
#   name is in that list (found: home/deploy/.config/psysh/psysh_history), and all *_history files are excluded.
# One LOG.md line per night. Cron 03:15 (/etc/cron.d/nizam-deploy). Manual test: ex4-oracle-backup.sh --dry-run (no LOG line).
CONF=/root/.nizam/oracle-backup.conf
SW=/data/Nizam/nizam-data/projects/FA-002-farooq-music/_nizam/ORACLE-BACKUP.json
DST=/data/Nizam/projects/FA-002-farooq-music/_archive/oracle
LOG=/var/log/nizam-oracle-backup.log
NZLOG=/data/Nizam/nizam-data/projects/FA-002-farooq-music/_nizam/LOG.md
DRYLIST=/var/log/nizam-oracle-dry-list.txt
HOST=""; USER=deploy; KEY=/root/.ssh/nizam_deploy; SRC_LIST=""
[ -f "$CONF" ] && . "$CONF"
SECRET_EXCLUDES=( '.env' '.env.*' '.ssh/' 'id_rsa*' 'id_ed25519*' 'id_ecdsa*' '*.pem' '*.key' '*.token' '*.p12' '*.pfx'
                  '*_history' '.lesshst' '.pgpass' '.netrc' '.git-credentials' 'auth.json' '.config/gh/' '.docker/config.json' )
SECRET_RE='(^|/)\.env($|\.)|(^|/)\.ssh/|(^|/)id_(rsa|ed25519|ecdsa)|\.(pem|key|token|p12|pfx)$|_history$|(^|/)\.(pgpass|netrc|git-credentials|lesshst)$|(^|/)auth\.json$|(^|/)\.config/gh/|(^|/)\.docker/config\.json$'
MANUAL=0; [ "${1:-}" = "--dry-run" ] && MANUAL=1
mapfile -t SWL < <(python3 - "$SW" <<'PY'
import json, sys
try:
    d = json.load(open(sys.argv[1], encoding='utf-8'))
except Exception:
    d = {}
print('1' if d.get('enabled') is True else '0')
for x in d.get('exclude', []) if isinstance(d.get('exclude', []), list) else []:
    if isinstance(x, str) and x.strip() and '\n' not in x:
        print(x.strip())
PY
)
ENABLED=${SWL[0]:-0}
MODE=real; { [ "$ENABLED" = 1 ] && [ $MANUAL = 0 ]; } || MODE=dry
EXC=(); for p in "${SECRET_EXCLUDES[@]}" "${SWL[@]:1}"; do EXC+=( "--exclude=$p" ); done
SSHC="ssh -o BatchMode=yes -o IdentitiesOnly=yes -o StrictHostKeyChecking=yes -o ConnectTimeout=20 -o ServerAliveInterval=30 -i $KEY"
today=$(date +%F)
nzlog(){ [ $MANUAL = 0 ] && [ -f "$NZLOG" ] && echo "$(date '+%Y-%m-%d %H:%M') · ex4-backup · $1" >> "$NZLOG"; }
human(){ numfmt --to=iec --suffix=B "${1:-0}" 2>/dev/null || echo "${1:-0} bytes"; }

main(){
echo "== $(date '+%F %T') oracle backup start · mode=$MODE · switch enabled=$ENABLED · extra excludes=$(( ${#SWL[@]} - 1 < 0 ? 0 : ${#SWL[@]} - 1 ))"
if [ -z "$HOST" ] || [ -z "$SRC_LIST" ]; then
  res="BLOCKED · oracle backup · settings file $CONF missing or empty"
elif ! timeout 90 $SSHC "$USER@$HOST" true 2>&1; then
  res="BLOCKED · oracle backup · login to $USER@$HOST failed (see $LOG on the EX4)"
else
  : > "$DRYLIST"; ok=1
  for s in $SRC_LIST; do
    rsync -azn --relative "${EXC[@]}" --out-format='%l %n' -e "$SSHC" "$USER@$HOST:$s" /tmp/nizam-oracle-dry/ >> "$DRYLIST" 2>/tmp/nizam-oracle-dry.err
    rc=$?; if [ $rc != 0 ] && [ $rc != 24 ]; then ok=0; echo "  rsync list $s exit $rc:"; head -n 15 /tmp/nizam-oracle-dry.err | sed 's/^/    /'; fi
  done
  read -r n bytes < <(awk '$2 !~ /\/$/ {n++; s+=$1} END {print n+0, s+0}' "$DRYLIST")
  leak=$(awk '{ $1=""; sub(/^ /,""); print }' "$DRYLIST" | grep -E "$SECRET_RE" | head -n 5)
  echo "  list: $n files · $(human "$bytes")"
  echo "  biggest folders:"
  awk '$2 !~ /\/$/ { split($2,a,"/"); k=a[1]"/"a[2]"/"a[3]; s[k]+=$1; c[k]++ } END { for (k in s) print s[k], c[k], k }' "$DRYLIST" \
    | sort -rn | head -n 12 | while read -r b c k; do printf '    %-40s %6s files  %s\n' "$k" "$c" "$(human "$b")"; done
  if [ -n "$leak" ]; then
    echo "  SECRET-LOOKING NAMES IN THE LIST (nothing copied):"; echo "$leak" | sed 's/^/    /'
    res="BLOCKED · oracle backup · secret-looking file names in the list - NOTHING copied ($MODE)"
  elif [ $MODE = dry ]; then
    [ $ok = 1 ] && res="MAINT · oracle backup DRY-RUN ok (switch off, nothing copied) · $n files · $(human "$bytes") would be copied · secret check 0" \
                || res="BLOCKED · oracle backup DRY-RUN · rsync errors (see $LOG on the EX4)"
  else
    prev=""; for d in $(ls -1d "$DST"/20*/ 2>/dev/null); do d=${d%/}; [ "$d" = "$DST/$today" ] && continue; [ -n "$(ls -A "$d" 2>/dev/null)" ] && prev=$d; done
    mkdir -p "$DST/$today"; ok=1
    for s in $SRC_LIST; do
      rsync -az --relative "${EXC[@]}" ${prev:+--link-dest="$prev"} -e "$SSHC" "$USER@$HOST:$s" "$DST/$today/" 2>&1
      rc=$?; [ $rc = 0 ] || [ $rc = 24 ] || { ok=0; echo "  rsync $s exit $rc"; }
    done
    n=$(find "$DST/$today" -type f | wc -l); sz=$(du -sh "$DST/$today" | cut -f1)
    leak=$(cd "$DST/$today" && find . -type f | sed 's#^\./##' | grep -E "$SECRET_RE" | head -n 3)
    if [ -n "$leak" ]; then res="BLOCKED · oracle nightly copy · secret-looking file copied - check at once: $(echo $leak)"
    elif [ $ok = 1 ]; then res="MAINT · oracle nightly copy ok · $n files · $sz · $DST/$today"
    else res="BLOCKED · oracle nightly copy partial · $n files · see $LOG on the EX4"; fi
  fi
fi
echo "$res"
nzlog "$res"
echo "== $(date '+%F %T') done"
}
if [ $MANUAL = 1 ]; then main 2>&1 | tee -a "$LOG"; else main >> "$LOG" 2>&1; fi
tail -c 200000 "$LOG" > "$LOG.tmp" && mv "$LOG.tmp" "$LOG"
