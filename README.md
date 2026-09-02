# Gerador de Termo de Retirada - D.E. Jales

Site simples que facilita o preenchimento do **Termo de Retirada de Equipamento** (Unidade Regional de Ensino – Região de Jales).

Você preenche **NOME DA ESCOLA** e os **equipamentos** num formulário confortável e o site já mostra o documento idêntico ao `modelo.doc` original, pronto para **imprimir / salvar em PDF**.

> Roda 100% no navegador. Nada é enviado para servidor.

## ✨ O que facilita

- **NOME DA ESCOLA** com **busca inteligente** — digite `jose`, `maria`, `coripheu` e aparece dropdown com as **33 EEs da Diretoria de Jales** (filtra ignorando acento/maiúscula, `↑↓` + `Enter` pra selecionar). Pode digitar livre se não achar.
- Lista de **Equipamentos** com adicionar/remover linhas:
  - já vem pré-preenchido com `28x Tablets`, `2x Notebook Multilaser`, `2x Notebook Positivo` (igual ao modelo.doc) — é só editar
  - botão **＋ Adicionar linha**
  - atalhos rápidos
- **Campos de caneta**: `Responsável`, `Documento` e `Data` ficam **em branco** no termo para preencher à mão (como pediu)
- **Pré-visualização ao vivo** em tamanho A4, igualzinho ao Word
- Botão **Imprimir / Salvar em PDF** (usa o diálogo nativo do navegador)
- **Favicon** com brasão SP no topo do navegador

## 📁 Onde fica na VM

Na sua VM Ubuntu (Proxmox) com site de ramais em `/var/www/html`:

```
/var/www/html/
├── index.html        ← seu site de RAMAIS (não mexer!)
└── termo/
    └── index.html    ← novo gerador (este projeto - também em /index.html na raiz do repo)
```

Acesse depois por: **`http://IP-DA-VM/termo/`**

> O site de ramais continua intacto. O termo é uma subpasta.
> **Novo:** o `index.html` agora está também na **raiz do repo**, então `git clone https://github.com/poiattileo/termo.git /var/www/html/termo` já deixa o site pronto sem precisar copiar de `termo/termo`.

## 🚀 Como instalar na VM Ubuntu

### Opção 1 — Copiar via SCP (do Windows)

No **PowerShell do Windows** (na pasta do projeto `C:\Projetos\termo`):

```powershell
# compactar só a pasta termo
Compress-Archive -Path .\termo\* -DestinationPath termo.zip -Force

# enviar para a VM (troque USUARIO e IP)
scp termo.zip USUARIO@IP-DA-VM:/tmp/

# entrar na VM
ssh USUARIO@IP-DA-VM
```

Na **VM Ubuntu**:

```bash
sudo mkdir -p /var/www/html/termo
sudo unzip -o /tmp/termo.zip -d /var/www/html/termo/
sudo chown -R www-data:www-data /var/www/html/termo
sudo chmod -R 755 /var/www/html/termo

# testar
ls -lh /var/www/html/termo/
# deve mostrar index.html

# reiniciar apache/nginx se necessário
sudo systemctl reload apache2  # ou nginx
```

### Opção 2 — Copiar direto se já tem acesso à pasta

Se a VM monta pasta ou você está via `\\IP\www`:

```bash
sudo mkdir -p /var/www/html/termo
sudo cp index.html /var/www/html/termo/
sudo chown -R www-data:www-data /var/www/html/termo
```

### Opção 3 — Git / WinSCP

Pode arrastar a pasta `termo/` inteira com **WinSCP**, **FileZilla** ou **VS Code Remote SSH** para `/var/www/html/`.

## 🖨️ Como usar (impressão)

1. Acesse `http://IP/termo/`
2. Preencha **Escola** (use a busca) e **Equipamentos**
3. Clique **Imprimir / Salvar em PDF**
4. Na janela de impressão:
   - Destino: **Salvar como PDF** ou impressora
   - Layout: **Retrato** / **A4**
   - Margens: **Padrão** ou **Nenhuma**
   - **Desmarque** “Cabeçalhos e rodapés” para ficar igual ao modelo

Dica: `Responsável`, `Documento` e `Data` já ficam em branco para preencher à caneta no papel.

## 🛠️ Arquivos

```
index.html       ← site completo na raiz (deploy direto: git clone ... /var/www/html/termo)
termo/
└── index.html   ← cópia idêntica (compatibilidade - pode usar /termo/termo se já clonou antes)
modelo.doc       ← original de referência (não usado pelo site)
deploy.sh/.ps1   ← scripts de deploy (fora da pasta web, não expostos se usar /opt)
```

O `index.html` é **autocontido**: não precisa instalar nada, não precisa internet, não precisa PHP. O favicon já vem embutido (brasão SP em base64).

## 🔧 Personalizar

- **Brasão**: já está embutido em base64. Para trocar, substitua `data:image/png;base64,...` no topo do HTML.
- **Cabeçalho**: edite as linhas `SECRETARIA DE ESTADO...` dentro de `.paper-header`.
- **Rodapé**: assinatura fica em `.sig-wrap`.

## ❓ Dúvidas

- **Vai apagar meu site de ramais?** Não, se criar a subpasta `/termo`. Não copie por cima de `/var/www/html/index.html`.
- **Precisa de banco?** Não. O rascunho é salvo só no `localStorage` do navegador.
- **Funciona offline?** Sim, depois de carregado.

Feito para facilitar seu dia a dia na D.E. Jales. 💙
