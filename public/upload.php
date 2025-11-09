<?php
/* =========================
 *  LauschR – Upload & Feed
 *  Einfache Admin-Seite zum Hochladen von Audio + Feed-Generierung
 *  (c) Kevin Baum – MIT License (wenn du willst)
 * ========================= */

// ---------- KONFIG ----------
$AUDIO_DIR   = __DIR__ . '/data/audio';                 // Serverpfad für Audio-Dateien
$PUBLIC_DIR  = __DIR__ . '/public';                     // Serverpfad für Feed/Index
$DB_PATH     = __DIR__ . '/episodes.json';              // „Mini-DB“ (JSON)

// Öffentlich erreichbare URLs:
$DATA_BASE_URL  = 'https://lauschr.de/raime-upload/data';    // ohne Slash am Ende
$PUBLIC_BASE_URL= 'https://lauschr.de/raime-upload/public';  // ohne Slash am Ende

// Feed-Metadaten:
$FEED_TITLE   = 'LauschR • RAIME';
$FEED_DESC    = 'Kurzfassungen & Audio-Memos für die Forschungsgruppe';
$FEED_LINK    = 'https://lauschr.de/raime-upload/';          // Webseite zum Feed
$FEED_LANG    = 'de-DE';
$FEED_AUTHOR  = 'Kevin Baum';
$FEED_OWNER   = ['name' => 'Kevin Baum', 'email' => 'mail@kevinbaum.de'];

// **Logo für Podcatcher** (möglichst quadratisch ≥1400x1400, PNG/JPG):
$FEED_IMAGE_URL = 'https://lauschr.de/raime-upload/logo.png';

// Maximale Upload-Größe (Server muss das auch erlauben: post_max_size, upload_max_filesize)
$MAX_BYTES = 200 * 1024 * 1024; // 200 MB

// ---------- INIT ----------
@mkdir($AUDIO_DIR, 0775, true);
@mkdir($PUBLIC_DIR, 0775, true);

$episodes = file_exists($DB_PATH)
  ? json_decode(file_get_contents($DB_PATH), true) : [];
if (!is_array($episodes)) $episodes = [];

// ---------- HELFER ----------
function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function build_feed(array $episodes, array $meta, string $outPath) {
  // $meta keys: title, desc, link, lang, author, owner[name,email], image_url, public_base_url
  $rss  = [];
  $rss[] = '<?xml version="1.0" encoding="UTF-8"?>';
  $rss[] = '<rss version="2.0"'
        .  ' xmlns:atom="http://www.w3.org/2005/Atom"'
        .  ' xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd">';
  $rss[] = '<channel>';

  // Basis
  $rss[] = '<title>'.h($meta['title']).'</title>';
  $rss[] = '<link>'.h($meta['link']).'</link>';
  $rss[] = '<language>'.h($meta['lang']).'</language>';
  $rss[] = '<description>'.h($meta['desc']).'</description>';
  $rss[] = '<atom:link href="'.h(rtrim($meta['public_base_url'],'/').'/podcasts.xml').'" rel="self" type="application/rss+xml" />';

  // iTunes-Infos
  $rss[] = '<itunes:author>'.h($meta['author']).'</itunes:author>';
  $rss[] = '<itunes:owner><itunes:name>'.h($meta['owner']['name']).'</itunes:name><itunes:email>'.h($meta['owner']['email']).'</itunes:email></itunes:owner>';
  $rss[] = '<itunes:explicit>false</itunes:explicit>';
  if (!empty($meta['image_url'])) {
    $rss[] = '<itunes:image href="'.h($meta['image_url']).'" />';
    // optional auch <image> für klassische Reader:
    $rss[] = '<image><url>'.h($meta['image_url']).'</url><title>'.h($meta['title']).'</title><link>'.h($meta['link']).'</link></image>';
  }

  foreach ($episodes as $e) {
    $enclosureType = $e['mime'] ?? 'audio/mpeg';
    $guid = !empty($e['guid']) ? $e['guid'] : sha1(($e['url'] ?? '') . ($e['pub_date_rfc'] ?? ''));
    $rss[] = '<item>';
    $rss[] = '  <title>'.h($e['title']).'</title>';
    if (!empty($e['summary'])) {
      $rss[] = '  <description>'.h($e['summary']).'</description>';
      $rss[] = '  <itunes:summary>'.h($e['summary']).'</itunes:summary>';
    }
    $rss[] = '  <pubDate>'.h($e['pub_date_rfc']).'</pubDate>';
    $rss[] = '  <guid isPermaLink="false">'.h($guid).'</guid>';
    $rss[] = '  <enclosure url="'.h($e['url']).'" length="'.intval($e['size']).'" type="'.h($enclosureType).'" />';
    if (!empty($e['author'])) {
      $rss[] = '  <author>'.h($e['author']).'</author>';
      $rss[] = '  <itunes:author>'.h($e['author']).'</itunes:author>';
    }
    if (!empty($e['duration'])) {
      // Dauer als HH:MM:SS oder MM:SS
      $rss[] = '  <itunes:duration>'.h($e['duration']).'</itunes:duration>';
    }
    $rss[] = '</item>';
  }

  $rss[] = '</channel>';
  $rss[] = '</rss>';

  file_put_contents($outPath, implode("\n", $rss));
}

function build_index(array $episodes, string $outPath, string $title, string $feedUrl) {
  ob_start(); ?>
<!doctype html>
<meta charset="utf-8">
<title><?= h($title) ?></title>
<link rel="alternate" type="application/rss+xml" title="<?= h($title) ?>" href="<?= h($feedUrl) ?>">
<style>
  body{font:16px/1.5 system-ui, -apple-system, Segoe UI, Roboto, sans-serif; max-width: 800px; margin: 2rem auto; padding: 0 1rem;}
  header{display:flex; align-items:center; gap:1rem; margin-bottom:1rem}
  header img{width:64px; height:64px; object-fit:cover; border-radius:8px}
  .epi{margin:1rem 0; padding:1rem; border:1px solid #ddd; border-radius:10px}
  .epi h3{margin:0 0 .25rem}
  .epi small{color:#666}
</style>
<header>
  <img src="<?= h(str_replace('podcasts.xml','../logo.png',$feedUrl)) ?>" alt="Logo">
  <div>
    <h1><?= h($title) ?></h1>
    <p><a href="<?= h($feedUrl) ?>">RSS/Podcast-Feed</a></p>
  </div>
</header>
<?php foreach ($episodes as $e): ?>
  <section class="epi">
    <h3><?= h($e['title']) ?></h3>
    <small><?= h($e['pub_date_rfc']) ?><?= !empty($e['duration']) ? ' • '.h($e['duration']) : '' ?></small>
    <?php if (!empty($e['summary'])): ?>
      <p><?= nl2br(h($e['summary'])) ?></p>
    <?php endif; ?>
    <audio controls preload="none" src="<?= h($e['url']) ?>" style="width:100%"></audio>
  </section>
<?php endforeach; ?>
<?php
  file_put_contents($outPath, ob_get_clean());
}

// ---------- POST: Upload ----------
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_upload'])) {
  // Felder
  $title   = trim($_POST['title'] ?? '');
  $author  = trim($_POST['author'] ?? $FEED_AUTHOR);
  $summary = trim($_POST['summary'] ?? '');
  $dateStr = trim($_POST['date'] ?? ''); // optional, z. B. 2025-11-07 09:30
  $duration= trim($_POST['duration'] ?? ''); // optional HH:MM:SS
  $explicit= !empty($_POST['explicit']); // (wir schreiben dennoch <itunes:explicit>false> auf Channel-Ebene)

  // Datei prüfen
  if (!isset($_FILES['audio']) || $_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
    $msg = 'Upload fehlgeschlagen.';
  } elseif ($_FILES['audio']['size'] > $MAX_BYTES) {
    $msg = 'Datei zu groß.';
  } else {
    $tmp  = $_FILES['audio']['tmp_name'];
    $orig = $_FILES['audio']['name'];

    // MIME/Endung
    $mime = function_exists('mime_content_type') ? mime_content_type($tmp) : '';
    $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    $ok   = [
      'mp3' => 'audio/mpeg',
      'm4a' => 'audio/mp4',
      'mp4' => 'audio/mp4',
      'aac' => 'audio/aac',
    ];
    $detected = $ok[$ext] ?? ($mime ?: 'audio/mpeg');
    if (!in_array($detected, $ok, true) && !in_array($ext, array_keys($ok), true)) {
      $msg = 'Nur MP3/M4A/MP4/AAC erlaubt.';
    } else {
      // Dateiname
      $date = $dateStr ? new DateTime($dateStr) : new DateTime('now');
      $slug = preg_replace('~[^a-z0-9]+~i', '-', $title ?: pathinfo($orig, PATHINFO_FILENAME));
      $slug = trim($slug, '-');
      if ($slug === '') $slug = 'episode';
      $fname = $slug . '-' . $date->format('Ymd-His') . '.' . $ext;

      // Ziel
      $dest = $AUDIO_DIR . '/' . $fname;
      if (!move_uploaded_file($tmp, $dest)) {
        $msg = 'Konnte Datei nicht speichern.';
      } else {
        // Metadaten
        $size = filesize($dest);
        $url  = rtrim($DATA_BASE_URL, '/') . '/audio/' . rawurlencode($fname);

        $episodes[] = [
          'title'        => $title ?: $fname,
          'author'       => $author,
          'summary'      => $summary,
          'url'          => $url,
          'size'         => $size,
          'mime'         => $detected,
          'pub_date_rfc' => $date->format(DateTime::RFC2822),
          'duration'     => $duration,
          'guid'         => sha1($url.$date->format('c')),
          'explicit'     => $explicit ? 'yes' : 'no',
        ];

        // Neueste zuerst
        usort($episodes, fn($a,$b) => strtotime($b['pub_date_rfc']) <=> strtotime($a['pub_date_rfc']));
        file_put_contents($DB_PATH, json_encode($episodes, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));

        // Feed + Index bauen
        build_feed(
          $episodes,
          [
            'title' => $FEED_TITLE,
            'desc'  => $FEED_DESC,
            'link'  => $FEED_LINK,
            'lang'  => $FEED_LANG,
            'author'=> $FEED_AUTHOR,
            'owner' => $FEED_OWNER,
            'image_url' => $FEED_IMAGE_URL,
            'public_base_url' => $PUBLIC_BASE_URL,
          ],
          $PUBLIC_DIR . '/podcasts.xml'
        );

        // Logo ins public kopieren? (nur falls du es bequem hier ablegen willst)
        // Wenn dein Logo bereits unter $FEED_IMAGE_URL liegt, brauchst du das nicht:
        // @copy(__DIR__ . '/logo.png', $PUBLIC_DIR . '/logo.png');

        build_index($episodes, $PUBLIC_DIR . '/index.html', $FEED_TITLE, rtrim($PUBLIC_BASE_URL,'/') . '/podcasts.xml');

        $msg = '✅ Upload & Feed-Update erfolgreich.';
      }
    }
  }
}

// ---------- UI ----------
?>
<!doctype html>
<meta charset="utf-8">
<title>LauschR Upload</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  body{font:16px/1.5 system-ui, -apple-system, Segoe UI, Roboto, sans-serif; max-width: 900px; margin: 2rem auto; padding: 0 1rem;}
  h1{margin:0 0 .5rem}
  form{border:1px solid #ddd; padding:1rem; border-radius:12px}
  label{display:block; margin:.5rem 0 .25rem}
  input[type="text"], input[type="datetime-local"], textarea{width:100%; padding:.6rem; border:1px solid #ccc; border-radius:8px}
  textarea{min-height:120px}
  .row{display:grid; grid-template-columns: 1fr 1fr; gap:1rem}
  .msg{margin:1rem 0; padding:.75rem 1rem; border-radius:8px; background:#f6f7ff}
  .episodes{margin-top:2rem}
  .epi{padding:.75rem 0; border-bottom:1px dashed #ddd}
  .epi small{color:#666}
  .hint{color:#666; font-size:.9rem}
</style>

<h1>LauschR – Admin Upload</h1>
<p class="hint">
  Feed: <a href="<?= h($PUBLIC_BASE_URL) ?>/podcasts.xml" target="_blank"><?= h($PUBLIC_BASE_URL) ?>/podcasts.xml</a><br>
  Übersicht: <a href="<?= h($PUBLIC_BASE_URL) ?>/index.html" target="_blank"><?= h($PUBLIC_BASE_URL) ?>/index.html</a>
</p>

<?php if ($msg): ?>
  <div class="msg"><?= h($msg) ?></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">
  <input type="hidden" name="do_upload" value="1">
  <label>Titel</label>
  <input type="text" name="title" placeholder="Episode-Titel" required>

  <div class="row">
    <div>
      <label>Autor</label>
      <input type="text" name="author" value="<?= h($FEED_AUTHOR) ?>">
    </div>
    <div>
      <label>Datum/Zeit (optional)</label>
      <input type="datetime-local" name="date">
    </div>
  </div>

  <div class="row">
    <div>
      <label>Dauer (optional, z. B. 18:22 oder 00:18:22)</label>
      <input type="text" name="duration" placeholder="MM:SS oder HH:MM:SS">
    </div>
    <div style="display:flex;align-items:end;gap:.5rem">
      <label>&nbsp;</label>
      <label><input type="checkbox" name="explicit" value="1"> Explizit?</label>
    </div>
  </div>

  <label>Zusammenfassung (optional)</label>
  <textarea name="summary" placeholder="Kurzbeschreibung / Shownotes ..."></textarea>

  <label>Audio-Datei (MP3/M4A/MP4/AAC)</label>
  <input type="file" name="audio" accept=".mp3,.m4a,.mp4,.aac,audio/*" required>

  <p class="hint">Max. Größe: <?= intval($MAX_BYTES / (1024*1024)) ?> MB.</p>

  <button type="submit" style="padding:.7rem 1rem; border-radius:10px; border:0; background:#333; color:#fff; cursor:pointer">
    Hochladen & Feed aktualisieren
  </button>
</form>

<div class="episodes">
  <h2>Letzte Episoden</h2>
  <?php foreach ($episodes as $e): ?>
    <div class="epi">
      <strong><?= h($e['title']) ?></strong>
      <small> • <?= h($e['pub_date_rfc']) ?><?= !empty($e['duration']) ? ' • '.h($e['duration']) : '' ?></small><br>
      <?php if (!empty($e['summary'])): ?>
        <div><?= nl2br(h($e['summary'])) ?></div>
      <?php endif; ?>
      <div><a href="<?= h($e['url']) ?>" target="_blank">Datei öffnen</a></div>
    </div>
  <?php endforeach; ?>
</div>
