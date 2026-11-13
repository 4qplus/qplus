param(
    [string]$PayloadZip = $(if ($PSScriptRoot) { Join-Path $PSScriptRoot "payload.zip" } else { Join-Path (Get-Location).Path "payload.zip" }),
    [switch]$Silent,
    [string]$InstallDir = $(if (Test-Path "D:\") { "D:\qplus" } else { "C:\qplus" }),
    [AllowEmptyString()][string]$StorageDir = "",
    [AllowEmptyString()][string]$AdminPw = "",
    [AllowEmptyString()][string]$GuestPw = "",
    [int]$Port = 80,
    [ValidateSet("Local", "External")][string]$NetworkScope = "Local",
    [ValidateSet("PortForwardDDNS", "Cloudflare", "PrivateVPN")][string]$ExternalMode = "PortForwardDDNS",
    [AllowEmptyString()][string]$DomainName = "",
    [AllowEmptyString()][string]$DdnsUpdateUrl = "",
    [AllowEmptyString()][string]$CloudflareToken = "",
    [switch]$SkipIIS,
    [ValidateSet("en", "ko")][string]$Language = "en"
)

$ErrorActionPreference = "Stop"

# Ensure running as Administrator
$currentPrincipal = New-Object Security.Principal.WindowsPrincipal([Security.Principal.WindowsIdentity]::GetCurrent())
if (-not $currentPrincipal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    if ($Silent) {
        Write-Error "Administrator privileges are required."
        exit 1
    }
    $argList = @("-NoProfile", "-ExecutionPolicy", "Bypass", "-STA", "-WindowStyle", "Hidden", "-File", "`"$PSCommandPath`"")
    if ($PayloadZip) { $argList += @("-PayloadZip", "`"$PayloadZip`"") }
    Start-Process powershell.exe -Verb RunAs -ArgumentList $argList
    exit 0
}

function ConvertTo-SecretString {
    param([AllowEmptyString()][string]$PlainValue)
    $sec = New-Object System.Security.SecureString
    if (-not [string]::IsNullOrEmpty($PlainValue)) {
        foreach ($ch in $PlainValue.ToCharArray()) {
            $sec.AppendChar($ch)
        }
    }
    $sec.MakeReadOnly()
    return $sec
}

function Read-SecretString {
    param([SecureString]$Secret)
    if ($null -eq $Secret -or $Secret.Length -eq 0) { return "" }
    $bstr = [System.Runtime.InteropServices.Marshal]::SecureStringToBSTR($Secret)
    try {
        return [System.Runtime.InteropServices.Marshal]::PtrToStringUni($bstr)
    } finally {
        if ($bstr -ne [IntPtr]::Zero) {
            [System.Runtime.InteropServices.Marshal]::ZeroFreeBSTR($bstr)
        }
    }
}

function Set-QplusFirewallRule {
    param(
        [string]$RuleName,
        [int]$Port,
        [string]$RemoteAddress = "Any"
    )
    Get-NetFirewallRule -DisplayName "qplus-*" -ErrorAction SilentlyContinue | Remove-NetFirewallRule -ErrorAction SilentlyContinue
    New-NetFirewallRule -DisplayName $RuleName -Direction Inbound -Protocol TCP -LocalPort $Port -RemoteAddress $RemoteAddress -Action Allow -Profile Any | Out-Null
}

function Get-LocalLanIPv4 {
    try {
        $ip = Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue |
            Where-Object { $_.IPAddress -notlike "127.*" -and $_.IPAddress -notlike "169.254.*" -and $_.PrefixOrigin -ne "WellKnown" } |
            Select-Object -First 1 -ExpandProperty IPAddress
        return $ip
    } catch { return $null }
}

function Invoke-QplusInstall {
    param(
        [string]$TargetDir,
        [string]$DataDir,
        [SecureString]$AdminSecret,
        [SecureString]$GuestSecret,
        [int]$WebPort,
        [string]$Scope = "Local",
        [string]$ExtMode = "PortForwardDDNS",
        [AllowEmptyString()][string]$HostDomain = "",
        [AllowEmptyString()][string]$DdnsUrl = "",
        [AllowEmptyString()][string]$CfToken = "",
        [bool]$ConfigureIIS = $true,
        [string]$Lang = "en",
        [scriptblock]$LogCallback = $null
    )

    function Write-StepLog($msg) {
        if ($LogCallback) { & $LogCallback $msg }
        else { Write-Host $msg }
    }

    $TargetDir = $TargetDir.Trim().TrimEnd('\', '/')
    $DataDir = $DataDir.Trim().TrimEnd('\', '/')
    if (-not $TargetDir) { throw "Installation directory is required." }
    if (-not $DataDir) { $DataDir = Join-Path $TargetDir "directory" }
    if ($null -eq $AdminSecret -or $AdminSecret.Length -eq 0) { throw "Initial admin password is required." }
    if ($null -eq $GuestSecret -or $GuestSecret.Length -eq 0) { throw "Initial guest password is required." }
    if (-not (Test-Path $PayloadZip)) { throw "Installation payload package (payload.zip) not found: $PayloadZip" }

    Write-StepLog "[1/6] Preparing installation directory: $TargetDir"
    New-Item -ItemType Directory -Force -Path $TargetDir | Out-Null
    New-Item -ItemType Directory -Force -Path $DataDir | Out-Null
    New-Item -ItemType Directory -Force -Path (Join-Path $TargetDir "temp") | Out-Null
    New-Item -ItemType Directory -Force -Path (Join-Path $TargetDir "qplus") | Out-Null

    Write-StepLog "[2/6] Extracting qplus core and PHP runtime..."
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $zip = [System.IO.Compression.ZipFile]::OpenRead($PayloadZip)
    try {
        foreach ($entry in $zip.Entries) {
            $destPath = Join-Path $TargetDir $entry.FullName
            if ($entry.FullName.EndsWith("/") -or $entry.FullName.EndsWith("\")) {
                New-Item -ItemType Directory -Force -Path $destPath | Out-Null
            } else {
                $parentDir = Split-Path -Parent $destPath
                if (-not (Test-Path $parentDir)) { New-Item -ItemType Directory -Force -Path $parentDir | Out-Null }
                [System.IO.Compression.ZipFileExtensions]::ExtractToFile($entry, $destPath, $true)
            }
        }
    } finally {
        $zip.Dispose()
    }

    Write-StepLog "[3/6] Generating PHP configuration (php.ini) and security configuration (config.php)..."
    $tempNorm = (Join-Path $TargetDir "temp").Replace('/', '\')
    $errLogPath = Join-Path $tempNorm "php_errors.log"
    $phpIniContent = @"
extension_dir = "ext"
extension=curl
extension=mbstring
extension=openssl
extension=zip

max_file_uploads = 500
upload_max_filesize = 500M
post_max_size = 500M
max_execution_time = 300
max_input_time = 300
memory_limit = 512M

upload_tmp_dir = "$tempNorm"
session.save_path = "$tempNorm"
session.cookie_httponly = 1
session.use_only_cookies = 1
session.cookie_samesite = "Lax"

date.timezone = Asia/Seoul
display_errors = Off
display_startup_errors = Off
log_errors = On
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT
error_log = "$errLogPath"

; --- IIS FastCGI 필수 추가 설정 ---
cgi.force_redirect = 0
cgi.fix_pathinfo = 1
fastcgi.impersonate = 1
"@
    $phpIniPath = Join-Path $TargetDir "php\php.ini"
    [System.IO.File]::WriteAllText($phpIniPath, $phpIniContent, (New-Object System.Text.UTF8Encoding $false))

    # Hash passwords using bundled php.exe
    $phpExe = Join-Path $TargetDir "php\php.exe"
    $storageForward = $DataDir.Replace('\', '/')
    $hashScript = Join-Path $tempNorm "init_cfg.php"
    $phpCode = @'
<?php
$adminPw = getenv('QP_ADMIN_PW');
$guestPw = getenv('QP_GUEST_PW');
$storageDir = getenv('QP_STORAGE_DIR');
$lang = getenv('QP_LANG') ?: 'en';
$cfg = array(
    'admin_pw' => password_hash($adminPw, PASSWORD_DEFAULT),
    'guest_pw' => password_hash($guestPw, PASSWORD_DEFAULT),
    'language' => $lang,
    'folder_pws' => array(),
    'storage_dir' => $storageDir,
);
file_put_contents(getenv('QP_CFG_PATH'), "<?php return " . var_export($cfg, true) . "; ?>");
'@
    [System.IO.File]::WriteAllText($hashScript, $phpCode, (New-Object System.Text.UTF8Encoding $false))
    $env:QP_ADMIN_PW = Read-SecretString $AdminSecret
    $env:QP_GUEST_PW = Read-SecretString $GuestSecret
    $env:QP_STORAGE_DIR = $storageForward
    $env:QP_LANG = $Lang
    $env:QP_CFG_PATH = Join-Path $TargetDir "config.php"
    try {
        & $phpExe $hashScript
        if ($LASTEXITCODE -ne 0) { throw "비밀번호 암호화 초기화에 실패했습니다. (Visual C++ x64 재배포 패키지 설치 여부를 확인하세요.)" }
    } finally {
        Remove-Item $hashScript -ErrorAction SilentlyContinue
        Remove-Item Env:QP_ADMIN_PW, Env:QP_GUEST_PW, Env:QP_STORAGE_DIR, Env:QP_LANG, Env:QP_CFG_PATH -ErrorAction SilentlyContinue
    }

    # Initialize empty data files
    $tipsPath = Join-Path $TargetDir "tips_data.php"
    $reqPath = Join-Path $TargetDir "requests_data.php"
    if (-not (Test-Path $tipsPath)) { [System.IO.File]::WriteAllText($tipsPath, "<?php return array(); ?>", (New-Object System.Text.UTF8Encoding $false)) }
    if (-not (Test-Path $reqPath)) { [System.IO.File]::WriteAllText($reqPath, "<?php return array(); ?>", (New-Object System.Text.UTF8Encoding $false)) }

    Write-StepLog "[4/6] Setting directory access permissions (BUILTIN\IIS_IUSRS)..."
    & icacls $DataDir /grant "BUILTIN\IIS_IUSRS:(OI)(CI)M" /T /Q | Out-Null
    & icacls (Join-Path $TargetDir "temp") /grant "BUILTIN\IIS_IUSRS:(OI)(CI)F" /T /Q | Out-Null
    & icacls (Join-Path $TargetDir "qplus") /grant "BUILTIN\IIS_IUSRS:(OI)(CI)M" /T /Q | Out-Null
    & icacls (Join-Path $TargetDir "config.php") /grant "BUILTIN\IIS_IUSRS:M" /Q | Out-Null
    & icacls $tipsPath /grant "BUILTIN\IIS_IUSRS:M" /Q | Out-Null
    & icacls $reqPath /grant "BUILTIN\IIS_IUSRS:M" /Q | Out-Null

    if ($ConfigureIIS) {
        Write-StepLog "[5/6] Configuring IIS Web Server and FastCGI (PHP)..."
        $appcmd = Join-Path $env:SystemRoot "System32\inetsrv\appcmd.exe"
        if (-not (Test-Path $appcmd)) {
            Write-StepLog "  - Enabling Windows IIS and CGI features (this may take a few minutes)..."
            $features = @("IIS-WebServerRole", "IIS-WebServer", "IIS-CommonHttpFeatures", "IIS-DefaultDocument", "IIS-StaticContent", "IIS-Security", "IIS-RequestFiltering", "IIS-ApplicationDevelopment", "IIS-CGI")
            foreach ($f in $features) {
                dism.exe /Online /Enable-Feature /FeatureName:$f /All /NoRestart /Quiet | Out-Null
            }
        }
        if (-not (Test-Path $appcmd)) { throw "Could not activate IIS (appcmd.exe)." }

        $phpCgi = Join-Path $TargetDir "php\php-cgi.exe"
        # Configure FastCGI application
        & $appcmd set config -section:system.webServer/fastCgi /-"[fullPath='$phpCgi']" 2>$null | Out-Null
        & $appcmd set config -section:system.webServer/fastCgi /+"[fullPath='$phpCgi',activityTimeout='3600',requestTimeout='3600']" | Out-Null

        # Configure Default Web Site physical path and port binding
        & $appcmd set vdir "Default Web Site/" /physicalPath:"$TargetDir" | Out-Null
        & $appcmd set site "Default Web Site" /bindings:"http/*:${WebPort}:" | Out-Null

        # Configure PHP FastCGI Handler
        & $appcmd set config "Default Web Site" -section:system.webServer/handlers /-"[name='PHP_via_FastCGI']" 2>$null | Out-Null
        & $appcmd set config "Default Web Site" -section:system.webServer/handlers /+"[name='PHP_via_FastCGI',path='*.php',verb='*',modules='FastCgiModule',scriptProcessor='$phpCgi',resourceType='File',requireAccess='Script']" | Out-Null

        & $appcmd start site "Default Web Site" 2>$null | Out-Null
    } else {
        Write-StepLog "[5/6] Skipped IIS auto-configuration."
    }

    $accessInfo = "http://localhost:$WebPort/"
    if (-not $ConfigureIIS) {
        Write-StepLog "[6/6] Skipped network/firewall configuration due to SkipIIS option."
    } elseif ($Scope -eq "Local") {
        Write-StepLog "[6/6] Configuring local network (LAN) firewall rule..."
        try {
            Set-QplusFirewallRule -RuleName "qplus-Local-HTTP-$WebPort" -Port $WebPort -RemoteAddress "LocalSubnet"
            Write-StepLog "  - Registered Windows Firewall allow rule for LocalSubnet TCP $WebPort."
        } catch {
            Write-StepLog "  ! 방화벽 규칙 자동 등록 실패 (수동 확인 필요): $($_.Exception.Message)"
        }
        $lanIp = Get-LocalLanIPv4
        if ($lanIp) {
            $accessInfo = "http://localhost:$WebPort/  (내부망: http://${lanIp}:$WebPort/)"
        }
    } else {
        switch ($ExtMode) {
            "PortForwardDDNS" {
                Write-StepLog "[6/6] Configuring external network (Port Forwarding + DDNS)..."
                try {
                    Set-QplusFirewallRule -RuleName "qplus-External-HTTP-$WebPort" -Port $WebPort -RemoteAddress "Any"
                    Write-StepLog "  - Registered Windows Firewall allow rule for TCP $WebPort (Any)."
                } catch {
                    Write-StepLog "  ! 방화벽 규칙 자동 등록 실패: $($_.Exception.Message)"
                }
                if ($DdnsUrl.Trim() -ne "") {
                    try {
                        $ddnsScript = Join-Path $TargetDir "ddns_update.ps1"
                        $safeUrl = $DdnsUrl.Trim().Replace("'", "''")
                        $scriptBody = "try { Invoke-WebRequest -Uri '$safeUrl' -UseBasicParsing -TimeoutSec 15 | Out-Null } catch {}"
                        [System.IO.File]::WriteAllText($ddnsScript, $scriptBody, (New-Object System.Text.UTF8Encoding $true))
                        $action = New-ScheduledTaskAction -Execute "powershell.exe" -Argument "-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$ddnsScript`""
                        $trigger = New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval (New-TimeSpan -Minutes 5) -RepetitionDuration (New-TimeSpan -Days 3650)
                        Register-ScheduledTask -TaskName "qplus-DDNS-Updater" -Action $action -Trigger $trigger -User "SYSTEM" -RunLevel Highest -Force | Out-Null
                        & powershell.exe -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File $ddnsScript
                        Write-StepLog "  - DDNS 자동 갱신 작업(5분 주기: qplus-DDNS-Updater)을 등록하고 1회 갱신했습니다."
                    } catch {
                        Write-StepLog "  ! DDNS 자동 갱신 작업 등록 실패: $($_.Exception.Message)"
                    }
                }
                $lanIp = Get-LocalLanIPv4
                if ($HostDomain.Trim() -ne "") {
                    $portSuffix = if ($WebPort -eq 80) { "" } else { ":$WebPort" }
                    $accessInfo = "http://$($HostDomain.Trim())$portSuffix/  (공유기에서 외부 $WebPort -> ${lanIp}:$WebPort 포트포워딩 필요)"
                } else {
                    $accessInfo = "http://localhost:$WebPort/  (공유기에서 ${lanIp}:$WebPort 포트포워딩 후 공인IP/DDNS로 접속)"
                }
            }
            "Cloudflare" {
                Write-StepLog "[6/6] Configuring external network (Cloudflare Tunnel)..."
                if ($CfToken.Trim() -ne "") {
                    $cfExe = "C:\Program Files (x86)\cloudflared\cloudflared.exe"
                    if (-not (Test-Path $cfExe)) {
                        $cfExe64 = "C:\Program Files\cloudflared\cloudflared.exe"
                        if (Test-Path $cfExe64) { $cfExe = $cfExe64 }
                    }
                    if (Test-Path $cfExe) {
                        & $cfExe service install $CfToken.Trim() | Out-Null
                        Write-StepLog "  - cloudflared Windows 서비스를 등록하고 시작했습니다."
                    } else {
                        Write-StepLog "  ! cloudflared.exe가 설치되어 있지 않아 터널 자동 등록을 건너뛰었습니다."
                    }
                } else {
                    Write-StepLog "  ! Cloudflare Tunnel 토큰이 비어 있어 터널 서비스 등록을 건너뛰었습니다."
                }
                $accessInfo = "Cloudflare Tunnel 연결 도메인 (로컬 확인: http://localhost:$WebPort/)"
            }
            "PrivateVPN" {
                Write-StepLog "[6/6] Configuring external network (Private VPN)..."
                try {
                    Set-QplusFirewallRule -RuleName "qplus-VPN-HTTP-$WebPort" -Port $WebPort -RemoteAddress "Any"
                    Write-StepLog "  - Windows 방화벽에 VPN/사설망 접속용 TCP $WebPort 허용 규칙을 등록했습니다."
                } catch {
                    Write-StepLog "  ! 방화벽 규칙 자동 등록 실패: $($_.Exception.Message)"
                }
                $vpnIp = $null
                $tsExe = Join-Path $env:ProgramFiles "Tailscale\tailscale.exe"
                if (Test-Path $tsExe) {
                    try { $vpnIp = (& $tsExe ip -4 2>$null | Select-Object -First 1).Trim() } catch {}
                }
                if ($vpnIp) {
                    Write-StepLog "  - Tailscale IPv4 감지됨: $vpnIp"
                    $accessInfo = "http://${vpnIp}:$WebPort/  (Tailscale 연결 기기 전용)"
                } else {
                    Write-StepLog "  - 사설 VPN(Tailscale/WireGuard 등) 실행 후 해당 VPN IP:$WebPort 로 접속하세요."
                    $accessInfo = "http://localhost:$WebPort/  (VPN 연결 후 VPN IP:$WebPort 로 접속)"
                }
            }
        }
    }

    Write-StepLog "Installation completed successfully! Access URL: $accessInfo"
    return $accessInfo
}

if ($Silent) {
    $resolvedDataDir = if ($StorageDir) { $StorageDir } else { Join-Path $InstallDir "directory" }
    $resolvedScope = if ($CloudflareToken.Trim() -ne "" -and $NetworkScope -eq "Local") { "External" } else { $NetworkScope }
    $resolvedExtMode = if ($CloudflareToken.Trim() -ne "" -and $NetworkScope -eq "Local") { "Cloudflare" } else { $ExternalMode }
    Invoke-QplusInstall `
        -TargetDir $InstallDir `
        -DataDir $resolvedDataDir `
        -AdminSecret (ConvertTo-SecretString $AdminPw) `
        -GuestSecret (ConvertTo-SecretString $GuestPw) `
        -WebPort $Port `
        -Scope $resolvedScope `
        -ExtMode $resolvedExtMode `
        -HostDomain $DomainName `
        -DdnsUrl $DdnsUpdateUrl `
        -CfToken $CloudflareToken `
        -ConfigureIIS (-not $SkipIIS) `
        -Lang $Language | Out-Null
    exit 0
}

# Build GUI Wizard
Add-Type -AssemblyName System.Windows.Forms
Add-Type -AssemblyName System.Drawing
[System.Windows.Forms.Application]::EnableVisualStyles()

$form = New-Object System.Windows.Forms.Form
$form.Text = "qplus Setup Wizard"
$form.Size = New-Object System.Drawing.Size(560, 755)
$form.StartPosition = "CenterScreen"
$form.FormBorderStyle = "FixedDialog"
$form.MaximizeBox = $false
$form.BackColor = [System.Drawing.Color]::FromArgb(245, 245, 245)
$form.Font = New-Object System.Drawing.Font("Segoe UI", 9.5)

# Header Panel
$headerPanel = New-Object System.Windows.Forms.Panel
$headerPanel.Dock = "Top"
$headerPanel.Height = 72
$headerPanel.BackColor = [System.Drawing.Color]::White
$form.Controls.Add($headerPanel)

$titleLbl = New-Object System.Windows.Forms.Label
$titleLbl.Text = "qplus Setup Wizard"
$titleLbl.Font = New-Object System.Drawing.Font("Segoe UI", 13, [System.Drawing.FontStyle]::Bold)
$titleLbl.ForeColor = [System.Drawing.Color]::FromArgb(20, 20, 20)
$titleLbl.Location = New-Object System.Drawing.Point(22, 12)
$titleLbl.AutoSize = $true
$headerPanel.Controls.Add($titleLbl)

$subLbl = New-Object System.Windows.Forms.Label
$subLbl.Text = "Configure web server (IIS + PHP) and storage for your private cloud."
$subLbl.ForeColor = [System.Drawing.Color]::FromArgb(100, 100, 100)
$subLbl.Location = New-Object System.Drawing.Point(23, 41)
$subLbl.AutoSize = $true
$headerPanel.Controls.Add($subLbl)

# Language Selector (Header Right)
$pnlLang = New-Object System.Windows.Forms.Panel
$pnlLang.Location = New-Object System.Drawing.Point(365, 14)
$pnlLang.Size = New-Object System.Drawing.Size(165, 26)
$headerPanel.Controls.Add($pnlLang)

$rbLangEn = New-Object System.Windows.Forms.RadioButton
$rbLangEn.Text = "English"
$rbLangEn.Font = New-Object System.Drawing.Font("Segoe UI", 9, [System.Drawing.FontStyle]::Bold)
$rbLangEn.Location = New-Object System.Drawing.Point(0, 2)
$rbLangEn.AutoSize = $true
$rbLangEn.Checked = $true
$pnlLang.Controls.Add($rbLangEn)

$rbLangKo = New-Object System.Windows.Forms.RadioButton
$rbLangKo.Text = "한국어"
$rbLangKo.Font = New-Object System.Drawing.Font("Malgun Gothic", 9, [System.Drawing.FontStyle]::Bold)
$rbLangKo.Location = New-Object System.Drawing.Point(82, 2)
$rbLangKo.AutoSize = $true
$pnlLang.Controls.Add($rbLangKo)

# Main Form Body
$bodyPanel = New-Object System.Windows.Forms.Panel
$bodyPanel.Location = New-Object System.Drawing.Point(20, 84)
$bodyPanel.Size = New-Object System.Drawing.Size(504, 575)
$bodyPanel.BackColor = [System.Drawing.Color]::White
$form.Controls.Add($bodyPanel)

function Create-FieldLabel($parent, $text, $y) {
    $lbl = New-Object System.Windows.Forms.Label
    $lbl.Text = $text
    $lbl.Font = New-Object System.Drawing.Font("Segoe UI", 9, [System.Drawing.FontStyle]::Bold)
    $lbl.ForeColor = [System.Drawing.Color]::FromArgb(40, 40, 40)
    $lbl.Location = New-Object System.Drawing.Point(20, $y)
    $lbl.AutoSize = $true
    $parent.Controls.Add($lbl)
    return $lbl
}

# 1. Install Directory
$defaultDrive = if (Test-Path "D:\") { "D:" } else { "C:" }
$defaultInstall = if ($InstallDir) { $InstallDir } else { "$defaultDrive\qplus" }
$defaultStorage = if ($StorageDir) { $StorageDir } else { "$defaultInstall\directory" }

$lblStep1 = Create-FieldLabel $bodyPanel "1. Installation Path (Web Root)" 14
$txtInstall = New-Object System.Windows.Forms.TextBox
$txtInstall.Text = $defaultInstall
$txtInstall.Location = New-Object System.Drawing.Point(20, 36)
$txtInstall.Size = New-Object System.Drawing.Size(375, 26)
$bodyPanel.Controls.Add($txtInstall)

$btnBrowseInstall = New-Object System.Windows.Forms.Button
$btnBrowseInstall.Text = "Browse..."
$btnBrowseInstall.Location = New-Object System.Drawing.Point(402, 34)
$btnBrowseInstall.Size = New-Object System.Drawing.Size(82, 27)
$bodyPanel.Controls.Add($btnBrowseInstall)

# 2. Storage Directory
$lblStep2 = Create-FieldLabel $bodyPanel "2. Shared Storage Path (directory)" 72
$txtStorage = New-Object System.Windows.Forms.TextBox
$txtStorage.Text = $defaultStorage
$txtStorage.Location = New-Object System.Drawing.Point(20, 94)
$txtStorage.Size = New-Object System.Drawing.Size(375, 26)
$bodyPanel.Controls.Add($txtStorage)

$btnBrowseStorage = New-Object System.Windows.Forms.Button
$btnBrowseStorage.Text = "Browse..."
$btnBrowseStorage.Location = New-Object System.Drawing.Point(402, 92)
$btnBrowseStorage.Size = New-Object System.Drawing.Size(82, 27)
$bodyPanel.Controls.Add($btnBrowseStorage)

$script:customStorageEdited = $false
$txtStorage.Add_TextChanged({ $script:customStorageEdited = $true })
$txtInstall.Add_TextChanged({
    if (-not $script:customStorageEdited -and $txtInstall.Text.Trim() -ne "") {
        $txtStorage.Text = Join-Path $txtInstall.Text.Trim() "directory"
        $script:customStorageEdited = $false
    }
})

$btnBrowseInstall.Add_Click({
    $fbd = New-Object System.Windows.Forms.FolderBrowserDialog
    $fbd.Description = if ($rbLangKo.Checked) { "qplus 프로그램을 설치할 폴더를 선택하세요." } else { "Select the folder to install qplus." }
    if ($txtInstall.Text -and (Test-Path $txtInstall.Text)) { $fbd.SelectedPath = $txtInstall.Text }
    if ($fbd.ShowDialog() -eq "OK") { $txtInstall.Text = $fbd.SelectedPath }
})
$btnBrowseStorage.Add_Click({
    $fbd = New-Object System.Windows.Forms.FolderBrowserDialog
    $fbd.Description = if ($rbLangKo.Checked) { "공유 파일을 저장할 디렉터리 경로를 선택하세요." } else { "Select the directory path to store shared files." }
    if ($txtStorage.Text -and (Test-Path $txtStorage.Text)) { $fbd.SelectedPath = $txtStorage.Text }
    if ($fbd.ShowDialog() -eq "OK") {
        $script:customStorageEdited = $true
        $txtStorage.Text = $fbd.SelectedPath
    }
})

# 3. Initial Passwords
$lblStep3 = Create-FieldLabel $bodyPanel "3. Admin Password" 130
$txtAdminPw = New-Object System.Windows.Forms.TextBox
$txtAdminPw.UseSystemPasswordChar = $true
$txtAdminPw.Location = New-Object System.Drawing.Point(20, 152)
$txtAdminPw.Size = New-Object System.Drawing.Size(222, 26)
$bodyPanel.Controls.Add($txtAdminPw)

$lblGuest = New-Object System.Windows.Forms.Label
$lblGuest.Text = "4. Guest Password"
$lblGuest.Font = New-Object System.Drawing.Font("Segoe UI", 9, [System.Drawing.FontStyle]::Bold)
$lblGuest.ForeColor = [System.Drawing.Color]::FromArgb(40, 40, 40)
$lblGuest.Location = New-Object System.Drawing.Point(262, 130)
$lblGuest.AutoSize = $true
$bodyPanel.Controls.Add($lblGuest)

$txtGuestPw = New-Object System.Windows.Forms.TextBox
$txtGuestPw.UseSystemPasswordChar = $true
$txtGuestPw.Location = New-Object System.Drawing.Point(262, 152)
$txtGuestPw.Size = New-Object System.Drawing.Size(222, 26)
$bodyPanel.Controls.Add($txtGuestPw)

# 5. IIS Port & Option
$lblStep5 = Create-FieldLabel $bodyPanel "5. Web Port (Default: 80)" 190
$numPort = New-Object System.Windows.Forms.NumericUpDown
$numPort.Minimum = 1; $numPort.Maximum = 65535; $numPort.Value = 80
$numPort.Location = New-Object System.Drawing.Point(20, 212)
$numPort.Size = New-Object System.Drawing.Size(100, 26)
$bodyPanel.Controls.Add($numPort)

$chkIIS = New-Object System.Windows.Forms.CheckBox
$chkIIS.Text = "Auto-configure IIS web server & FastCGI"
$chkIIS.Checked = $true
$chkIIS.Location = New-Object System.Drawing.Point(140, 214)
$chkIIS.AutoSize = $true
$bodyPanel.Controls.Add($chkIIS)

# 6. Network Topology Selection
$lblStep6 = Create-FieldLabel $bodyPanel "6. Network Topology" 248

$grpScope = New-Object System.Windows.Forms.Panel
$grpScope.Location = New-Object System.Drawing.Point(20, 270)
$grpScope.Size = New-Object System.Drawing.Size(464, 28)
$bodyPanel.Controls.Add($grpScope)

$rbScopeLocal = New-Object System.Windows.Forms.RadioButton
$rbScopeLocal.Text = "Local network only (LAN/Intranet)"
$rbScopeLocal.Location = New-Object System.Drawing.Point(0, 3)
$rbScopeLocal.AutoSize = $true
$rbScopeLocal.Checked = $true
$grpScope.Controls.Add($rbScopeLocal)

$rbScopeExternal = New-Object System.Windows.Forms.RadioButton
$rbScopeExternal.Text = "External network (Internet access)"
$rbScopeExternal.Location = New-Object System.Drawing.Point(235, 3)
$rbScopeExternal.AutoSize = $true
$grpScope.Controls.Add($rbScopeExternal)

$grpExtMode = New-Object System.Windows.Forms.Panel
$grpExtMode.Location = New-Object System.Drawing.Point(20, 300)
$grpExtMode.Size = New-Object System.Drawing.Size(464, 28)
$grpExtMode.Enabled = $false
$bodyPanel.Controls.Add($grpExtMode)

$rbExtPfd = New-Object System.Windows.Forms.RadioButton
$rbExtPfd.Text = "Port Forwarding + DDNS"
$rbExtPfd.Location = New-Object System.Drawing.Point(12, 3)
$rbExtPfd.AutoSize = $true
$rbExtPfd.Checked = $true
$grpExtMode.Controls.Add($rbExtPfd)

$rbExtCf = New-Object System.Windows.Forms.RadioButton
$rbExtCf.Text = "Cloudflare Tunnel"
$rbExtCf.Location = New-Object System.Drawing.Point(185, 3)
$rbExtCf.AutoSize = $true
$grpExtMode.Controls.Add($rbExtCf)

$rbExtVpn = New-Object System.Windows.Forms.RadioButton
$rbExtVpn.Text = "Private VPN (Tailscale)"
$rbExtVpn.Location = New-Object System.Drawing.Point(325, 3)
$rbExtVpn.AutoSize = $true
$grpExtMode.Controls.Add($rbExtVpn)

# Dynamic Detail Panel
$pnlDetail = New-Object System.Windows.Forms.Panel
$pnlDetail.Location = New-Object System.Drawing.Point(20, 332)
$pnlDetail.Size = New-Object System.Drawing.Size(464, 102)
$pnlDetail.BackColor = [System.Drawing.Color]::FromArgb(248, 250, 252)
$pnlDetail.BorderStyle = "FixedSingle"
$bodyPanel.Controls.Add($pnlDetail)

$lblModeInfo = New-Object System.Windows.Forms.Label
$lblModeInfo.Location = New-Object System.Drawing.Point(12, 10)
$lblModeInfo.Size = New-Object System.Drawing.Size(438, 80)
$lblModeInfo.ForeColor = [System.Drawing.Color]::FromArgb(70, 70, 70)
$pnlDetail.Controls.Add($lblModeInfo)

$lblDomain = New-Object System.Windows.Forms.Label
$lblDomain.Text = "Domain / DDNS (Optional, e.g. storage.duckdns.org)"
$lblDomain.Location = New-Object System.Drawing.Point(12, 8)
$lblDomain.AutoSize = $true
$pnlDetail.Controls.Add($lblDomain)

$txtDomain = New-Object System.Windows.Forms.TextBox
$txtDomain.Location = New-Object System.Drawing.Point(12, 26)
$txtDomain.Size = New-Object System.Drawing.Size(436, 23)
$pnlDetail.Controls.Add($txtDomain)

$lblDdnsUrl = New-Object System.Windows.Forms.Label
$lblDdnsUrl.Text = "DDNS Auto-Update URL (Optional, 5-min scheduled task)"
$lblDdnsUrl.Location = New-Object System.Drawing.Point(12, 53)
$lblDdnsUrl.AutoSize = $true
$pnlDetail.Controls.Add($lblDdnsUrl)

$txtDdnsUrl = New-Object System.Windows.Forms.TextBox
$txtDdnsUrl.Location = New-Object System.Drawing.Point(12, 71)
$txtDdnsUrl.Size = New-Object System.Drawing.Size(436, 23)
$pnlDetail.Controls.Add($txtDdnsUrl)

$lblCfToken = New-Object System.Windows.Forms.Label
$lblCfToken.Text = "Cloudflare Tunnel Token"
$lblCfToken.Location = New-Object System.Drawing.Point(12, 16)
$lblCfToken.AutoSize = $true
$pnlDetail.Controls.Add($lblCfToken)

$txtCfToken = New-Object System.Windows.Forms.TextBox
$txtCfToken.Location = New-Object System.Drawing.Point(12, 36)
$txtCfToken.Size = New-Object System.Drawing.Size(436, 24)
$pnlDetail.Controls.Add($txtCfToken)

$lblCfHint = New-Object System.Windows.Forms.Label
$lblCfHint.Text = "* Automatically registered as a Windows service if cloudflared is installed."
$lblCfHint.ForeColor = [System.Drawing.Color]::FromArgb(110, 110, 110)
$lblCfHint.Location = New-Object System.Drawing.Point(12, 68)
$lblCfHint.AutoSize = $true
$pnlDetail.Controls.Add($lblCfHint)

$syncNetworkUI = {
    $isExt = $rbScopeExternal.Checked
    $grpExtMode.Enabled = $isExt

    $lblModeInfo.Visible = $false
    $lblDomain.Visible = $false; $txtDomain.Visible = $false
    $lblDdnsUrl.Visible = $false; $txtDdnsUrl.Visible = $false
    $lblCfToken.Visible = $false; $txtCfToken.Visible = $false; $lblCfHint.Visible = $false

    $isKo = $rbLangKo.Checked
    if (-not $isExt) {
        $lblModeInfo.Visible = $true
        if ($isKo) {
            $lblModeInfo.Text = "로컬 네트워크(LAN) 전용 구성:`r`n- 같은 공유기/사내망 기기에서만 접속할 수 있도록 Windows 방화벽(LocalSubnet)을 제한 허용합니다.`r`n- 외부 인터넷에서는 접속되지 않습니다."
        } else {
            $lblModeInfo.Text = "Local network (LAN) configuration:`r`n- Restricts Windows Firewall to LocalSubnet for intranet access only.`r`n- Not accessible from the external internet."
        }
    } elseif ($rbExtPfd.Checked) {
        $lblDomain.Visible = $true; $txtDomain.Visible = $true
        $lblDdnsUrl.Visible = $true; $txtDdnsUrl.Visible = $true
    } elseif ($rbExtCf.Checked) {
        $lblCfToken.Visible = $true; $txtCfToken.Visible = $true; $lblCfHint.Visible = $true
    } else {
        $lblModeInfo.Visible = $true
        if ($isKo) {
            $lblModeInfo.Text = "사설 VPN (Tailscale / WireGuard 등) 구성:`r`n- 공유기 포트포워딩 없이 VPN 가상 네트워크를 통해서만 접속합니다.`r`n- 설치 시 방화벽 포트를 허용하고, Tailscale이 설치된 경우 VPN IP를 자동 감지합니다."
        } else {
            $lblModeInfo.Text = "Private VPN (Tailscale / WireGuard, etc.) configuration:`r`n- Access via secure virtual VPN network without port forwarding.`r`n- Opens firewall port and automatically detects Tailscale IP if installed."
        }
    }
}
$rbScopeLocal.Add_CheckedChanged($syncNetworkUI)
$rbScopeExternal.Add_CheckedChanged($syncNetworkUI)
$rbExtPfd.Add_CheckedChanged($syncNetworkUI)
$rbExtCf.Add_CheckedChanged($syncNetworkUI)
$rbExtVpn.Add_CheckedChanged($syncNetworkUI)

# Log Box
$txtLog = New-Object System.Windows.Forms.TextBox
$txtLog.Multiline = $true
$txtLog.ScrollBars = "Vertical"
$txtLog.ReadOnly = $true
$txtLog.BackColor = [System.Drawing.Color]::FromArgb(248, 248, 248)
$txtLog.Location = New-Object System.Drawing.Point(20, 444)
$txtLog.Size = New-Object System.Drawing.Size(464, 116)
$bodyPanel.Controls.Add($txtLog)

# Footer Buttons
$btnInstall = New-Object System.Windows.Forms.Button
$btnInstall.Text = "Install"
$btnInstall.Font = New-Object System.Drawing.Font("Segoe UI", 10, [System.Drawing.FontStyle]::Bold)
$btnInstall.BackColor = [System.Drawing.Color]::FromArgb(2, 132, 199)
$btnInstall.ForeColor = [System.Drawing.Color]::White
$btnInstall.FlatStyle = "Flat"
$btnInstall.FlatAppearance.BorderSize = 0
$btnInstall.Location = New-Object System.Drawing.Point(322, 668)
$btnInstall.Size = New-Object System.Drawing.Size(110, 36)
$form.Controls.Add($btnInstall)

$btnClose = New-Object System.Windows.Forms.Button
$btnClose.Text = "Close"
$btnClose.Location = New-Object System.Drawing.Point(440, 668)
$btnClose.Size = New-Object System.Drawing.Size(84, 36)
$btnClose.Add_Click({ $form.Close() })
$form.Controls.Add($btnClose)

# Localization Switcher
$applyLocalization = {
    $isKo = $rbLangKo.Checked
    if ($isKo) {
        $form.Text = "qplus 설치 마법사"
        $titleLbl.Text = "qplus 설치 마법사"
        $subLbl.Text = "웹 서버(IIS + PHP) 및 파일 저장소 경로를 지정하여 새 서버에 세팅합니다."
        $lblStep1.Text = "1. 프로그램 설치 경로 (웹 루트)"
        $btnBrowseInstall.Text = "찾아보기..."
        $lblStep2.Text = "2. 파일 공유 저장소 경로 (directory)"
        $btnBrowseStorage.Text = "찾아보기..."
        $lblStep3.Text = "3. 초기 관리자 비밀번호"
        $lblGuest.Text = "4. 초기 게스트 비밀번호"
        $lblStep5.Text = "5. 웹 포트 (기본 80)"
        $chkIIS.Text = "IIS 웹 서버 및 FastCGI 자동 구성"
        $lblStep6.Text = "6. 네트워크 구성 방식"
        $rbScopeLocal.Text = "로컬 네트워크 전용 (내부망/LAN)"
        $rbScopeExternal.Text = "외부 네트워크 (인터넷 접속)"
        $rbExtPfd.Text = "포트포워딩 + DDNS"
        $rbExtCf.Text = "Cloudflare Tunnel"
        $rbExtVpn.Text = "사설 VPN (Tailscale 등)"
        $lblDomain.Text = "접속 도메인/DDNS (선택, 예: storage.duckdns.org)"
        $lblDdnsUrl.Text = "DDNS 자동 갱신 URL (선택, 5분 주기 스케줄 등록)"
        $lblCfToken.Text = "Cloudflare Tunnel 토큰"
        $lblCfHint.Text = "※ cloudflared가 설치된 경우 Windows 서비스로 자동 등록됩니다."
        $btnInstall.Text = "설치 시작"
        $btnClose.Text = "닫기"
        if ($txtLog.Text -eq "" -or $txtLog.Text.StartsWith("Choose installation path")) {
            $txtLog.Text = "설치 경로, 비밀번호, 네트워크 방식을 선택한 후 '설치 시작'을 누르세요.`r`n"
        }
    } else {
        $form.Text = "qplus Setup Wizard"
        $titleLbl.Text = "qplus Setup Wizard"
        $subLbl.Text = "Configure web server (IIS + PHP) and storage for your private cloud."
        $lblStep1.Text = "1. Installation Path (Web Root)"
        $btnBrowseInstall.Text = "Browse..."
        $lblStep2.Text = "2. Shared Storage Path (directory)"
        $btnBrowseStorage.Text = "Browse..."
        $lblStep3.Text = "3. Admin Password"
        $lblGuest.Text = "4. Guest Password"
        $lblStep5.Text = "5. Web Port (Default: 80)"
        $chkIIS.Text = "Auto-configure IIS web server & FastCGI"
        $lblStep6.Text = "6. Network Topology"
        $rbScopeLocal.Text = "Local network only (LAN/Intranet)"
        $rbScopeExternal.Text = "External network (Internet access)"
        $rbExtPfd.Text = "Port Forwarding + DDNS"
        $rbExtCf.Text = "Cloudflare Tunnel"
        $rbExtVpn.Text = "Private VPN (Tailscale)"
        $lblDomain.Text = "Domain / DDNS (Optional, e.g. storage.duckdns.org)"
        $lblDdnsUrl.Text = "DDNS Auto-Update URL (Optional, 5-min scheduled task)"
        $lblCfToken.Text = "Cloudflare Tunnel Token"
        $lblCfHint.Text = "* Automatically registered as a Windows service if cloudflared is installed."
        $btnInstall.Text = "Install"
        $btnClose.Text = "Close"
        if ($txtLog.Text -eq "" -or $txtLog.Text.StartsWith("설치 경로")) {
            $txtLog.Text = "Choose installation path, passwords, and network mode, then click 'Install'.`r`n"
        }
    }
    & $syncNetworkUI
}

$rbLangEn.Add_CheckedChanged($applyLocalization)
$rbLangKo.Add_CheckedChanged($applyLocalization)
& $applyLocalization

$btnInstall.Add_Click({
    $btnInstall.Enabled = $false
    $btnClose.Enabled = $false
    $txtLog.Clear()
    $logAction = {
        param($m)
        $txtLog.AppendText("$m`r`n")
        [System.Windows.Forms.Application]::DoEvents()
    }
    try {
        $selectedScope = if ($rbScopeExternal.Checked) { "External" } else { "Local" }
        $selectedExtMode = if ($rbExtCf.Checked) { "Cloudflare" } elseif ($rbExtVpn.Checked) { "PrivateVPN" } else { "PortForwardDDNS" }
        $selectedLang = if ($rbLangKo.Checked) { "ko" } else { "en" }
        $accessResult = Invoke-QplusInstall `
            -TargetDir $txtInstall.Text `
            -DataDir $txtStorage.Text `
            -AdminSecret (ConvertTo-SecretString $txtAdminPw.Text) `
            -GuestSecret (ConvertTo-SecretString $txtGuestPw.Text) `
            -WebPort ([int]$numPort.Value) `
            -Scope $selectedScope `
            -ExtMode $selectedExtMode `
            -HostDomain $txtDomain.Text `
            -DdnsUrl $txtDdnsUrl.Text `
            -CfToken $txtCfToken.Text `
            -ConfigureIIS $chkIIS.Checked `
            -Lang $selectedLang `
            -LogCallback $logAction

        $succMsg = if ($selectedLang -eq "ko") {
            "qplus 서버 설치가 완료되었습니다!`n접속 주소: $accessResult"
        } else {
            "qplus server installation completed successfully!`nAccess URL: $accessResult"
        }
        $succTitle = if ($selectedLang -eq "ko") { "설치 완료" } else { "Installation Complete" }
        [System.Windows.Forms.MessageBox]::Show($succMsg, $succTitle, "OK", "Information") | Out-Null
    } catch {
        & $logAction "[Error] $($_.Exception.Message)"
        $errTitle = if ($rbLangKo.Checked) { "설치 오류" } else { "Installation Error" }
        [System.Windows.Forms.MessageBox]::Show($_.Exception.Message, $errTitle, "OK", "Error") | Out-Null
    } finally {
        $btnInstall.Enabled = $true
        $btnClose.Enabled = $true
    }
})

[void]$form.ShowDialog()
