#!/usr/bin/env bash
# ex4-sites-backup.sh — nightly pull of every live website (Hostinger) into its project's 2-source on the NAS.
# Owner's rule: the live site IS the final source → 2-source/<domain>/ is an exact mirror of the server.
# Files the server deleted/changed are NOT lost: they go to _archive/site-history/<date>/ (rsync --backup). Pull only — never writes to the server.
# Key: /root/.ssh/nizam_deploy (public half must be added in hPanel → Advanced → SSH Access → Add SSH key, and SSH must be ENABLED there).
# Config: /root/.nizam/sites.conf · Cron 02:30 nightly (installed by EX4-SITES.bat) · Log /var/log/nizam-sites.log · one LOG.md line per site
# Usage: ex4-sites-backup.sh [--test]   (--test = only check the SSH login of every site, copy nothing)
CONF=/root/.nizam/sites.conf; KEY=/root/.ssh/nizam_deploy; P=/data/Nizam/projects; NZ=/data/Nizam/nizam-data/projects; L=/var/log/nizam-sites.log
today=$(date +%F); TEST=0; [ "${1:-}" = "--test" ] && TEST=1
SSH="ssh -i $KEY -o BatchMode=yes -o ConnectTimeout=20 -o StrictHostKeyChecking=accept-new"
{
echo "== $(date '+%F %T') sites backup start (test=$TEST)"
grep -v '^\s*#' "$CONF" | grep '|' | while IFS='|' read -r dom slug host port user rpath; do
  dom=$(echo $dom); slug=$(echo $slug); host=$(echo $host); port=$(echo $port); user=$(echo $user); rpath=$(echo $rpath)
  [ -z "$dom" ] && continue
  if ! $SSH -n -p "$port" "$user@$host" "test -d $rpath" 2>/tmp/nz-ssh.err </dev/null; then
    echo "$dom: NO_SSH ($(tail -1 /tmp/nz-ssh.err | cut -c1-90)) — enable SSH + add the EX4 key in hPanel for $user"; continue; fi
  if [ $TEST = 1 ]; then echo "$dom: ssh ok ($user@$host:$port $rpath)"; continue; fi
  dst=$P/$slug/2-source/$dom; hist=$P/$slug/_archive/site-history/$today; mkdir -p "$dst" "$hist"
  if rsync -az --delete --backup --backup-dir="$hist" --exclude '.cache' --exclude 'error_log' -e "$SSH -p $port" "$user@$host:$rpath/" "$dst/" >>$L.rsync 2>&1 </dev/null; then
    n=$(find "$dst" -type f | wc -l); sz=$(du -sh "$dst" | cut -f1); ch=$(find "$hist" -type f 2>/dev/null | wc -l); [ "$ch" = 0 ] && rmdir "$hist" 2>/dev/null
    res="MAINT · site backup $dom → 2-source · $n files · $sz · changed/removed kept: $ch"; echo "$dom: ok $n files $sz (history $ch)"
  else res="BLOCKED · site backup $dom failed · see /var/log/nizam-sites.log.rsync"; echo "$dom: FAIL"; fi
  chown -R farooq:nasusers "$P/$slug/2-source" "$P/$slug/_archive" 2>/dev/null; find "$P/$slug/2-source" "$P/$slug/_archive" -type d -exec chmod 2770 {} + 2>/dev/null
  [ -f "$NZ/$slug/_nizam/LOG.md" ] && echo "$(date '+%Y-%m-%d %H:%M') · ex4-backup · $res" >> "$NZ/$slug/_nizam/LOG.md"
done
echo "== $(date '+%F %T') done"
} >> $L 2>&1
tail -c 200000 $L > $L.tmp && mv $L.tmp $L
[ $TEST = 1 ] && tail -12 $L
