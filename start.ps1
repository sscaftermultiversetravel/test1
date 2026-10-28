# 行動商城 - Windows 一鍵啟動（內建 PHP 與 MariaDB，不需另外安裝）
$ErrorActionPreference = 'Stop'
$root  = Split-Path -Parent $MyInvocation.MyCommand.Path
$php   = Join-Path $root 'php\php.exe'
$bin   = Join-Path $root 'mariadb\bin'
$data  = Join-Path $root 'data\db'
$flag  = Join-Path $root 'data\initialized'
$rootPwFile = Join-Path $root 'data\root.pw'
$dbPort  = 13307          # 避開電腦上可能已存在的 3306
$webPort = 8000
New-Item -ItemType Directory -Force -Path (Join-Path $root 'data') | Out-Null

function Wait-Db($pw) {
  for ($i = 0; $i -lt 60; $i++) {
    & "$bin\mariadb-admin.exe" --host=127.0.0.1 --port=$dbPort --user=root --password=$pw ping 2>$null | Out-Null
    if ($LASTEXITCODE -eq 0) { return $true }
    Start-Sleep -Seconds 1
  }
  return $false
}

Write-Host '=== 行動商城 ===' -ForegroundColor Cyan

# 1. 第一次執行：初始化資料庫目錄，設定隨機的 root 密碼
if (-not (Test-Path $rootPwFile)) {
  $rootPw = -join ((48..57) + (65..90) + (97..122) | Get-Random -Count 24 | ForEach-Object { [char]$_ })
  Write-Host '第一次執行，正在初始化資料庫（約 30 秒）...'
  & "$bin\mariadb-install-db.exe" --datadir="$data" --password="$rootPw" | Out-Null
  if ($LASTEXITCODE -ne 0) { throw '資料庫初始化失敗' }
  Set-Content -Path $rootPwFile -Value $rootPw -Encoding ASCII
}
$rootPw = (Get-Content $rootPwFile -Raw).Trim()

# 2. 啟動 MariaDB（只接受本機連線）
$db = Start-Process -FilePath "$bin\mariadbd.exe" -WindowStyle Hidden -PassThru -ArgumentList @(
  "--datadir=$data", "--port=$dbPort", '--bind-address=127.0.0.1',
  '--character-set-server=utf8mb4', '--collation-server=utf8mb4_unicode_ci', '--skip-name-resolve')
Write-Host '啟動資料庫...'
if (-not (Wait-Db $rootPw)) { throw '資料庫沒有在時間內啟動，請查看 data\db 內的 .err 記錄檔' }

try {
  # 3. 第一次：建庫、建表、建立帳號
  if (-not (Test-Path $flag)) {
    Write-Host '建立資料表...'
    & $php (Join-Path $root 'api\install.php') "--port=$dbPort" "--rootpw=$rootPw" "--out=$(Join-Path $root 'ADMIN_LOGIN.txt')"
    if ($LASTEXITCODE -ne 0) { throw '安裝失敗' }
    Set-Content -Path $flag -Value 'ok'
    Write-Host '管理員帳號與密碼已寫入 ADMIN_LOGIN.txt' -ForegroundColor Yellow
  }

  # 4. 啟動網站 + API（同一個埠）
  $url = "http://localhost:$webPort"
  Write-Host "網站已啟動：$url" -ForegroundColor Green
  Write-Host '請保持此視窗開啟；要結束請按 Ctrl+C 或關閉視窗。'
  Start-Process $url
  Push-Location $root
  & $php -S "127.0.0.1:$webPort" -t (Join-Path $root 'app') (Join-Path $root 'api\router.php')
  Pop-Location
}
finally {
  Write-Host '正在關閉資料庫...'
  & "$bin\mariadb-admin.exe" --host=127.0.0.1 --port=$dbPort --user=root --password=$rootPw shutdown 2>$null | Out-Null
  Start-Sleep -Seconds 2
  if (-not $db.HasExited) { Stop-Process -Id $db.Id -Force -ErrorAction SilentlyContinue }
}
