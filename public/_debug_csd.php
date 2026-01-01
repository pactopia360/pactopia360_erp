<?php
declare(strict_types=1);

/**
 * Debug rápido para validar CSD/FIEL sin OpenSSL CLI (Windows/WAMP).
 *
 * USO:
 * 1) Abre en navegador: http://localhost:8000/_debug_csd.php
 * 2) Sube .cer + .key + password
 * 3) Te dirá:
 *    - Si puede abrir la llave privada (password ok)
 *    - Si el par cer/key coincide (mismo modulus)
 *
 * IMPORTANTE: borrar este archivo al terminar.
 */

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass = (string)($_POST['pass'] ?? '');
    $passTrim = trim($pass);

    $cerTmp = $_FILES['cer']['tmp_name'] ?? '';
    $keyTmp = $_FILES['key']['tmp_name'] ?? '';

    $out = [
        'pass_len' => strlen($pass),
        'pass_len_trim' => strlen($passTrim),
        'cer_uploaded' => is_file($cerTmp),
        'key_uploaded' => is_file($keyTmp),
        'php_version' => PHP_VERSION,
        'openssl_version' => defined('OPENSSL_VERSION_TEXT') ? OPENSSL_VERSION_TEXT : 'N/A',
        'errors' => [],
        'cert' => [],
        'key' => [],
        'pair' => [],
    ];

    if (!is_file($cerTmp) || !is_file($keyTmp)) {
        $out['errors'][] = 'Faltan archivos .cer o .key';
        $result = $out;
    } else {
        $cerData = file_get_contents($cerTmp);
        $keyData = file_get_contents($keyTmp);

        // ---- CERT ----
        $certRes = @openssl_x509_read($cerData);
        if (!$certRes) {
            while ($e = openssl_error_string()) $out['errors'][] = "[CERT] $e";
        } else {
            $certInfo = openssl_x509_parse($certRes) ?: [];
            $out['cert']['subject'] = $certInfo['subject'] ?? null;
            $out['cert']['issuer']  = $certInfo['issuer'] ?? null;
            $out['cert']['serial']  = $certInfo['serialNumberHex'] ?? ($certInfo['serialNumber'] ?? null);
            $out['cert']['validFrom'] = $certInfo['validFrom_time_t'] ?? null;
            $out['cert']['validTo']   = $certInfo['validTo_time_t'] ?? null;

            $certPub = openssl_pkey_get_public($certRes);
            if (!$certPub) {
                while ($e = openssl_error_string()) $out['errors'][] = "[CERT-PUB] $e";
            } else {
                $pubDet = openssl_pkey_get_details($certPub) ?: [];
                $out['cert']['type'] = $pubDet['type'] ?? null;
                if (!empty($pubDet['rsa']['n'])) {
                    $out['cert']['mod_md5'] = md5($pubDet['rsa']['n']);
                } else {
                    $out['cert']['mod_md5'] = null;
                    $out['errors'][] = '[CERT] No pude extraer módulo RSA (¿no es RSA?)';
                }
            }
        }

        // ---- KEY (INTENTO 1: directo) ----
        $keyRes = @openssl_pkey_get_private($keyData, $passTrim);
        if (!$keyRes) {
            while ($e = openssl_error_string()) $out['errors'][] = "[KEY] $e";
        } else {
            $keyDet = openssl_pkey_get_details($keyRes) ?: [];
            $out['key']['type'] = $keyDet['type'] ?? null;
            if (!empty($keyDet['rsa']['n'])) {
                $out['key']['mod_md5'] = md5($keyDet['rsa']['n']);
            } else {
                $out['key']['mod_md5'] = null;
                $out['errors'][] = '[KEY] No pude extraer módulo RSA (¿no es RSA?)';
            }
        }

        // ---- Pair check ----
        if (!empty($out['cert']['mod_md5']) && !empty($out['key']['mod_md5'])) {
            $out['pair']['match'] = ($out['cert']['mod_md5'] === $out['key']['mod_md5']);
            $out['pair']['message'] = $out['pair']['match']
                ? 'OK: .cer y .key sí corresponden (mismo par).'
                : 'ERROR: .cer y .key NO corresponden (par distinto: CSD vs FIEL o llave diferente).';
        } else {
            $out['pair']['match'] = null;
            $out['pair']['message'] = 'No se pudo comparar el par (faltó módulo RSA en cert o key).';
        }

        $result = $out;
    }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Debug CSD</title>
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial; padding:24px; max-width:900px; margin:auto;}
    .card{border:1px solid #ddd; border-radius:12px; padding:16px; margin:12px 0;}
    .row{display:flex; gap:12px; flex-wrap:wrap;}
    label{display:block; font-weight:600; margin:10px 0 6px;}
    input{padding:10px; width:100%; max-width:520px;}
    button{padding:10px 14px; border-radius:10px; border:1px solid #111; background:#111; color:#fff; cursor:pointer;}
    pre{white-space:pre-wrap; word-break:break-word; background:#0b1020; color:#dbeafe; padding:12px; border-radius:10px;}
    .warn{color:#b45309;}
  </style>
</head>
<body>
  <h1>Debug CSD/FIEL (sin OpenSSL CLI)</h1>
  <p class="warn"><b>IMPORTANTE:</b> borra <code>public/_debug_csd.php</code> cuando termines.</p>

  <div class="card">
    <form method="post" enctype="multipart/form-data">
      <div class="row">
        <div style="flex:1">
          <label>.cer</label>
          <input type="file" name="cer" accept=".cer" required>
        </div>
        <div style="flex:1">
          <label>.key</label>
          <input type="file" name="key" accept=".key" required>
        </div>
      </div>
      <label>Contraseña</label>
      <input type="password" name="pass" autocomplete="off" required>
      <div style="margin-top:14px">
        <button type="submit">Validar</button>
      </div>
    </form>
  </div>

  <?php if ($result): ?>
    <div class="card">
      <h2>Resultado</h2>
      <pre><?= h(json_encode($result, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre>
    </div>
  <?php endif; ?>
</body>
</html>
