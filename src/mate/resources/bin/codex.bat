@echo off
setlocal

set "SCRIPT_DIR=%~dp0"
for %%I in ("%SCRIPT_DIR%..") do set "PROJECT_DIR=%%~fI"

where codex >nul 2>nul
if errorlevel 1 (
    echo Codex CLI is not installed or not available in PATH. 1>&2
    echo Install Codex and try again. 1>&2
    exit /b 1
)

pushd "%PROJECT_DIR%" >nul
codex -c "mcp_servers.symfony_ai_mate_local.command='./vendor/bin/mate'" -c "mcp_servers.symfony_ai_mate_local.args=['serve','--force-keep-alive']" %*
set "CODE=%ERRORLEVEL%"
popd >nul

exit /b %CODE%
