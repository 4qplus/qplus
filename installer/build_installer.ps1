$ErrorActionPreference = "Stop"
$installerDir = if ($PSScriptRoot) { $PSScriptRoot } else { Split-Path -Parent $MyInvocation.MyCommand.Path }
$sourceRoot = Split-Path -Parent $installerDir
$stageDir = Join-Path $installerDir "_stage"
$payloadZip = Join-Path $installerDir "payload.zip"
$outputExe = Join-Path $installerDir "qplus_setup.exe"
$launcherCs = Join-Path $installerDir "_launcher.cs"
$manifestFile = Join-Path $installerDir "_app.manifest"
$sedFile = Join-Path $installerDir "qplus_setup.sed"

if (Test-Path $stageDir) { Remove-Item $stageDir -Recurse -Force }
if (Test-Path $payloadZip) { Remove-Item $payloadZip -Force }
if (Test-Path $outputExe) { Remove-Item $outputExe -Force }
if (Test-Path $sedFile) { Remove-Item $sedFile -Force }

New-Item -ItemType Directory -Force -Path $stageDir | Out-Null

# Copy clean application files (exclude live data, secrets, temp, directory)
$rootFiles = @(
    "index.php",
    "app_ui.php",
    "paths.php",
    "download.php",
    "upload.php",
    "delete.php",
    "folder_access.php",
    "session_presence.php",
    "web.config"
)

foreach ($f in $rootFiles) {
    $src = Join-Path $sourceRoot $f
    if (-not (Test-Path $src)) { throw "필수 파일 누락: $src" }
    Copy-Item -Path $src -Destination (Join-Path $stageDir $f) -Force
}

Copy-Item -Path (Join-Path $sourceRoot "api") -Destination (Join-Path $stageDir "api") -Recurse -Force
Copy-Item -Path (Join-Path $sourceRoot "assets") -Destination (Join-Path $stageDir "assets") -Recurse -Force

# Copy PHP runtime excluding machine-specific php.ini and dev files
$phpDest = Join-Path $stageDir "php"
New-Item -ItemType Directory -Force -Path $phpDest | Out-Null
Get-ChildItem -Path (Join-Path $sourceRoot "php") -Exclude "php.ini", "dev" | ForEach-Object {
    Copy-Item -Path $_.FullName -Destination (Join-Path $phpDest $_.Name) -Recurse -Force
}

Write-Host "Creating payload.zip..."
Add-Type -AssemblyName System.IO.Compression.FileSystem
[System.IO.Compression.ZipFile]::CreateFromDirectory($stageDir, $payloadZip, [System.IO.Compression.CompressionLevel]::Optimal, $false)
Remove-Item $stageDir -Recurse -Force

# Create UAC Admin Manifest
$manifestXml = @'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<assembly xmlns="urn:schemas-microsoft-com:asm.v1" manifestVersion="1.0">
  <trustInfo xmlns="urn:schemas-microsoft-com:asm.v3">
    <security>
      <requestedPrivileges>
        <requestedExecutionLevel level="requireAdministrator" uiAccess="false"/>
      </requestedPrivileges>
    </security>
  </trustInfo>
</assembly>
'@
[System.IO.File]::WriteAllText($manifestFile, $manifestXml, (New-Object System.Text.UTF8Encoding $false))

# Create C# self-extracting launcher
$csCode = @'
using System;
using System.Diagnostics;
using System.IO;
using System.Reflection;

class QplusSetupLauncher
{
    static void ExtractResource(string resourceName, string destPath)
    {
        using (Stream s = Assembly.GetExecutingAssembly().GetManifestResourceStream(resourceName))
        using (FileStream fs = new FileStream(destPath, FileMode.Create, FileAccess.Write))
        {
            s.CopyTo(fs);
        }
    }

    [STAThread]
    static int Main(string[] args)
    {
        string tempWorkDir = Path.Combine(Path.GetTempPath(), "qplus_setup_" + Guid.NewGuid().ToString("N").Substring(0, 8));
        Directory.CreateDirectory(tempWorkDir);
        try
        {
            string zipPath = Path.Combine(tempWorkDir, "payload.zip");
            string ps1Path = Path.Combine(tempWorkDir, "qplus_installer.ps1");
            ExtractResource("payload.zip", zipPath);
            ExtractResource("qplus_installer.ps1", ps1Path);

            string extraArgs = "";
            foreach (string a in args)
            {
                if (a.StartsWith("-"))
                    extraArgs += " " + a;
                else
                    extraArgs += " \"" + a.Replace("\"", "\\\"") + "\"";
            }
            ProcessStartInfo psi = new ProcessStartInfo();
            psi.FileName = "powershell.exe";
            psi.Arguments = "-NoProfile -ExecutionPolicy Bypass -STA -WindowStyle Hidden -File \"" + ps1Path + "\" -PayloadZip \"" + zipPath + "\"" + extraArgs;
            psi.UseShellExecute = false;
            psi.CreateNoWindow = true;

            using (Process proc = Process.Start(psi))
            {
                proc.WaitForExit();
                return proc.ExitCode;
            }
        }
        finally
        {
            try { Directory.Delete(tempWorkDir, true); } catch { }
        }
    }
}
'@
[System.IO.File]::WriteAllText($launcherCs, $csCode, (New-Object System.Text.UTF8Encoding $false))

$csc = Get-ChildItem "C:\Windows\Microsoft.NET\Framework64" -Filter "csc.exe" -Recurse | Select-Object -First 1 -ExpandProperty FullName
if (-not $csc) { throw "csc.exe를 찾을 수 없습니다." }

Write-Host "Compiling $outputExe..."
& $csc /nologo /target:winexe /out:"$outputExe" /win32manifest:"$manifestFile" /resource:"$payloadZip,payload.zip" /resource:"$installerDir\qplus_installer.ps1,qplus_installer.ps1" "$launcherCs"
if ($LASTEXITCODE -ne 0 -or -not (Test-Path $outputExe)) {
    throw "csc.exe 컴파일 실패 (ExitCode=$LASTEXITCODE)"
}

Remove-Item $launcherCs, $manifestFile -ErrorAction SilentlyContinue
$exeInfo = Get-Item $outputExe
Write-Host ("Build complete: {0} ({1:N2} MB)" -f $exeInfo.FullName, ($exeInfo.Length / 1MB))
