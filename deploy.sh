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

# Compacta só o conteúdo de ./termo
echo "→ Compactando termo/index.html ..."
rm -f /tmp/termo.zip
zip -j /tmp/termo.zip termo/index.html

echo "→ Enviando para $DEST:/tmp/ ..."
scp /tmp/termo.zip "$DEST:/tmp/"

echo "→ Instalando em /var/www/html/termo na VM ..."
ssh "$DEST" "sudo mkdir -p /var/www/html/termo && sudo unzip -o /tmp/termo.zip -d /var/www/html/termo && sudo chown -R www-data:www-data /var/www/html/termo && sudo chmod -R 755 /var/www/html/termo && ls -lh /var/www/html/termo/"

echo "✔ Pronto! Acesse http://IP/termo/"
