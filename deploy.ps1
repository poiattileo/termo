# Deploy via PowerShell (Windows -> VM Ubuntu)
param(
  [Parameter(Mandatory=$true)] [string]$Destino  # ex: jal@192.168.0.10
)

$ErrorActionPreference = "Stop"
$termoIdx = Join-Path $PSScriptRoot "termo\index.html"
if (!(Test-Path $termoIdx)) { Write-Error "Não achei termo\index.html em $PSScriptRoot"; exit 1 }

$zip = Join-Path $env:TEMP "termo.zip"
if (Test-Path $zip) { Remove-Item $zip -Force }
Compress-Archive -Path $termoIdx -DestinationPath $zip -Force
Write-Host "→ Zip criado em $zip" -ForegroundColor Cyan

Write-Host "→ Enviando para $Destino:/tmp/ ..." -ForegroundColor Cyan
scp $zip "${Destino}:/tmp/"

Write-Host "→ Instalando em /var/www/html/termo ..." -ForegroundColor Cyan
ssh $Destino "sudo mkdir -p /var/www/html/termo && sudo unzip -o /tmp/termo.zip -d /var/www/html/termo && sudo chown -R www-data:www-data /var/www/html/termo && sudo chmod -R 755 /var/www/html/termo && ls -lh /var/www/html/termo/"

Write-Host "✔ Pronto! Acesse http://IP/termo/" -ForegroundColor Green
