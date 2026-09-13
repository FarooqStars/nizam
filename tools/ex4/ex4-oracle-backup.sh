#!/usr/bin/env bash
# ex4-oracle-backup.sh — Phase 3 (remaining): nightly copy of the Oracle VM fm2-stream (Farooq Music 2.0) to the EX4.
# Pull only (rsync over Tailscale SSH, key /root/.ssh/nizam_deploy as user deploy@). Never writes to the server. Never deletes locally:
# each night lands in /data/Nizam/projects/FA-002-farooq-music/_archive/oracle/YYYY-MM-DD (hard-linked against the previous night, so
# unchanged files cost no space). One LOG line per run. Cron 03:15 (installed by ex4-nizam.sh v2).
# Owner's one-time step: add /root/.ssh/nizam_deploy.pub to deploy@fm2-stream authorized_keys (via FM2-*.bat on HOME-01). No new VM, no reset.
HOST=100.107.73.115; USER=deploy
SRC_LIST="/var/www /etc/nginx /etc/systemd/system /home/deploy"     # adjust after the first dry-run (see FM2-LIVE-STATUS for the real paths)
DST=/data/Nizam/projects/FA-002-farooq-music/_archive/oracle; LOG=/var/log/nizam-oracle-backup.log
NZLOG=/data/Nizam/nizam-data/projects/FA-002-farooq-music/_nizam/LOG.md
today=$(date +%F); prev=$(ls -1d $DST/20* 2>/dev/null | tail -1)
mkdir -p $DST/$today
{
echo "== $(date '+%F %T') oracle backup start"
if ! ssh -o BatchMode=yes -o ConnectTimeout=15 -o StrictHostKeyChecking=accept-new -i /root/.ssh/nizam_deploy $USER@$HOST true 2>&1; then
  echo "NO_SSH: deploy@$HOST refused the EX4 key — owner must add /root/.ssh/nizam_deploy.pub to authorized_keys"; res="BLOCKED · oracle backup · ssh key not accepted yet"
else
  ok=1
  for s in $SRC_LIST; do
    rsync -az --relative ${prev:+--link-dest=$prev} -e "ssh -o BatchMode=yes -i /root/.ssh/nizam_deploy" "$USER@$HOST:$s" "$DST/$today/" 2>&1 || ok=0
  done
  n=$(find $DST/$today -type f | wc -l); sz=$(du -sh $DST/$today | cut -f1)
  [ $ok = 1 ] && res="MAINT · oracle nightly copy ok · $n files · $sz · $DST/$today" || res="BLOCKED · oracle nightly copy partial · $n files · see $LOG"
  echo "$res"
fi
[ -f "$NZLOG" ] && echo "$(date '+%Y-%m-%d %H:%M') · ex4-backup · $res" >> "$NZLOG"
echo "== $(date '+%F %T') done"
} >> $LOG 2>&1
tail -c 200000 $LOG > $LOG.tmp && mv $LOG.tmp $LOG
