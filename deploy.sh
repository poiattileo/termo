#!/bin/bash
# Deploy rápido para VM Ubuntu em /var/www/html/termo
# Use: ./deploy.sh usuario@IP
set -e
DEST=${1:-}
if [ -z "$DEST" ]; then
  echo "Uso: ./deploy.sh usuario@IP-da-VM"
  echo "Ex:  ./deploy.sh jal@192.168.0.10"
  exit 1
fi

# Compacta conteúdo de ./termo (inclui print.php para impressão direta HP_PeB 600dpi)
echo "→ Compactando termo/index.html + termo/print.php ..."
rm -f /tmp/termo.zip
zip -j /tmp/termo.zip termo/index.html termo/print.php

echo "→ Enviando para $DEST:/tmp/ ..."
scp /tmp/termo.zip "$DEST:/tmp/"

echo "→ Instalando em /var/www/html/termo na VM ..."
ssh "$DEST" "sudo mkdir -p /var/www/html/termo && sudo unzip -o /tmp/termo.zip -d /var/www/html/termo && sudo chown -R www-data:www-data /var/www/html/termo && sudo chmod -R 755 /var/www/html/termo && ls -lh /var/www/html/termo/ && echo '--- CUPS ---' && lpstat -p HP_PeB 2>&1 || echo 'AVISO: impressora HP_PeB não encontrada - verifique CUPS' && echo '--- Dependências ---' && which wkhtmltopdf 2>&1 || echo 'AVISO: wkhtmltopdf não instalado (apt install wkhtmltopdf)'"

echo "✔ Pronto! Acesse http://IP/termo/"
