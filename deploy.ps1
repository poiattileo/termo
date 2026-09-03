# Deploy via PowerShell (Windows -> VM Ubuntu)
param(
  [Parameter(Mandatory=$true)] [string]$Destino  # ex: jal@192.168.0.10
)

$ErrorActionPreference = "Stop"
$root = $PSScriptRoot

$zip = Join-Path $env:TEMP "termo.zip"
if (Test-Path $zip) { Remove-Item $zip -Force }

Write-Host "→ Compactando projeto..." -ForegroundColor Cyan
# lista de itens para deploy (raiz do repo)
$items = @(
  (Join-Path $root "index.html"),
  (Join-Path $root "print.php"),
  (Join-Path $root "install.php"),
  (Join-Path $root "api"),
  (Join-Path $root "admin"),
  (Join-Path $root "storage"),
  (Join-Path $root "data")
) | Where-Object { Test-Path $_ }

if (!(Test-Path (Join-Path $root "index.html"))) { Write-Error "Não achei index.html em $root"; exit 1 }

Compress-Archive -Path $items -DestinationPath $zip -Force
Write-Host "→ Zip criado em $zip" -ForegroundColor Cyan

Write-Host "→ Enviando para $Destino:/tmp/ ..." -ForegroundColor Cyan
scp $zip "${Destino}:/tmp/"

Write-Host "→ Instalando em /var/www/html/termo ..." -ForegroundColor Cyan
ssh $Destino @"
sudo mkdir -p /var/www/html/termo/data /var/www/html/termo/storage/prints && \
sudo unzip -o /tmp/termo.zip -d /var/www/html/termo && \
sudo chown -R www-data:www-data /var/www/html/termo && \
sudo chmod -R 755 /var/www/html/termo && \
sudo chmod -R 775 /var/www/html/termo/data /var/www/html/termo/storage && \
ls -lh /var/www/html/termo/ && echo '--- API ---' && ls -lh /var/www/html/termo/api/ && \
php -v 2>&1 | head -n1 || echo 'AVISO: php não instalado' && \
lpstat -p HP_PeB 2>&1 || echo 'AVISO: HP_PeB não encontrada' && \
which wkhtmltopdf 2>&1 || echo 'AVISO: instale wkhtmltopdf: sudo apt install wkhtmltopdf'
"@

Write-Host "✔ Pronto! Acesse http://IP/termo/ e http://IP/termo/admin/login.php (admin/admin123)" -ForegroundColor Green
Write-Host "  Depois acesse http://IP/termo/install.php para inicializar o banco." -ForegroundColor Yellow
