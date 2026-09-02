#!/usr/bin/env python3
"""
server.py - Alternativa ao print.php em Python/Flask
Imprime direto na HP_PeB a 600dpi via CUPS

Uso:
  pip install flask
  sudo apt install wkhtmltopdf cups
  python3 server.py  # roda em http://0.0.0.0:5000

Endpoint: POST /print  com JSON {"html":"<html>...</html>"}
  curl -X POST -H "Content-Type: application/json" -d '{"html":"<h1>teste</h1>"}' http://localhost:5000/print

Integra com index.html trocando fetch para http://IP:5000/print se preferir Flask ao invés de PHP
"""
from flask import Flask, request, jsonify
import subprocess, tempfile, os, re, shutil

app = Flask(__name__)

PRINTER = "HP_PeB"
DPI = "600"
DPI_OPT = "600dpi"

def has_cmd(cmd): return shutil.which(cmd) is not None

@app.route('/print', methods=['POST','OPTIONS'])
def do_print():
    if request.method == 'OPTIONS':
        return ('', 204)
    html = None
    pdf_blob = None
    # JSON?
    if request.is_json:
        j = request.get_json(force=True, silent=True) or {}
        html = j.get('html')
        # permite pdf_base64
        if j.get('pdf_base64'):
            import base64
            try: pdf_blob = base64.b64decode(j['pdf_base64'])
            except: pass
    # arquivo upload?
    if 'file' in request.files:
        pdf_blob = request.files['file'].read()
    if not html and not pdf_blob:
        # tenta form
        html = request.form.get('html')
        raw = request.get_data()
        if raw[:4] == b'%PDF':
            pdf_blob = raw
    if not html and not pdf_blob:
        return jsonify(ok=False, error='Nenhum html ou pdf recebido. Envie JSON {html:"..."}'), 400

    tmp_pdf = tempfile.mktemp(suffix='.pdf')
    tmp_html = tempfile.mktemp(suffix='.html')
    try:
        if pdf_blob:
            open(tmp_pdf,'wb').write(pdf_blob)
        else:
            open(tmp_html,'w',encoding='utf-8').write(html)
            converted=False
            if has_cmd('wkhtmltopdf'):
                cmd=['wkhtmltopdf','--enable-local-file-access','--page-size','A4','--margin-top','0','--margin-bottom','0','--margin-left','0','--margin-right','0','--dpi',DPI,'--print-media-type','--encoding','utf-8',tmp_html,tmp_pdf]
                r=subprocess.run(cmd,capture_output=True,text=True)
                if r.returncode==0 and os.path.exists(tmp_pdf) and os.path.getsize(tmp_pdf)>500:
                    converted=True
                else:
                    print('wkhtmltopdf fail',r.stdout,r.stderr)
            if not converted:
                # fallback: tenta chromium
                for ch in ['chromium-browser','chromium','google-chrome']:
                    if has_cmd(ch):
                        cmd=[ch,'--headless','--disable-gpu','--no-sandbox',f'--print-to-pdf={tmp_pdf}',tmp_html]
                        r=subprocess.run(cmd,capture_output=True,text=True)
                        if r.returncode==0 and os.path.exists(tmp_pdf):
                            converted=True; break
            if not converted:
                # imprime HTML direto
                tmp_pdf = tmp_html

        # verifica impressora
        lpstat = subprocess.run(['lpstat','-p',PRINTER],capture_output=True,text=True)
        if 'unknown' in lpstat.stdout.lower() or 'unknown' in lpstat.stderr.lower() or (lpstat.returncode!=0 and 'HP_PeB' not in lpstat.stdout):
            lst = subprocess.run(['lpstat','-p','-d'],capture_output=True,text=True)
            return jsonify(ok=False,error=f"Impressora '{PRINTER}' não encontrada",lpstat=lpstat.stdout+lpstat.stderr,disponiveis=lst.stdout+lst.stderr),500

        is_html = tmp_pdf.endswith('.html')
        if is_html:
            cmd=['lp','-d',PRINTER,'-o','media=A4','-o','fit-to-page','-o',f'printer-resolution={DPI_OPT}','-o',f'Resolution={DPI_OPT}','-o','document-format=text/html',tmp_pdf]
        else:
            cmd=['lp','-d',PRINTER,'-o','media=A4','-o','fit-to-page','-o',f'printer-resolution={DPI_OPT}','-o',f'Resolution={DPI_OPT}',tmp_pdf]
        r=subprocess.run(cmd,capture_output=True,text=True)
        out=r.stdout+r.stderr
        m=re.search(r'request id is (\S+)',out,re.I)
        job=m.group(1) if m else None
        # cleanup
        try: os.unlink(tmp_html)
        except: pass
        if not is_html:
            try: os.unlink(tmp_pdf)
            except: pass
        if r.returncode!=0:
            return jsonify(ok=False,error='CUPS lp falhou',output=out,cmd=' '.join(cmd)),500
        return jsonify(ok=True,printer=PRINTER,dpi=DPI,job=job,output=out)
    except Exception as e:
        try: os.unlink(tmp_html)
        except: pass
        try: os.unlink(tmp_pdf)
        except: pass
        return jsonify(ok=False,error=str(e)),500

@app.route('/')
def idx(): return 'OK - POST /print com {html} para imprimir na HP_PeB 600dpi'

if __name__=='__main__':
    app.run(host='0.0.0.0',port=5000)
