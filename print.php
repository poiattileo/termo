<?php
// print.php - Impressão direta na HP_PeB a 600dpi via CUPS
// Coloque este arquivo em /var/www/html/termo/print.php
// Requisitos no Ubuntu servidor:
//   sudo apt update && sudo apt install -y cups wkhtmltopdf php
//   sudo lpstat -p HP_PeB   # verifica se impressora existe
//   sudo usermod -a -G lpadmin www-data
//   sudo systemctl restart apache2  (ou nginx/php-fpm)
//   sudo lpoptions -p HP_PeB -l  # lista opções de dpi/resolução suportadas

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok'=>false,'error'=>'Use POST']);
    exit;
}

// --- Config ---
$PRINTER = 'HP_PeB';
$DPI = '600'; // 600dpi
$PRINTER_DPI_OPT = '600dpi'; // para -o printer-resolution e -o Resolution

// Opcional: restringir por IP (descomente se quiser só rede interna)
// $allow = ['127.0.0.1','::1','192.168.0.','10.0.0.'];
// $ip = $_SERVER['REMOTE_ADDR'] ?? '';
// $okIp = false; foreach($allow as $a){ if(strpos($ip,$a)===0) $okIp=true; }
// if(!$okIp){ http_response_code(403); echo json_encode(['ok'=>false,'error'=>"IP não autorizado: $ip"]); exit; }

// --- Lê entrada: JSON {html:"..."} OU multipart com arquivo PDF OU raw PDF ---
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
$html = null;
$pdfBlob = null;
$isPdfUpload = false;

// 1) upload de arquivo via $_FILES
if (!empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
    $pdfBlob = file_get_contents($_FILES['file']['tmp_name']);
    $isPdfUpload = true;
}
// 2) JSON com html ou pdf base64
else if (is_array($data)) {
    if (!empty($data['html'])) $html = $data['html'];
    if (!empty($data['pdf_base64'])) { $pdfBlob = base64_decode($data['pdf_base64']); $isPdfUpload = true; }
    // permite sobrescrever impressora/dpi via JSON se quiser
    if (!empty($data['printer'])) $PRINTER = preg_replace('/[^A-Za-z0-9_\-]/','',$data['printer']);
    if (!empty($data['dpi'])) $DPI = preg_replace('/[^0-9]/','',$data['dpi']);
}
// 3) raw PDF (Content-Type: application/pdf)
else if (substr($raw,0,4) === '%PDF') {
    $pdfBlob = $raw;
    $isPdfUpload = true;
}
// 4) form-urlencoded html
else if (!empty($_POST['html'])) {
    $html = $_POST['html'];
}

if (!$html && !$pdfBlob) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'Nenhum html ou pdf recebido. Envie JSON {html:"..."} ou arquivo PDF.','hint'=>'Ex: curl -X POST -H "Content-Type: application/json" -d \'{"html":"<h1>teste</h1>"}\' http://IP/termo/print.php']);
    exit;
}

// Cria temporários
$tmpDir = sys_get_temp_dir();
$tmpPdf = tempnam($tmpDir, 'termo_') . '.pdf';
$tmpHtml = tempnam($tmpDir, 'termo_') . '.html';

try {
    // Se já veio PDF, salva direto
    if ($isPdfUpload && $pdfBlob) {
        file_put_contents($tmpPdf, $pdfBlob);
    } else {
        // Salva HTML e converte para PDF com wkhtmltopdf (mantém layout fiel a 600dpi)
        file_put_contents($tmpHtml, $html);

        // Tenta wkhtmltopdf se existir
        $hasWk = trim(shell_exec('which wkhtmltopdf 2>/dev/null'));
        $hasChromium = trim(shell_exec('which chromium-browser 2>/dev/null || which chromium 2>/dev/null || which google-chrome 2>/dev/null'));

        $converted = false;
        if ($hasWk) {
            // wkhtmltopdf com 600dpi e A4 sem margens extras (o HTML já tem padding)
            $cmd = sprintf(
                'wkhtmltopdf --enable-local-file-access --page-size A4 --margin-top 0 --margin-bottom 0 --margin-left 0 --margin-right 0 --dpi %s --print-media-type --encoding utf-8 %s %s 2>&1',
                escapeshellarg($DPI),
                escapeshellarg($tmpHtml),
                escapeshellarg($tmpPdf)
            );
            exec($cmd, $outWk, $retWk);
            if ($retWk === 0 && file_exists($tmpPdf) && filesize($tmpPdf) > 500) {
                $converted = true;
            } else {
                error_log("wkhtmltopdf falhou: " . implode("\n",$outWk) . " cmd=$cmd");
            }
        }

        // Fallback: chromium headless se wkhtmltopdf não disponível
        if (!$converted && $hasChromium) {
            $cmd = sprintf(
                '%s --headless --disable-gpu --no-sandbox --print-to-pdf=%s --print-to-pdf-no-header %s 2>&1',
                escapeshellarg($hasChromium),
                escapeshellarg($tmpPdf),
                escapeshellarg($tmpHtml)
            );
            exec($cmd, $outCh, $retCh);
            if ($retCh === 0 && file_exists($tmpPdf) && filesize($tmpPdf) > 500) $converted = true;
            else error_log("chromium print falhou: ".implode("\n",$outCh));
        }

        // Último fallback: imprime HTML direto via CUPS (pode perder fidelidade mas funciona)
        if (!$converted) {
            // Vamos tentar imprimir o HTML direto, sem PDF
            $tmpPdf = $tmpHtml; // reaproveita
            // marca que é HTML para lp usar mime correto
        }
    }

    // Verifica impressora existe
    $lpstat = shell_exec('lpstat -p '.escapeshellarg($PRINTER).' 2>&1');
    if (stripos($lpstat, 'unknown') !== false || stripos($lpstat, 'inexistente') !== false || trim($lpstat)==='') {
        // tenta listar impressoras disponíveis para debug
        $list = shell_exec('lpstat -p -d 2>&1');
        http_response_code(500);
        echo json_encode(['ok'=>false,'error'=>"Impressora '$PRINTER' não encontrada no CUPS.",'lpstat'=>$lpstat,'disponiveis'=>$list,'hint'=>"Rode no servidor: lpstat -p -d && lpoptions -p $PRINTER -l"]);
        @unlink($tmpHtml); @unlink($tmpPdf);
        exit;
    }

    // Monta comando lp com 600dpi
    // -o printer-resolution=600dpi é padrão IPP, -o Resolution=600dpi é alias HP, -o media=A4 garante tamanho
    $isHtmlDirect = (substr($tmpPdf,-5)==='.html');
    $fileToPrint = $tmpPdf;
    $cmdLp = sprintf(
        'lp -d %s -o media=A4 -o fit-to-page -o printer-resolution=%s -o Resolution=%s %s 2>&1',
        escapeshellarg($PRINTER),
        escapeshellarg($PRINTER_DPI_OPT),
        escapeshellarg($PRINTER_DPI_OPT),
        escapeshellarg($fileToPrint)
    );
    // Se HTML direto, força tipo
    if ($isHtmlDirect) {
        $cmdLp = sprintf(
            'lp -d %s -o media=A4 -o fit-to-page -o printer-resolution=%s -o Resolution=%s -o document-format=text/html %s 2>&1',
            escapeshellarg($PRINTER),
            escapeshellarg($PRINTER_DPI_OPT),
            escapeshellarg($PRINTER_DPI_OPT),
            escapeshellarg($fileToPrint)
        );
    }

    exec($cmdLp, $outLp, $retLp);
    $lpOutput = implode("\n", $outLp);

    // Limpa temporários
    @unlink($tmpHtml);
    // se não era html direto, tmpPdf é pdf e tmpHtml já apagado; se era html direto, tmpPdf == tmpHtml então já apagado
    if (!$isHtmlDirect) @unlink($tmpPdf);

    if ($retLp !== 0) {
        http_response_code(500);
        echo json_encode(['ok'=>false,'error'=>'CUPS lp falhou','cmd'=>$cmdLp,'output'=>$lpOutput,'hint'=>'Verifique: sudo lpstat -p HP_PeB ; sudo tail -n 50 /var/log/cups/error_log']);
        exit;
    }

    // Extrai job id se possível (ex: request id is HP_PeB-123)
    $jobId = null;
    if (preg_match('/request id is (\S+)/i', $lpOutput, $m)) $jobId = $m[1];

    echo json_encode(['ok'=>true,'printer'=>$PRINTER,'dpi'=>$DPI,'job'=>$jobId,'output'=>$lpOutput]);

} catch (Throwable $e) {
    @unlink($tmpHtml); @unlink($tmpPdf);
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
