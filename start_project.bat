@echo off
echo Starting BCxMCE project...

echo Waiting for services to be ready on port 10009...
:check_port
  echo Checking if localhost:10009 is available...
  powershell -Command "try { Test-NetConnection -ComputerName localhost -Port 10009 -InformationLevel Quiet } catch { exit 1 }" >nul 2>&1
  if %errorlevel% neq 0 (
    timeout /t 2 /nobreak >nul
    goto check_port
  )

echo Services are ready! Opening BCxMCE project in browser...
start http://bcxmce.local/
echo Project opened in browser. The URL is: http://bcxmce.local/