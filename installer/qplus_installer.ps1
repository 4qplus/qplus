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
    [switch]$SkipIIS
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
        [scriptblock]$LogCallback = $null
    )

    function Write-StepLog($msg) {
        if ($LogCallback) { & $LogCallback $msg }
        else { Write-Host $msg }
    }

    $TargetDir = $TargetDir.Trim().TrimEnd('\', '/')
    $DataDir = $DataDir.Trim().TrimEnd('\', '/')
    if (-not $TargetDir) { throw "설치 디렉터리를 지정하세요." }
    if (-not $DataDir) { $DataDir = Join-Path $TargetDir "directory" }
    if ($null -eq $AdminSecret -or $AdminSecret.Length -eq 0) { throw "초기 관리자 비밀번호를 입력하세요." }
    if ($null -eq $GuestSecret -or $GuestSecret.Length -eq 0) { throw "초기 게스트 비밀번호를 입력하세요." }
    if (-not (Test-Path $PayloadZip)) { throw "설치 패키지(payload.zip)를 찾을 수 없습니다: $PayloadZip" }

    Write-StepLog "[1/6] 설치 디렉터리 준비 중: $TargetDir"
    New-Item -ItemType Directory -Force -Path $TargetDir | Out-Null
    New-Item -ItemType Directory -Force -Path $DataDir | Out-Null
    New-Item -ItemType Directory -Force -Path (Join-Path $TargetDir "temp") | Out-Null
    New-Item -ItemType Directory -Force -Path (Join-Path $TargetDir "qplus") | Out-Null

    Write-StepLog "[2/6] qplus 시스템 및 PHP 런타임 압축 해제 중..."
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

    Write-StepLog "[3/6] PHP 설정(php.ini) 및 보안 설정(config.php) 생성 중..."
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
$cfg = array(
    'admin_pw' => password_hash($adminPw, PASSWORD_DEFAULT),
    'guest_pw' => password_hash($guestPw, PASSWORD_DEFAULT),
    'folder_pws' => array(),
    'storage_dir' => $storageDir,
);
file_put_contents(getenv('QP_CFG_PATH'), "<?php return " . var_export($cfg, true) . "; ?>");
'@
    [System.IO.File]::WriteAllText($hashScript, $phpCode, (New-Object System.Text.UTF8Encoding $false))
    $env:QP_ADMIN_PW = Read-SecretString $AdminSecret
    $env:QP_GUEST_PW = Read-SecretString $GuestSecret
    $env:QP_STORAGE_DIR = $storageForward
    $env:QP_CFG_PATH = Join-Path $TargetDir "config.php"
    try {
        & $phpExe $hashScript
        if ($LASTEXITCODE -ne 0) { throw "비밀번호 암호화 초기화에 실패했습니다. (Visual C++ x64 재배포 패키지 설치 여부를 확인하세요.)" }
    } finally {
        Remove-Item $hashScript -ErrorAction SilentlyContinue
        Remove-Item Env:QP_ADMIN_PW, Env:QP_GUEST_PW, Env:QP_STORAGE_DIR, Env:QP_CFG_PATH -ErrorAction SilentlyContinue
    }

    # Initialize empty data files
    $tipsPath = Join-Path $TargetDir "tips_data.php"
    $reqPath = Join-Path $TargetDir "requests_data.php"
    if (-not (Test-Path $tipsPath)) { [System.IO.File]::WriteAllText($tipsPath, "<?php return array(); ?>", (New-Object System.Text.UTF8Encoding $false)) }
    if (-not (Test-Path $reqPath)) { [System.IO.File]::WriteAllText($reqPath, "<?php return array(); ?>", (New-Object System.Text.UTF8Encoding $false)) }

    Write-StepLog "[4/6] 디렉터리 쓰기 권한(IIS_IUSRS) 부여 중..."
    & icacls $DataDir /grant "BUILTIN\IIS_IUSRS:(OI)(CI)M" /T /Q | Out-Null
    & icacls (Join-Path $TargetDir "temp") /grant "BUILTIN\IIS_IUSRS:(OI)(CI)F" /T /Q | Out-Null
    & icacls (Join-Path $TargetDir "qplus") /grant "BUILTIN\IIS_IUSRS:(OI)(CI)M" /T /Q | Out-Null
    & icacls (Join-Path $TargetDir "config.php") /grant "BUILTIN\IIS_IUSRS:M" /Q | Out-Null
    & icacls $tipsPath /grant "BUILTIN\IIS_IUSRS:M" /Q | Out-Null
    & icacls $reqPath /grant "BUILTIN\IIS_IUSRS:M" /Q | Out-Null

    if ($ConfigureIIS) {
        Write-StepLog "[5/6] IIS 웹 서버 및 FastCGI(PHP) 구성 중..."
        $appcmd = Join-Path $env:SystemRoot "System32\inetsrv\appcmd.exe"
        if (-not (Test-Path $appcmd)) {
            Write-StepLog "  - Windows IIS 및 CGI 기능 활성화 중 (시간이 다소 소요될 수 있습니다)..."
            $features = @("IIS-WebServerRole", "IIS-WebServer", "IIS-CommonHttpFeatures", "IIS-DefaultDocument", "IIS-StaticContent", "IIS-Security", "IIS-RequestFiltering", "IIS-ApplicationDevelopment", "IIS-CGI")
            foreach ($f in $features) {
                dism.exe /Online /Enable-Feature /FeatureName:$f /All /NoRestart /Quiet | Out-Null
            }
        }
        if (-not (Test-Path $appcmd)) { throw "IIS(appcmd.exe)를 활성화할 수 없습니다." }

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
        Write-StepLog "[5/6] IIS 자동 구성을 건너뛰었습니다."
    }

    $accessInfo = "http://localhost:$WebPort/"
    if (-not $ConfigureIIS) {
        Write-StepLog "[6/6] IIS 건너뛰기 옵션으로 네트워크/방화벽 구성을 생략했습니다."
    } elseif ($Scope -eq "Local") {
        Write-StepLog "[6/6] 로컬 네트워크(내부망 전용) 방화벽 구성 중..."
        try {
            Set-QplusFirewallRule -RuleName "qplus-Local-HTTP-$WebPort" -Port $WebPort -RemoteAddress "LocalSubnet"
            Write-StepLog "  - Windows 방화벽에 내부망(LocalSubnet) TCP $WebPort 허용 규칙을 등록했습니다."
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
                Write-StepLog "[6/6] 외부 네트워크(포트포워딩 + DDNS) 구성 중..."
                try {
                    Set-QplusFirewallRule -RuleName "qplus-External-HTTP-$WebPort" -Port $WebPort -RemoteAddress "Any"
                    Write-StepLog "  - Windows 방화벽에 외부 접속용 TCP $WebPort 허용 규칙을 등록했습니다."
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
                Write-StepLog "[6/6] 외부 네트워크(Cloudflare Tunnel) 구성 중..."
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
                Write-StepLog "[6/6] 외부 네트워크(사설 VPN) 구성 중..."
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

    Write-StepLog "설치가 성공적으로 완료되었습니다! 접속 주소: $accessInfo"
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
        -ConfigureIIS (-not $SkipIIS) | Out-Null
    exit 0
}

# Build GUI Wizard
Add-Type -AssemblyName System.Windows.Forms
Add-Type -AssemblyName System.Drawing
[System.Windows.Forms.Application]::EnableVisualStyles()

$form = New-Object System.Windows.Forms.Form
$form.Text = "qplus 설치 마법사"
$form.Size = New-Object System.Drawing.Size(560, 745)
$form.StartPosition = "CenterScreen"
$form.FormBorderStyle = "FixedDialog"
$form.MaximizeBox = $false
$form.BackColor = [System.Drawing.Color]::FromArgb(245, 245, 245)
$form.Font = New-Object System.Drawing.Font("Malgun Gothic", 9.5)

$headerPanel = New-Object System.Windows.Forms.Panel
$headerPanel.Dock = "Top"
$headerPanel.Height = 68
$headerPanel.BackColor = [System.Drawing.Color]::White
$form.Controls.Add($headerPanel)

$titleLbl = New-Object System.Windows.Forms.Label
$titleLbl.Text = "qplus 설치 마법사"
$titleLbl.Font = New-Object System.Drawing.Font("Malgun Gothic", 13, [System.Drawing.FontStyle]::Bold)
$titleLbl.ForeColor = [System.Drawing.Color]::FromArgb(20, 20, 20)
$titleLbl.Location = New-Object System.Drawing.Point(22, 14)
$titleLbl.AutoSize = $true
$headerPanel.Controls.Add($titleLbl)

$subLbl = New-Object System.Windows.Forms.Label
$subLbl.Text = "웹 서버(IIS + PHP) 및 파일 저장소 경로를 지정하여 새 서버에 세팅합니다."
$subLbl.ForeColor = [System.Drawing.Color]::FromArgb(100, 100, 100)
$subLbl.Location = New-Object System.Drawing.Point(23, 41)
$subLbl.AutoSize = $true
$headerPanel.Controls.Add($subLbl)

$bodyPanel = New-Object System.Windows.Forms.Panel
$bodyPanel.Location = New-Object System.Drawing.Point(20, 82)
$bodyPanel.Size = New-Object System.Drawing.Size(504, 565)
$bodyPanel.BackColor = [System.Drawing.Color]::White
$form.Controls.Add($bodyPanel)

function Add-FieldLabel($parent, $text, $y) {
    $lbl = New-Object System.Windows.Forms.Label
    $lbl.Text = $text
    $lbl.Font = New-Object System.Drawing.Font("Malgun Gothic", 9, [System.Drawing.FontStyle]::Bold)
    $lbl.ForeColor = [System.Drawing.Color]::FromArgb(40, 40, 40)
    $lbl.Location = New-Object System.Drawing.Point(20, $y)
    $lbl.AutoSize = $true
    $parent.Controls.Add($lbl)
}

# 1. Install Directory
$defaultDrive = if (Test-Path "D:\") { "D:" } else { "C:" }
$defaultInstall = if ($InstallDir) { $InstallDir } else { "$defaultDrive\qplus" }
$defaultStorage = if ($StorageDir) { $StorageDir } else { "$defaultInstall\directory" }

Add-FieldLabel $bodyPanel "1. 프로그램 설치 경로 (웹 루트)" 16
$txtInstall = New-Object System.Windows.Forms.TextBox
$txtInstall.Text = $defaultInstall
$txtInstall.Location = New-Object System.Drawing.Point(20, 38)
$txtInstall.Size = New-Object System.Drawing.Size(375, 26)
$bodyPanel.Controls.Add($txtInstall)

$btnBrowseInstall = New-Object System.Windows.Forms.Button
$btnBrowseInstall.Text = "찾아보기..."
$btnBrowseInstall.Location = New-Object System.Drawing.Point(402, 36)
$btnBrowseInstall.Size = New-Object System.Drawing.Size(82, 27)
$bodyPanel.Controls.Add($btnBrowseInstall)

# 2. Storage Directory
Add-FieldLabel $bodyPanel "2. 파일 공유 저장소 경로 (directory)" 74
$txtStorage = New-Object System.Windows.Forms.TextBox
$txtStorage.Text = $defaultStorage
$txtStorage.Location = New-Object System.Drawing.Point(20, 96)
$txtStorage.Size = New-Object System.Drawing.Size(375, 26)
$bodyPanel.Controls.Add($txtStorage)

$btnBrowseStorage = New-Object System.Windows.Forms.Button
$btnBrowseStorage.Text = "찾아보기..."
$btnBrowseStorage.Location = New-Object System.Drawing.Point(402, 94)
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
    $fbd.Description = "qplus 프로그램을 설치할 폴더를 선택하세요."
    if ($txtInstall.Text -and (Test-Path $txtInstall.Text)) { $fbd.SelectedPath = $txtInstall.Text }
    if ($fbd.ShowDialog() -eq "OK") { $txtInstall.Text = $fbd.SelectedPath }
})
$btnBrowseStorage.Add_Click({
    $fbd = New-Object System.Windows.Forms.FolderBrowserDialog
    $fbd.Description = "공유 파일을 저장할 디렉터리 경로를 선택하세요."
    if ($txtStorage.Text -and (Test-Path $txtStorage.Text)) { $fbd.SelectedPath = $txtStorage.Text }
    if ($fbd.ShowDialog() -eq "OK") {
        $script:customStorageEdited = $true
        $txtStorage.Text = $fbd.SelectedPath
    }
})

# 3. Initial Passwords
Add-FieldLabel $bodyPanel "3. 초기 관리자 비밀번호" 134
$txtAdminPw = New-Object System.Windows.Forms.TextBox
$txtAdminPw.UseSystemPasswordChar = $true
$txtAdminPw.Location = New-Object System.Drawing.Point(20, 156)
$txtAdminPw.Size = New-Object System.Drawing.Size(222, 26)
$bodyPanel.Controls.Add($txtAdminPw)

$lblGuest = New-Object System.Windows.Forms.Label
$lblGuest.Text = "4. 초기 게스트 비밀번호"
$lblGuest.Font = New-Object System.Drawing.Font("Malgun Gothic", 9, [System.Drawing.FontStyle]::Bold)
$lblGuest.ForeColor = [System.Drawing.Color]::FromArgb(40, 40, 40)
$lblGuest.Location = New-Object System.Drawing.Point(262, 134)
$lblGuest.AutoSize = $true
$bodyPanel.Controls.Add($lblGuest)

$txtGuestPw = New-Object System.Windows.Forms.TextBox
$txtGuestPw.UseSystemPasswordChar = $true
$txtGuestPw.Location = New-Object System.Drawing.Point(262, 156)
$txtGuestPw.Size = New-Object System.Drawing.Size(222, 26)
$bodyPanel.Controls.Add($txtGuestPw)

# 5. IIS Port & Option
Add-FieldLabel $bodyPanel "5. 웹 포트 (기본 80)" 194
$numPort = New-Object System.Windows.Forms.NumericUpDown
$numPort.Minimum = 1; $numPort.Maximum = 65535; $numPort.Value = 80
$numPort.Location = New-Object System.Drawing.Point(20, 216)
$numPort.Size = New-Object System.Drawing.Size(100, 26)
$bodyPanel.Controls.Add($numPort)

$chkIIS = New-Object System.Windows.Forms.CheckBox
$chkIIS.Text = "IIS 웹 서버 및 FastCGI 자동 구성"
$chkIIS.Checked = $true
$chkIIS.Location = New-Object System.Drawing.Point(140, 218)
$chkIIS.AutoSize = $true
$bodyPanel.Controls.Add($chkIIS)

# 6. Network Topology Selection
Add-FieldLabel $bodyPanel "6. 네트워크 구성 방식" 252

$grpScope = New-Object System.Windows.Forms.Panel
$grpScope.Location = New-Object System.Drawing.Point(20, 274)
$grpScope.Size = New-Object System.Drawing.Size(464, 28)
$bodyPanel.Controls.Add($grpScope)

$rbScopeLocal = New-Object System.Windows.Forms.RadioButton
$rbScopeLocal.Text = "로컬 네트워크 전용 (내부망/LAN)"
$rbScopeLocal.Location = New-Object System.Drawing.Point(0, 3)
$rbScopeLocal.AutoSize = $true
$rbScopeLocal.Checked = $true
$grpScope.Controls.Add($rbScopeLocal)

$rbScopeExternal = New-Object System.Windows.Forms.RadioButton
$rbScopeExternal.Text = "외부 네트워크 (인터넷 접속)"
$rbScopeExternal.Location = New-Object System.Drawing.Point(235, 3)
$rbScopeExternal.AutoSize = $true
$grpScope.Controls.Add($rbScopeExternal)

$grpExtMode = New-Object System.Windows.Forms.Panel
$grpExtMode.Location = New-Object System.Drawing.Point(20, 304)
$grpExtMode.Size = New-Object System.Drawing.Size(464, 28)
$grpExtMode.Enabled = $false
$bodyPanel.Controls.Add($grpExtMode)

$rbExtPfd = New-Object System.Windows.Forms.RadioButton
$rbExtPfd.Text = "포트포워딩 + DDNS"
$rbExtPfd.Location = New-Object System.Drawing.Point(12, 3)
$rbExtPfd.AutoSize = $true
$rbExtPfd.Checked = $true
$grpExtMode.Controls.Add($rbExtPfd)

$rbExtCf = New-Object System.Windows.Forms.RadioButton
$rbExtCf.Text = "Cloudflare Tunnel"
$rbExtCf.Location = New-Object System.Drawing.Point(170, 3)
$rbExtCf.AutoSize = $true
$grpExtMode.Controls.Add($rbExtCf)

$rbExtVpn = New-Object System.Windows.Forms.RadioButton
$rbExtVpn.Text = "사설 VPN (Tailscale 등)"
$rbExtVpn.Location = New-Object System.Drawing.Point(312, 3)
$rbExtVpn.AutoSize = $true
$grpExtMode.Controls.Add($rbExtVpn)

# Dynamic Detail Panel
$pnlDetail = New-Object System.Windows.Forms.Panel
$pnlDetail.Location = New-Object System.Drawing.Point(20, 336)
$pnlDetail.Size = New-Object System.Drawing.Size(464, 102)
$pnlDetail.BackColor = [System.Drawing.Color]::FromArgb(248, 250, 252)
$pnlDetail.BorderStyle = "FixedSingle"
$bodyPanel.Controls.Add($pnlDetail)

$lblModeInfo = New-Object System.Windows.Forms.Label
$lblModeInfo.Location = New-Object System.Drawing.Point(12, 12)
$lblModeInfo.Size = New-Object System.Drawing.Size(438, 78)
$lblModeInfo.ForeColor = [System.Drawing.Color]::FromArgb(70, 70, 70)
$pnlDetail.Controls.Add($lblModeInfo)

$lblDomain = New-Object System.Windows.Forms.Label
$lblDomain.Text = "접속 도메인/DDNS (선택, 예: storage.duckdns.org)"
$lblDomain.Location = New-Object System.Drawing.Point(12, 8)
$lblDomain.AutoSize = $true
$pnlDetail.Controls.Add($lblDomain)

$txtDomain = New-Object System.Windows.Forms.TextBox
$txtDomain.Location = New-Object System.Drawing.Point(12, 26)
$txtDomain.Size = New-Object System.Drawing.Size(436, 23)
$pnlDetail.Controls.Add($txtDomain)

$lblDdnsUrl = New-Object System.Windows.Forms.Label
$lblDdnsUrl.Text = "DDNS 자동 갱신 URL (선택, 5분 주기 스케줄 등록)"
$lblDdnsUrl.Location = New-Object System.Drawing.Point(12, 53)
$lblDdnsUrl.AutoSize = $true
$pnlDetail.Controls.Add($lblDdnsUrl)

$txtDdnsUrl = New-Object System.Windows.Forms.TextBox
$txtDdnsUrl.Location = New-Object System.Drawing.Point(12, 71)
$txtDdnsUrl.Size = New-Object System.Drawing.Size(436, 23)
$pnlDetail.Controls.Add($txtDdnsUrl)

$lblCfToken = New-Object System.Windows.Forms.Label
$lblCfToken.Text = "Cloudflare Tunnel 토큰"
$lblCfToken.Location = New-Object System.Drawing.Point(12, 16)
$lblCfToken.AutoSize = $true
$pnlDetail.Controls.Add($lblCfToken)

$txtCfToken = New-Object System.Windows.Forms.TextBox
$txtCfToken.Location = New-Object System.Drawing.Point(12, 36)
$txtCfToken.Size = New-Object System.Drawing.Size(436, 24)
$pnlDetail.Controls.Add($txtCfToken)

$lblCfHint = New-Object System.Windows.Forms.Label
$lblCfHint.Text = "※ cloudflared가 설치된 경우 Windows 서비스로 자동 등록됩니다."
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

    if (-not $isExt) {
        $lblModeInfo.Visible = $true
        $lblModeInfo.Text = "로컬 네트워크(LAN) 전용 구성:`r`n- 같은 공유기/사내망 기기에서만 접속할 수 있도록 Windows 방화벽(LocalSubnet)을 제한 허용합니다.`r`n- 외부 인터넷에서는 접속되지 않습니다."
    } elseif ($rbExtPfd.Checked) {
        $lblDomain.Visible = $true; $txtDomain.Visible = $true
        $lblDdnsUrl.Visible = $true; $txtDdnsUrl.Visible = $true
    } elseif ($rbExtCf.Checked) {
        $lblCfToken.Visible = $true; $txtCfToken.Visible = $true; $lblCfHint.Visible = $true
    } else {
        $lblModeInfo.Visible = $true
        $lblModeInfo.Text = "사설 VPN (Tailscale / WireGuard 등) 구성:`r`n- 공유기 포트포워딩 없이 VPN 가상 네트워크를 통해서만 접속합니다.`r`n- 설치 시 방화벽 포트를 허용하고, Tailscale이 설치된 경우 VPN IP를 자동 감지합니다."
    }
}
$rbScopeLocal.Add_CheckedChanged($syncNetworkUI)
$rbScopeExternal.Add_CheckedChanged($syncNetworkUI)
$rbExtPfd.Add_CheckedChanged($syncNetworkUI)
$rbExtCf.Add_CheckedChanged($syncNetworkUI)
$rbExtVpn.Add_CheckedChanged($syncNetworkUI)
& $syncNetworkUI

# Log Box
$txtLog = New-Object System.Windows.Forms.TextBox
$txtLog.Multiline = $true
$txtLog.ReadOnly = $true
$txtLog.ScrollBars = "Vertical"
$txtLog.BackColor = [System.Drawing.Color]::FromArgb(248, 248, 248)
$txtLog.Location = New-Object System.Drawing.Point(20, 448)
$txtLog.Size = New-Object System.Drawing.Size(464, 102)
$txtLog.Text = "설치 경로, 비밀번호, 네트워크 방식을 선택한 후 '설치 시작'을 누르세요.`r`n"
$bodyPanel.Controls.Add($txtLog)

# Footer Buttons
$btnInstall = New-Object System.Windows.Forms.Button
$btnInstall.Text = "설치 시작"
$btnInstall.Font = New-Object System.Drawing.Font("Malgun Gothic", 10, [System.Drawing.FontStyle]::Bold)
$btnInstall.BackColor = [System.Drawing.Color]::FromArgb(2, 132, 199)
$btnInstall.ForeColor = [System.Drawing.Color]::White
$btnInstall.FlatStyle = "Flat"
$btnInstall.FlatAppearance.BorderSize = 0
$btnInstall.Location = New-Object System.Drawing.Point(322, 658)
$btnInstall.Size = New-Object System.Drawing.Size(110, 36)
$form.Controls.Add($btnInstall)

$btnClose = New-Object System.Windows.Forms.Button
$btnClose.Text = "닫기"
$btnClose.Location = New-Object System.Drawing.Point(440, 658)
$btnClose.Size = New-Object System.Drawing.Size(84, 36)
$btnClose.Add_Click({ $form.Close() })
$form.Controls.Add($btnClose)

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
            -LogCallback $logAction
        [System.Windows.Forms.MessageBox]::Show("qplus 서버 설치가 완료되었습니다!`n접속 주소: $accessResult", "설치 완료", "OK", "Information") | Out-Null
    } catch {
        & $logAction "[오류] $($_.Exception.Message)"
        [System.Windows.Forms.MessageBox]::Show($_.Exception.Message, "설치 오류", "OK", "Error") | Out-Null
    } finally {
        $btnInstall.Enabled = $true
        $btnClose.Enabled = $true
    }
})

[void]$form.ShowDialog()

