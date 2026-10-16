@echo off
echo =========================================================
echo   Generando paquete ContaPro_cPanel_Deploy.zip (Linux)
echo =========================================================
powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "Add-Type -AssemblyName System.IO.Compression, System.IO.Compression.FileSystem; " ^
  "$src = '%~dp0'.TrimEnd('\'); " ^
  "$zipPath = \"$src\ContaPro_cPanel_Deploy.zip\"; " ^
  "if (Test-Path $zipPath) { Remove-Item $zipPath -Force }; " ^
  "$zip = [System.IO.Compression.ZipFile]::Open($zipPath, [System.IO.Compression.ZipArchiveMode]::Create); " ^
  "$files = Get-ChildItem -Path $src -Recurse -File -Force | Where-Object { $_.FullName -notmatch '\\\.well-known\\' -and $_.Name -notin @('ContaPro_cPanel_Deploy.zip', 'activar_dominio_local.bat', 'generar_paquete_cpanel.bat') }; " ^
  "foreach ($f in $files) { " ^
  "  $rel = $f.FullName.Substring($src.Length + 1).Replace('\', '/'); " ^
  "  [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $f.FullName, $rel, [System.IO.Compression.CompressionLevel]::Optimal) | Out-Null " ^
  "}; " ^
  "$zip.Dispose(); " ^
  "Write-Host 'Paquete ContaPro_cPanel_Deploy.zip actualizado con exito!' -ForegroundColor Green"
pause
