param(
    [string]$RepoPath = "C:\MAMP\htdocs\telegram",
    [switch]$PatchInitDb
)

$ErrorActionPreference = "Stop"

function Write-Utf8NoBom {
    param(
        [string]$Path,
        [string]$Text
    )
    $enc = New-Object System.Text.UTF8Encoding($false)
    [System.IO.File]::WriteAllText($Path, $Text, $enc)
}

function Backup-File {
    param(
        [string]$FilePath,
        [string]$RepoRoot,
        [string]$BackupRoot
    )
    $rel = $FilePath.Substring($RepoRoot.Length).TrimStart('\','/')
    $dest = Join-Path $BackupRoot $rel
    $destDir = Split-Path $dest -Parent
    if (-not (Test-Path $destDir)) {
        New-Item -ItemType Directory -Path $destDir | Out-Null
    }
    Copy-Item -Path $FilePath -Destination $dest -Force
}

if (-not (Test-Path $RepoPath)) {
    throw "Repo path not found: $RepoPath"
}

$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
$backupRoot = Join-Path $RepoPath ("backup_before_hostinger_patch_" + $timestamp)
New-Item -ItemType Directory -Path $backupRoot | Out-Null

$targets = @(
    "config.php",
    "app\Config\database.php",
    "index.php",
    "app\Core\Router.php",
    "app\Modules\Telegram\Services\TelegramService.php",
    "telegram.php",
    ".htaccess",
    "init_db.sql"
)

foreach ($rel in $targets) {
    $full = Join-Path $RepoPath $rel
    if (Test-Path $full) {
        Backup-File -FilePath $full -RepoRoot $RepoPath -BackupRoot $backupRoot
    }
}

Write-Host "Backup created at: $backupRoot"

# 1) config.php
$configPath = Join-Path $RepoPath "config.php"
$configText = @"
<?php
if (!function_exists('envv')) {
    function envv(`$key, `$default = null) {
        `$value = getenv(`$key);
        return (`$value === false || `$value === '') ? `$default : `$value;
    }
}

define('APP_ENV', envv('APP_ENV', 'local'));
define('DISPLAY_ERRORS', envv('DISPLAY_ERRORS', APP_ENV === 'production' ? '0' : '1'));

define('DB_HOST', envv('DB_HOST', '127.0.0.1'));
define('DB_NAME', envv('DB_NAME', 'telegrambot'));
define('DB_USER', envv('DB_USER', 'root'));
define('DB_PASS', envv('DB_PASS', 'root'));

define('DB_DSN', 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4');

define('BASE_API_URL', envv('BASE_API_URL', 'https://api.telegram.org/bot'));
define('APP_URL', rtrim(envv('APP_URL', 'http://localhost/telegram'), '/'));
define('TELEGRAM_VERIFY_SSL', envv('TELEGRAM_VERIFY_SSL', APP_ENV === 'production' ? '1' : '0'));
?>
"@
Write-Utf8NoBom -Path $configPath -Text $configText

# 2) app/Config/database.php
$dbConfigPath = Join-Path $RepoPath "app\Config\database.php"
$dbConfigText = @"
<?php
require_once dirname(__DIR__, 2) . '/config.php';
"@
Write-Utf8NoBom -Path $dbConfigPath -Text $dbConfigText

# 3) index.php targeted replacements
$indexPath = Join-Path $RepoPath "index.php"
$index = Get-Content -Raw -Path $indexPath

$index = $index -replace "ini_set\('display_errors',\s*1\);", "ini_set('display_errors', defined('DISPLAY_ERRORS') ? DISPLAY_ERRORS : '0');"

$index = $index -replace [regex]::Escape("session_set_cookie_params(0, `$base . '/');"), @"
session_set_cookie_params([
    'lifetime' => 0,
    'path' => `$base . '/',
    'secure' => (!empty(`$_SERVER['HTTPS']) && `$_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Lax',
]);
"@

Write-Utf8NoBom -Path $indexPath -Text $index

# 4) app/Core/Router.php remove debug emit block
$routerPath = Join-Path $RepoPath "app\Core\Router.php"
$router = Get-Content -Raw -Path $routerPath
$router = [regex]::Replace(
    $router,
    "(?s)\s*// Debug: Log the path being matched\s*if \(ini_get\('display_errors'\)\) \{\s*echo ""<!-- Debug: Method=\$method, Path=\$path -->"";\s*\}\s*",
    "`r`n"
)
Write-Utf8NoBom -Path $routerPath -Text $router

# 5) TelegramService SSL verification settings
$tgServicePath = Join-Path $RepoPath "app\Modules\Telegram\Services\TelegramService.php"
$tgService = Get-Content -Raw -Path $tgServicePath
$tgService = $tgService -replace "CURLOPT_SSL_VERIFYPEER\s*=>\s*false,", "CURLOPT_SSL_VERIFYPEER => (defined('TELEGRAM_VERIFY_SSL') ? TELEGRAM_VERIFY_SSL === '1' : true),"
$tgService = $tgService -replace "CURLOPT_SSL_VERIFYHOST\s*=>\s*false,", "CURLOPT_SSL_VERIFYHOST => (defined('TELEGRAM_VERIFY_SSL') && TELEGRAM_VERIFY_SSL === '1' ? 2 : 0),"
Write-Utf8NoBom -Path $tgServicePath -Text $tgService

# 6) telegram.php fix API constant + production debug guard
$legacyTgPath = Join-Path $RepoPath "telegram.php"
$legacyTg = Get-Content -Raw -Path $legacyTgPath
$legacyTg = $legacyTg -replace "API_URL", "BASE_API_URL"

$legacyTg = [regex]::Replace(
    $legacyTg,
    "(?s)// Debug log \(IMPORTANT\)\s*file_put_contents\(__DIR__ \. ""/tg_debug\.log"",\s*date\('c'\) \. "" METHOD=\{\$method\}\\nREQ="" \. json_encode\(\$data\) \. ""\\nRES=\{\$res\}\\nERR=\{\$err\}\\n-----------------\\n"",\s*FILE_APPEND\s*\);",
@"
if (!defined('APP_ENV') || APP_ENV !== 'production') {
  file_put_contents(__DIR__ . "/tg_debug.log",
    date('c') . " METHOD={$method}\nREQ=" . json_encode($data) . "\nRES={$res}\nERR={$err}\n-----------------\n",
    FILE_APPEND
  );
}
"@
)

Write-Utf8NoBom -Path $legacyTgPath -Text $legacyTg

# 7) .htaccess
$htaccessPath = Join-Path $RepoPath ".htaccess"
$htaccessText = @"
RewriteEngine On

RewriteRule ^(reset_db|apply_migration|run_db_update|fix_db|debug_p2p|test|test_dump|test_fetch|add_temp_payload|cleanup_failed_deposit|fix_legacy_withdrawals)\.php$ - [F,L,NC]
RewriteRule ^(tmp|scripts|database|cron|storage/logs|storage/sessions)/ - [F,L,NC]

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [L]
"@
Write-Utf8NoBom -Path $htaccessPath -Text $htaccessText

# 8) .gitignore create/merge
$gitignorePath = Join-Path $RepoPath ".gitignore"
$gitignoreEntries = @(
    "/storage/logs/*.log",
    "/storage/sessions/*",
    "/storage/receipts/*",
    "!/storage/receipts/.gitkeep",
    "/tmp/*",
    "/debug_output.json",
    "/status.json",
    "/tg_debug.log",
    "/test.php",
    "/test_dump.php",
    "/test_fetch.php",
    "/debug_p2p.php",
    "/fix_db.php",
    "/reset_db.php",
    "/apply_migration.php",
    "/run_db_update.php"
)

if (Test-Path $gitignorePath) {
    $existing = Get-Content -Path $gitignorePath
} else {
    $existing = @()
}

$final = New-Object System.Collections.Generic.List[string]
foreach ($line in $existing) { $final.Add($line) }
foreach ($line in $gitignoreEntries) {
    if (-not ($final -contains $line)) {
        $final.Add($line)
    }
}
Write-Utf8NoBom -Path $gitignorePath -Text (($final -join [Environment]::NewLine) + [Environment]::NewLine)

# 9) Optional init_db.sql portability
if ($PatchInitDb) {
    $initPath = Join-Path $RepoPath "init_db.sql"
    if (Test-Path $initPath) {
        $init = Get-Content -Raw -Path $initPath
        $init = [regex]::Replace($init, "^\s*USE\s+telegrambot;\s*\r?\n", "", "IgnoreCase, Multiline")
        Write-Utf8NoBom -Path $initPath -Text $init
    }
}

Write-Host ""
Write-Host "Patch completed."
Write-Host "Changed files:"
Write-Host " - config.php"
Write-Host " - app\Config\database.php"
Write-Host " - index.php"
Write-Host " - app\Core\Router.php"
Write-Host " - app\Modules\Telegram\Services\TelegramService.php"
Write-Host " - telegram.php"
Write-Host " - .htaccess"
Write-Host " - .gitignore"
if ($PatchInitDb) { Write-Host " - init_db.sql (USE line removed)" }

Write-Host ""
Write-Host "Next: run git status and test locally before push."