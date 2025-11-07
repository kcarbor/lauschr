<?php
// Zielverzeichnis (relativ zu dieser Datei)
$targetDir = __DIR__ . '/data';

// Verzeichnis sicherstellen
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0755, true);
}

$err = $ok = null;

// Upload behandeln
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['audio'])) {
    if (!is_uploaded_file($_FILES['audio']['tmp_name'])) {
        $err = 'No upload speficied.';
    } else {
        $name = basename($_FILES['audio']['name']);
        // simple whitelist für Audio-Endungen
        if (!preg_match('/\.(mp3|m4a|wav|ogg)$/i', $name)) {
            $err = 'Nur mp3/m4a/wav/ogg erlaubt.';
        } else {
            $dest = $targetDir . '/' . $name;
            if (move_uploaded_file($_FILES['audio']['tmp_name'], $dest)) {
                // Optional: Rechte knapp setzen
                @chmod($dest, 0644);
                $ok = "Upload ok: " . htmlspecialchars($name);
            } else {
                $err = 'Could not move file.';
            }
        }
    }
}

// Dateiliste
$files = [];
if (is_dir($targetDir)) {
    foreach (scandir($targetDir) as $f) {
        if ($f === '.' || $f === '..') continue;
        $path = $targetDir . '/' . $f;
        if (is_file($path)) {
            $files[] = [
                'name' => $f,
                'size' => filesize($path),
                'mtime'=> filemtime($path)
            ];
        }
    }
    usort($files, fn($a,$b)=> $b['mtime'] <=> $a['mtime']);
}
?>
<!doctype html>
<html lang="de">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>LauschR – Upload</title>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<style>
 body{font:16px/1.4 system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif;margin:2rem;max-width:900px}
 header{display:flex;align-items:center;gap:.5rem;margin-bottom:1.5rem}
 h1{font-size:1.4rem;margin:0}
 form{display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;margin-bottom:1rem}
 input[type=file]{max-width:400px}
 .msg{padding:.6rem .8rem;border-radius:.5rem;margin:.5rem 0}
 .ok{background:#e8fff2;border:1px solid #73d49c}
 .err{background:#fff1f1;border:1px solid #f06a6a}
 table{width:100%;border-collapse:collapse}
 th,td{padding:.5rem;border-bottom:1px solid #eee}
 th{text-align:left;font-weight:600}
 small{color:#666}
 code{background:#f6f8fa;padding:.2rem .35rem;border-radius:.3rem}
</style>
<header>
  <h1>LauschR – Upload</h1>
</header>

<?php if($ok): ?><div class="msg ok"><?=htmlspecialchars($ok)?></div><?php endif; ?>
<?php if($err): ?><div class="msg err"><?=htmlspecialchars($err)?></div><?php endif; ?>

<form method="post" enctype="multipart/form-data">
  <input type="file" name="audio" accept=".mp3,.m4a,.wav,.ogg" required>
  <button>Upload</button>
  <small>Target: <code>/data/</code> on this server</small>

</form>

<table>
  <thead><tr><th>File</th><th>Size</th><th>Modified</th></tr></thead>
  <tbody>
    <?php if(!$files): ?>
      <tr><td colspan="3"><em>No files yet.</em></td></tr>
    <?php else: foreach($files as $f): ?>
      <tr>
        <td><?=htmlspecialchars($f['name'])?></td>
        <td><?=number_format($f['size']/1024/1024,2)?> MB</td>
        <td><?=date('Y-m-d H:i', $f['mtime'])?></td>
      </tr>
    <?php endforeach; endif; ?>
  </tbody>
</table>
