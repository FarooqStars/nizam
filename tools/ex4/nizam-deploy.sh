#!/usr/bin/env bash
# nizam-deploy.sh — Phase 4b: push each project's 3-final to its live server when it changed.
# Reads projects/<slug>/_nizam/DEPLOY.json (kind: ssh | git | http). enabled=false → dry-run only.
# Cron: */5 * * * * root /usr/local/bin/nizam-deploy.sh   (installed by ex4-nizam.sh v2)
# Log: /var/log/nizam-deploy.log · one LOG.md line per deploy · never deletes anything on the server (rsync without --delete unless rsync_delete=true)
NZ=/data/Nizam; L=/var/log/nizam-deploy.log; STATE=/var/lib/nizam; mkdir -p $STATE
DATA=$NZ/nizam-data; PROJ=$NZ/projects
command -v python3 >/dev/null || exit 0
{
for cfg in $DATA/projects/*/_nizam/DEPLOY.json; do
  [ -f "$cfg" ] || continue
  slug=$(basename "$(dirname "$(dirname "$cfg")")"); pid=${slug%%-*}-${slug#*-}; pid=${pid:0:6}
  src=$PROJ/$slug/3-final; [ -d "$src" ] || continue
  # fingerprint of 3-final (names + sizes + mtimes) — deploy only when it changed
  fp=$(find "$src" -type f -printf '%P %s %T@\n' | sort | md5sum | cut -c1-32)
  [ "$(cat $STATE/$slug.fp 2>/dev/null)" = "$fp" ] && continue
  echo "== $(date '+%F %T') $slug changed"
  # refuse junk
  junk=$(find "$src" \( -name .git -o -name node_modules -o -name '*.bak' -o -name '*.tmp' -o -name Thumbs.db -o -name .DS_Store -o -name desktop.ini \) | head -3)
  if [ -n "$junk" ]; then echo "REFUSED junk in 3-final: $junk"; python3 - "$DATA/projects/$slug/_nizam/LOG.md" "$pid" "BLOCKED · deploy refused · junk in 3-final: $(echo $junk | tr '\n' ' ')" <<'PY'
import sys,datetime; open(sys.argv[1],'a',encoding='utf-8').write(f"{datetime.datetime.now():%Y-%m-%d %H:%M} · ex4-deploy · {sys.argv[3]}\n")
PY
  echo "$fp" > $STATE/$slug.fp; continue; fi
  n=$(find "$src" -type f | wc -l)
  enabled=$(python3 -c "import json,sys;print(json.load(open(sys.argv[1])).get('enabled',False))" "$cfg")
  results=""
  while IFS=$'\t' read -r kind name host user path repo branch url rdel; do
    [ -z "$kind" ] && continue
    if [ "$enabled" != "True" ]; then results="$results [$kind $name: DRY-RUN $n files]"; continue; fi
    case $kind in
      ssh)  opts="-az"; [ "$rdel" = "True" ] && opts="$opts --delete"
            if rsync $opts -e "ssh -o BatchMode=yes -o StrictHostKeyChecking=accept-new -i /root/.ssh/nizam_deploy" "$src/" "$user@$host:$path" >>$L.rsync 2>&1; then results="$results [ssh $name: ok]"; else results="$results [ssh $name: FAIL]"; fi ;;
      git)  w=$STATE/git-$slug; if [ ! -d $w/.git ]; then git clone -q -b "$branch" "$repo" $w 2>>$L || { results="$results [git $name: clone FAIL]"; continue; }; fi
            git -C $w pull -q 2>>$L; rsync -a --delete --exclude .git "$src/" "$w/"; git -C $w add -A
            if git -C $w diff --cached --quiet; then results="$results [git $name: no change]"; else git -C $w commit -q -m "deploy $slug · ex4 $(date '+%F %H:%M')" && git -C $w push -q 2>>$L && results="$results [git $name: pushed]" || results="$results [git $name: push FAIL]"; fi ;;
      http) tok=""; [ -f "$path" ] && tok=$(cat "$path")   # for http, 'path' column carries token_file
            z=/tmp/nz-$slug.zip; rm -f $z; (cd "$src" && zip -qr $z . -x '.git/*')
            code=$(curl -s -o /tmp/nz-$slug.out -w '%{http_code}' -H "X-Deploy-Token: $tok" -F "zip=@$z" "$url"); [ "$code" = "200" ] && results="$results [http $name: ok]" || results="$results [http $name: HTTP $code]"; rm -f $z ;;
    esac
  done < <(python3 - "$cfg" <<'PY'
import json,sys
for t in json.load(open(sys.argv[1])).get('targets',[]):
    print('\t'.join(str(t.get(k,'')) for k in ('kind','name','host','user','path' if t.get('kind')!='http' else 'token_file','repo','branch','url','rsync_delete')))
PY
)
  echo "$slug: $n files$results"
  python3 - "$DATA/projects/$slug/_nizam/LOG.md" "$n" "$results" <<'PY'
import sys,datetime; open(sys.argv[1],'a',encoding='utf-8').write(f"{datetime.datetime.now():%Y-%m-%d %H:%M} · ex4-deploy · MAINT · deploy 3-final · {sys.argv[2]} files{sys.argv[3]}\n")
PY
  echo "$fp" > $STATE/$slug.fp
done
} >> $L 2>&1
tail -c 200000 $L > $L.tmp && mv $L.tmp $L
