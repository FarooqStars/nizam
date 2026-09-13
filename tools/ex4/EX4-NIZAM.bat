@echo off
REM EX4-NIZAM.bat - Nizam 2.0 Phase 3: make the EX4 the master copy of the Nizam record (clone repos, cron every 15 min).
REM Run on a PC on the home LAN. Asks for the EX4 root password twice (scp + ssh). Run it AGAIN after adding the deploy key on GitHub.
REM Output -> ex4\nizam.txt for Claude (contains only the PUBLIC key - safe).
setlocal
set "HOST=192.168.10.28"
set "SRC=%~dp0ex4-nizam.sh"
set "OUT=%~dp0nizam.txt"
set "SSHOPT=-o StrictHostKeyChecking=accept-new -o ConnectTimeout=15"
echo.
echo EX4 - Nizam master copy (Phase 3)
echo ----------------------------------
scp %SSHOPT% "%SRC%" root@%HOST%:/tmp/ex4-nizam.sh
if errorlevel 1 ( echo ERROR: upload failed. Wrong password or IP? Tell Claude. & pause & exit /b 1 )
ssh %SSHOPT% root@%HOST% "sed -i 's/\r$//' /tmp/ex4-nizam.sh; bash /tmp/ex4-nizam.sh" > "%OUT%" 2>&1
type "%OUT%"
echo.
echo ===== summary =====
type "%OUT%" | findstr /r /c:"git:" /c:"DEPLOY KEY" /c:"ssh-ed25519" /c:"nizam:" /c:"nizam-data:" /c:"NEED_DEPLOY_KEY" /c:"cron:" /c:"pushed" /c:"no change" /c:"STATE=" /c:"ERROR" /c:"WARN"
echo ===================
echo Saved: %OUT%
echo.
echo Done. Tell Claude: nizam ex4 ready  (and send the summary lines)
pause
