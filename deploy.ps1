#Requires -Version 3.0
<#
.SYNOPSIS
    Deploy the NKS osTicket API plugin into an osTicket installation on Windows / IIS.

.DESCRIPTION
    Windows counterpart of deploy.sh. Copies the plugin into include\plugins\nks-osticket-api
    and the single web entry into api\nks-osticket.php. IIS serves that file directly, so the
    .htaccess bypass from deploy.sh is not needed; a rewrite rule in api\web.config that sends
    every request to http.php would hide it, which the script reports.

    Before overwriting, the current plugin folder and web entry are copied to
    %ProgramData%\nks-osticket-api\backup\<timestamp> (outside the web root).

.PARAMETER WebRoot
    osTicket web root (the folder with main.inc.php and api\). Default: detected from IIS via -HostName.
.PARAMETER HostName
    Host name of the IIS binding, e.g. helpdesk.example.com. Used to find the web root.
.PARAMETER PingUrl
    Full URL of api/nks-osticket.php. With -ApiKey, meta.ping is called after the copy.
.PARAMETER ApiKey
    API key for the meta.ping check (not stored).
.PARAMETER RecycleAppPool
    Recycle the site's application pool so PHP's OPcache drops the old files.

.EXAMPLE
    .\deploy.ps1 -HostName helpdesk.example.com -PingUrl https://helpdesk.example.com/api/nks-osticket.php -ApiKey <key>
.EXAMPLE
    .\deploy.ps1 -WebRoot C:\inetpub\osticket\upload
#>
[CmdletBinding()]
param(
    [string]$WebRoot  = '',
    [string]$HostName = '',
    [string]$PingUrl  = '',
    [string]$ApiKey   = '',
    [switch]$RecycleAppPool
)

$ErrorActionPreference = 'Stop'
$src = $PSScriptRoot
$appPool = $null

# Works on Windows PowerShell 3.0+ (older servers); hence no #Requires -RunAsAdministrator (4.0+)
$principal = New-Object Security.Principal.WindowsPrincipal([Security.Principal.WindowsIdentity]::GetCurrent())
if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) { throw 'Run in an elevated PowerShell (Administrator).' }

# --- web root ----------------------------------------------------------------
if (-not $WebRoot) {
    if (-not $HostName) { throw 'Pass -WebRoot or -HostName.' }
    Import-Module WebAdministration
    $site = Get-Website | Where-Object { $_.bindings.Collection.bindingInformation -match (':' + [regex]::Escape($HostName) + '$') } |
            Select-Object -First 1
    if (-not $site) { throw "No IIS site with a binding for $HostName." }
    # osTicket can be the site itself or an application below it
    $candidates = @([pscustomobject]@{ Path = $site.physicalPath; Pool = $site.applicationPool }) +
                  @(Get-WebApplication -Site $site.name | ForEach-Object { [pscustomobject]@{ Path = $_.PhysicalPath; Pool = $_.applicationPool } })
    $hit = $candidates | Where-Object { Test-Path (Join-Path ([Environment]::ExpandEnvironmentVariables($_.Path)) 'main.inc.php') } |
           Select-Object -First 1
    if (-not $hit) { throw "Site '$($site.name)' found, but no folder with main.inc.php. Pass -WebRoot." }
    $WebRoot = [Environment]::ExpandEnvironmentVariables($hit.Path)
    $appPool = $hit.Pool
    Write-Host "IIS site '$($site.name)', web root $WebRoot, app pool $appPool"
}
if (-not (Test-Path (Join-Path $WebRoot 'main.inc.php'))) { throw "$WebRoot\main.inc.php not found - wrong web root?" }
if (-not (Test-Path (Join-Path $WebRoot 'api'))) { throw "$WebRoot\api not found" }

$pluginDir = Join-Path $WebRoot 'include\plugins\nks-osticket-api'
$entry     = Join-Path $WebRoot 'api\nks-osticket.php'

# --- backup ------------------------------------------------------------------
$backup = Join-Path $env:ProgramData ('nks-osticket-api\backup\' + (Get-Date -Format 'yyyyMMdd_HHmmss'))
if ((Test-Path $pluginDir) -or (Test-Path $entry)) {
    New-Item -ItemType Directory -Force -Path $backup | Out-Null
    if (Test-Path $pluginDir) { Copy-Item $pluginDir (Join-Path $backup 'nks-osticket-api') -Recurse }
    if (Test-Path $entry)     { Copy-Item $entry $backup }
    Write-Host "-> backup in $backup"
}

# --- copy --------------------------------------------------------------------
Write-Host "-> installing plugin into $pluginDir"
New-Item -ItemType Directory -Force -Path $pluginDir | Out-Null
# replace src completely so files removed upstream do not linger
if (Test-Path (Join-Path $pluginDir 'src')) { Remove-Item (Join-Path $pluginDir 'src') -Recurse -Force }
Copy-Item (Join-Path $src 'src') $pluginDir -Recurse
foreach ($f in 'plugin.php', 'config.php', 'class.NksOsTicketApiPlugin.php') { Copy-Item (Join-Path $src $f) $pluginDir -Force }

Write-Host "-> installing web entry into $entry"
Copy-Item (Join-Path $src 'web\nks-osticket.php') $entry -Force

# --- IIS rewrite check -------------------------------------------------------
$wc = Join-Path $WebRoot 'api\web.config'
if ((Test-Path $wc) -and (Select-String -Path $wc -Pattern '<rewrite>' -Quiet) -and -not (Select-String -Path $wc -Pattern 'nks-osticket' -Quiet)) {
    Write-Warning "$wc has rewrite rules. If they send every request to http.php, add as FIRST rule:"
    Write-Warning '<rule name="nks-osticket" stopProcessing="true"><match url="^nks-osticket\.php" /><action type="None" /></rule>'
}

if ($RecycleAppPool -and $appPool) {
    Restart-WebAppPool -Name $appPool
    Write-Host "-> app pool $appPool recycled"
}

# --- check -------------------------------------------------------------------
$version = (Select-String -Path (Join-Path $pluginDir 'src\Kernel.php') -Pattern "VERSION = '([^']+)'").Matches[0].Groups[1].Value
Write-Host "done. Deployed plugin version $version."
if ($PingUrl -and $ApiKey) {
    [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
    try {
        $r = Invoke-RestMethod -Uri "$($PingUrl)?action=meta.ping" -Headers @{ 'X-Api-Key' = $ApiKey } -UseBasicParsing
        Write-Host "meta.ping: version $($r.data.version), keyId $($r.data.keyId), osTicket $($r.data.osticket)"
        if ($r.data.version -ne $version) { Write-Warning "Endpoint still reports $($r.data.version): run again with -RecycleAppPool (OPcache)." }
    } catch {
        Write-Warning "meta.ping failed: $(if ($_.ErrorDetails.Message) { $_.ErrorDetails.Message } else { $_.Exception.Message })"
    }
}
