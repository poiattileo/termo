# Deploy via PowerShell (Windows -> VM Ubuntu)
param(
  [Parameter(Mandatory=$true)] [string]$Destino  # ex: jal@192.168.0.10
)

$ErrorActionPreference = "Stop"
$termoIdx = Join-Path $PSScriptRoot "termo\index.html"
$termoPrint = Join-Path $PSScriptRoot "termo\print.php"
if (!(Test-Path $termoIdx)) { Write-Error "Não achei termo\index.html em $PSScriptRoot"; exit 1 }
if (!(Test-Path $termoPrint)) { Write-Warning "AVISO: termo\print.php não encontrado - impressão direta HP_PeB não vai funcionar" }

$zip = Join-Path $env:TEMP "termo.zip"
if (Test-Path $zip) { Remove-Item $zip -Force }
# inclui index.html + print.php
$files = @($termoIdx)
if (Test-Path $termoPrint) { $files += $termoPrint }
Compress-Archive -Path $files -DestinationPath $zip -Force
Write-Host "→ Zip criado em $zip" -ForegroundColor Cyan

Write-Host "→ Enviando para $Destino:/tmp/ ..." -ForegroundColor Cyan
scp $zip "${Destino}:/tmp/"

Write-Host "→ Instalando em /var/www/html/termo ..." -ForegroundColor Cyan
ssh $Destino "sudo mkdir -p /var/www/html/termo && sudo unzip -o /tmp/termo.zip -d /var/www/html/termo && sudo chown -R www-data:www-data /var/www/html/termo && sudo chmod -R 755 /var/www/html/termo && ls -lh /var/www/html/termo/ && echo '--- CUPS ---' && lpstat -p HP_PeB 2>&1 || echo 'AVISO: HP_PeB não encontrada' && which wkhtmltopdf 2>&1 || echo 'AVISO: instale wkhtmltopdf: sudo apt install wkhtmltopdf'"

Write-Host "✔ Pronto! Acesse http://IP/termo/" -ForegroundColor Green
