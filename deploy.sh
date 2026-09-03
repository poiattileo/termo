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

echo "→ Compactando projeto (index, api, admin, storage, print.php, install.php) ..."
rm -f /tmp/termo.zip
# compacta a partir da raiz do repo, excluindo .git e coisas desnecessárias
zip -r /tmp/termo.zip index.html print.php api admin storage data install.php -x "*.git*" "*.DS_Store" "data/*.db" "storage/prints/*.png" "storage/prints/*.jpg" 2>/dev/null || \
zip -r /tmp/termo.zip index.html print.php api admin storage data install.php

echo "→ Enviando para $DEST:/tmp/ ..."
scp /tmp/termo.zip "$DEST:/tmp/"

echo "→ Instalando em /var/www/html/termo na VM ..."
ssh "$DEST" "
  sudo mkdir -p /var/www/html/termo/data /var/www/html/termo/storage/prints && \
  sudo unzip -o /tmp/termo.zip -d /var/www/html/termo && \
  sudo chown -R www-data:www-data /var/www/html/termo && \
  sudo chmod -R 755 /var/www/html/termo && \
  sudo chmod -R 775 /var/www/html/termo/data /var/www/html/termo/storage && \
  echo '--- Arquivos ---' && ls -lh /var/www/html/termo/ && echo '--- API ---' && ls -lh /var/www/html/termo/api/ && echo '--- Admin ---' && ls -lh /var/www/html/termo/admin/ && \
  echo '--- CUPS ---' && lpstat -p HP_PeB 2>&1 || echo 'AVISO: impressora HP_PeB não encontrada - verifique CUPS' && \
  echo '--- Dependências ---' && php -v 2>&1 | head -n1 || echo 'AVISO: php não instalado (apt install php php-sqlite3)' && \
  which wkhtmltopdf 2>&1 || echo 'AVISO: wkhtmltopdf não instalado (apt install wkhtmltopdf) - opcional para foto server-side' && \
  echo '' && echo '→ Acesse http://IP/termo/install.php para inicializar o banco (admin/admin123)' 
"

echo "✔ Pronto! Acesse http://IP/termo/ e http://IP/termo/admin/login.php"
