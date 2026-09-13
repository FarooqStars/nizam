#!/usr/bin/env bash
# ex4-nizam.sh v2 — Nizam 2.0 Phase 3 + 4b: the EX4 is the master copy of the Nizam record, builds START/stats/schedules,
# and (v2) runs nizam-deploy.sh (3-final → server, dry-run until DEPLOY.json enabled=true) + Oracle nightly copy.
# /data/Nizam/nizam (public repo) + /data/Nizam/nizam-data (private repo, via a deploy key made HERE)
# + cron every 15 min: pull → build START → commit/push. Touches nothing else. Idempotent.
# Run as root:  bash /tmp/ex4-nizam.sh
set -uo pipefail
NZ=/data/Nizam; KEY=/root/.ssh/nizam_deploy
echo "== $(date '+%F %T') nizam start"
mountpoint -q /data || { echo "ERROR: /data not mounted — stop"; exit 1; }

echo "== tools"
command -v git >/dev/null || { apt-get update -qq && apt-get install -y -qq git; }
command -v python3 >/dev/null || apt-get install -y -qq python3
git --version | sed 's/^/git: /'; python3 --version | sed 's/^/python: /'
git config --global user.name  >/dev/null || git config --global user.name  "ex4-nizam"
git config --global user.email >/dev/null || git config --global user.email "ex4@nizam.local"
git config --global pull.rebase true

echo "== deploy key (public half is safe to show)"
install -d -m 700 /root/.ssh
[ -f "$KEY" ] || ssh-keygen -t ed25519 -N "" -C "ex4-nizam-deploy" -f "$KEY" -q
grep -q "nizam_deploy" /root/.ssh/config 2>/dev/null || cat >> /root/.ssh/config <<EOF
Host github.com
  IdentityFile $KEY
  IdentitiesOnly yes
  StrictHostKeyChecking accept-new
EOF
chmod 600 /root/.ssh/config
echo "-----BEGIN DEPLOY KEY (public)-----"; cat "$KEY.pub"; echo "-----END DEPLOY KEY-----"

echo "== public repo nizam"
install -d "$NZ"
if [ -d "$NZ/nizam/.git" ]; then git -C "$NZ/nizam" pull -q && echo "nizam: updated"; else git clone -q https://github.com/farooqmusicai/nizam.git "$NZ/nizam" && echo "nizam: cloned"; fi

echo "== private repo nizam-data (needs the deploy key on GitHub with write access)"
if [ -d "$NZ/nizam-data/.git" ]; then
  git -C "$NZ/nizam-data" pull -q && echo "nizam-data: updated" || echo "WARN: nizam-data pull failed"
else
  if git clone -q git@github.com:farooqmusicai/nizam-data.git "$NZ/nizam-data" 2>/tmp/nz-clone.err; then echo "nizam-data: cloned"
  else echo "NEED_DEPLOY_KEY: add the public key above to GitHub → nizam-data → Settings → Deploy keys (Allow write access), then run this again"; sed 's/^/  /' /tmp/nz-clone.err | tail -3; fi
fi

echo "== real data folders + Samba share Nizam (\\\\ex4\\Nizam)"
install -d -m 2770 -o farooq -g nasusers "$NZ/projects" 2>/dev/null || install -d -m 2775 "$NZ/projects"
if ! grep -q '^\[Nizam\]' /etc/samba/smb.conf; then
  cat >> /etc/samba/smb.conf <<'EOF2'

[Nizam]
   comment = Nizam 2.0 project folders (1-working / 2-source / 3-final)
   path = /data/Nizam/projects
   valid users = farooq @nasusers
   read only = no
   browseable = yes
   create mask = 0660
   directory mask = 2770
   force group = nasusers
EOF2
  systemctl reload smbd 2>/dev/null || systemctl restart smbd; echo "samba: share Nizam added"
else echo "samba: share Nizam present"; fi

echo "== sync script + cron (every 15 min)"
cat > /usr/local/bin/nizam-sync.sh <<'EOF'
#!/usr/bin/env bash
# nizam-sync.sh — pull both repos, rebuild START, push if changed. Log: /var/log/nizam-sync.log
NZ=/data/Nizam; L=/var/log/nizam-sync.log
{ echo "== $(date '+%F %T')"
  git -C $NZ/nizam pull -q 2>&1
  [ -d $NZ/nizam-data/.git ] || { echo "nizam-data missing"; exit 0; }
  cd $NZ/nizam-data && git pull -q 2>&1
  python3 $NZ/nizam/tools/nizam_folders.py $NZ/projects 2>&1
  python3 $NZ/nizam/tools/build_start.py 2>&1
  git add -A START.md START.ur.md stats.json schedules.json projects/*/_nizam/LOG.md projects/*/0-docs 2>/dev/null
  if ! git diff --cached --quiet; then
    git commit -q -m "START + stats + schedules · ex4 $(date '+%Y-%m-%d %H:%M')" && git push -q 2>&1 && echo "pushed"
  else echo "no change"; fi
} >> $L 2>&1
tail -c 200000 $L > $L.tmp && mv $L.tmp $L
EOF
chmod 755 /usr/local/bin/nizam-sync.sh
echo '*/15 * * * * root /usr/local/bin/nizam-sync.sh' > /etc/cron.d/nizam
chmod 644 /etc/cron.d/nizam; systemctl is-active cron >/dev/null || systemctl enable --now cron
echo "cron: /etc/cron.d/nizam installed"

echo "== deploy + oracle-backup scripts (from the public repo)"
for f in nizam-deploy.sh ex4-oracle-backup.sh; do
  [ -f "$NZ/nizam/tools/ex4/$f" ] && install -m 755 "$NZ/nizam/tools/ex4/$f" /usr/local/bin/$f && echo "installed /usr/local/bin/$f"
done
cat > /etc/cron.d/nizam-deploy <<'EOF3'
*/5 * * * * root [ -x /usr/local/bin/nizam-deploy.sh ] && /usr/local/bin/nizam-deploy.sh
15 3 * * * root [ -x /usr/local/bin/ex4-oracle-backup.sh ] && /usr/local/bin/ex4-oracle-backup.sh
EOF3
chmod 644 /etc/cron.d/nizam-deploy; echo "cron: nizam-deploy every 5 min (dry-run until DEPLOY.json enabled) · oracle backup 03:15 nightly"

if [ -d "$NZ/nizam-data/.git" ]; then
  echo "== first sync now"; /usr/local/bin/nizam-sync.sh; tail -6 /var/log/nizam-sync.log | sed 's/^/  /'
  echo "STATE=READY"
else
  echo "STATE=WAITING_FOR_DEPLOY_KEY"
fi
echo "== $(date '+%F %T') nizam done"
