@echo off
setlocal EnableExtensions DisableDelayedExpansion
rem Download the latest noReita GitHub Release and update an existing installation.
rem Usage: update-from-github.bat [path\to\noreita]

set "REPOSITORY=sakots/noReita"
if "%~1"=="" (
  set "TARGET_DIRECTORY=%~dp0noreita"
) else (
  set "TARGET_DIRECTORY=%~f1"
)

if not exist "%TARGET_DIRECTORY%\index.php" (
  echo noReita installation was not found: "%TARGET_DIRECTORY%"
  echo Pass the directory containing index.php as the first argument.
  exit /b 1
)

set "WORK_DIRECTORY=%TEMP%\noreita-update-%RANDOM%%RANDOM%"
set "METADATA=%WORK_DIRECTORY%\release.txt"
set "ARCHIVE=%WORK_DIRECTORY%\noreita.zip"
set "EXTRACT_DIRECTORY=%WORK_DIRECTORY%\extracted"
mkdir "%WORK_DIRECTORY%" >nul 2>nul || exit /b 1

powershell -NoProfile -ExecutionPolicy Bypass -Command "$ErrorActionPreference='Stop'; $release=Invoke-RestMethod -Headers @{Accept='application/vnd.github+json';'User-Agent'='noReita-release-updater'} -Uri 'https://api.github.com/repos/%REPOSITORY%/releases/latest'; $asset=@($release.assets | Where-Object {$_.name -match '\.zip$'})[0]; if ($null -eq $asset -or [string]::IsNullOrEmpty($asset.digest)) { throw 'The latest release does not contain a ZIP asset with a digest.' }; Set-Content -NoNewline -Encoding ascii -Path '%METADATA%' -Value ($release.tag_name + '|' + $asset.browser_download_url + '|' + $asset.digest)"
if errorlevel 1 goto :failed

for /f "usebackq tokens=1-3 delims=|" %%A in ("%METADATA%") do (
  set "RELEASE_TAG=%%A"
  set "DOWNLOAD_URL=%%B"
  set "RELEASE_DIGEST=%%C"
)
if "%DOWNLOAD_URL%"=="" goto :failed

echo Downloading noReita %RELEASE_TAG%...
powershell -NoProfile -ExecutionPolicy Bypass -Command "$ErrorActionPreference='Stop'; Invoke-WebRequest -Uri '%DOWNLOAD_URL%' -OutFile '%ARCHIVE%'"
if errorlevel 1 goto :failed

powershell -NoProfile -ExecutionPolicy Bypass -Command "$ErrorActionPreference='Stop'; $expected='%RELEASE_DIGEST%'.Replace('sha256:',''); $actual=(Get-FileHash -Algorithm SHA256 -LiteralPath '%ARCHIVE%').Hash.ToLowerInvariant(); if ($actual -ne $expected.ToLowerInvariant()) { throw 'SHA-256 verification failed.' }; Expand-Archive -LiteralPath '%ARCHIVE%' -DestinationPath '%EXTRACT_DIRECTORY%' -Force; $index=Get-ChildItem -LiteralPath '%EXTRACT_DIRECTORY%' -Filter index.php -File -Recurse | Where-Object {$_.FullName -match '[\\/]noreita[\\/]index\.php$'} | Select-Object -First 1; if ($null -eq $index) { throw 'The release archive does not contain noreita/index.php.' }; Set-Content -NoNewline -Encoding ascii -Path '%WORK_DIRECTORY%\source.txt' -Value $index.DirectoryName"
if errorlevel 1 goto :failed

set /p "SOURCE_DIRECTORY=" < "%WORK_DIRECTORY%\source.txt"
robocopy "%SOURCE_DIRECTORY%" "%TARGET_DIRECTORY%" /E /COPY:DAT /DCOPY:DAT /R:2 /W:1 /NFL /NDL /NJH /NJS /XD img thumbnail temp session cache backup errorlog auditlog /XF config.local.php *.db *.db-*
if errorlevel 8 goto :failed

echo Updated "%TARGET_DIRECTORY%" to %RELEASE_TAG%. Local configuration and runtime data were preserved.
rd /s /q "%WORK_DIRECTORY%"
exit /b 0

:failed
echo Update failed. Existing files were not deleted.
if exist "%WORK_DIRECTORY%" rd /s /q "%WORK_DIRECTORY%"
exit /b 1
