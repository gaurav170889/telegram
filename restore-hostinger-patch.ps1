param(
    [string]$RepoPath = "C:\MAMP\htdocs\telegram",
    [string]$BackupPath = ""
)

$ErrorActionPreference = "Stop"

function Resolve-LatestBackup {
    param([string]$Root)
    $dirs = Get-ChildItem -Path $Root -Directory |
        Where-Object { $_.Name -like "backup_before_hostinger_patch_*" } |
        Sort-Object LastWriteTime -Descending
    if (-not $dirs -or $dirs.Count -eq 0) {
        throw "No backup_before_hostinger_patch_* folder found in $Root"
    }
    return $dirs[0].FullName
}

if (-not (Test-Path $RepoPath)) {
    throw "Repo path not found: $RepoPath"
}

if ([string]::IsNullOrWhiteSpace($BackupPath)) {
    $BackupPath = Resolve-LatestBackup -Root $RepoPath
}

if (-not (Test-Path $BackupPath)) {
    throw "Backup path not found: $BackupPath"
}

Write-Host "RepoPath   : $RepoPath"
Write-Host "BackupPath : $BackupPath"
Write-Host ""

# Files we want to restore if present in backup
$files = @(
    "config.php",
    "app\Config\database.php",
    "index.php",
    "app\Core\Router.php",
    "app\Modules\Telegram\Services\TelegramService.php",
    "telegram.php",
    ".htaccess",
    "init_db.sql",
    ".gitignore"
)

$restored = @()
$skipped = @()

foreach ($rel in $files) {
    $src = Join-Path $BackupPath $rel
    $dst = Join-Path $RepoPath $rel

    if (Test-Path $src) {
        $dstDir = Split-Path $dst -Parent
        if (-not (Test-Path $dstDir)) {
            New-Item -ItemType Directory -Path $dstDir | Out-Null
        }
        Copy-Item -Path $src -Destination $dst -Force
        $restored += $rel
    } else {
        $skipped += $rel
    }
}

# Optional cleanup: if .gitignore did not exist in backup but exists now, keep it.
# (No delete performed intentionally for safety.)

Write-Host "Restore completed."
Write-Host ""

if ($restored.Count -gt 0) {
    Write-Host "Restored files:"
    $restored | ForEach-Object { Write-Host " - $_" }
}

if ($skipped.Count -gt 0) {
    Write-Host ""
    Write-Host "Not found in backup (skipped):"
    $skipped | ForEach-Object { Write-Host " - $_" }
}

Write-Host ""
Write-Host "Next steps:"
Write-Host "  git status"
Write-Host "  git diff"