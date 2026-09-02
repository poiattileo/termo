# Gerador de Termo de Retirada - D.E. Jales

Site simples que facilita o preenchimento do **Termo de Retirada de Equipamento** (Unidade Regional de Ensino – Região de Jales).

Você preenche **NOME DA ESCOLA** e os **equipamentos** num formulário confortável e o site já mostra o documento idêntico ao `modelo.doc` original, pronto para **imprimir / salvar em PDF**.

> Roda 100% no navegador. Nada é enviado para servidor.

## ✨ O que facilita

- Campo dedicado para **NOME DA ESCOLA** (já sai em CAIXA ALTA e em destaque no termo)
- Campo para **Responsável** (depois do “Eu,”) e **RG/CPF**
- **Data de Retirada** com calendário (formato DD/MM/AAAA no papel)
- Lista de **Equipamentos** com adicionar/remover linhas:
  - já vem pré-preenchido com `28x Tablets`, `2x Notebook Multilaser`, `2x Notebook Positivo` (igual ao modelo.doc) — é só editar
  - botão **＋ Adicionar linha**
  - atalhos rápidos
- **Pré-visualização ao vivo** em tamanho A4, igualzinho ao Word
- Botão **Imprimir / Salvar em PDF** (usa o diálogo nativo do navegador)

## 📁 Onde fica na VM

Na sua VM Ubuntu (Proxmox) com site de ramais em `/var/www/html`:

```
/var/www/html/
├── index.html        ← seu site de RAMAIS (não mexer!)
└── termo/
    └── index.html    ← novo gerador (este projeto)
```

Acesse depois por: **`http://IP-DA-VM/termo/`**

> O site de ramais continua intacto. O termo é uma subpasta.

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
2. Preencha Escola, Responsável, Data e Equipamentos
3. Clique **Imprimir / Salvar em PDF**
4. Na janela de impressão:
   - Destino: **Salvar como PDF** ou impressora
   - Layout: **Retrato** / **A4**
   - Margens: **Padrão** ou **Nenhuma**
   - **Desmarque** “Cabeçalhos e rodapés” para ficar igual ao modelo

Dica: deixe campos em branco se preferir completar à caneta — a linha fica para assinar.

## 🛠️ Arquivos

```
termo/
└── index.html   ← site completo (HTML+CSS+JS+brasão embutido, sem dependências)
modelo.doc       ← original de referência (não usado pelo site)
```

O `index.html` é **autocontido**: não precisa instalar nada, não precisa internet, não precisa PHP.

## 🔧 Personalizar

- **Brasão**: já está embutido em base64. Para trocar, substitua `data:image/png;base64,...` no topo do HTML.
- **Cabeçalho**: edite as linhas `SECRETARIA DE ESTADO...` dentro de `.paper-header`.
- **Rodapé**: assinatura fica em `.sig-wrap`.

## ❓ Dúvidas

- **Vai apagar meu site de ramais?** Não, se criar a subpasta `/termo`. Não copie por cima de `/var/www/html/index.html`.
- **Precisa de banco?** Não. O rascunho é salvo só no `localStorage` do navegador.
- **Funciona offline?** Sim, depois de carregado.

Feito para facilitar seu dia a dia na D.E. Jales. 💙
