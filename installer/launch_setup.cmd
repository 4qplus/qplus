@echo off
cd /d "%~dp0"
powershell.exe -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File "%~dp0qplus_installer.ps1" -PayloadZip "%~dp0payload.zip"

