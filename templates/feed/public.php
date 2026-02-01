<!DOCTYPE html>
<html lang="<?= htmlspecialchars($feed['language'] ?? 'de') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($feed['title']) ?></title>
    <meta name="description" content="<?= htmlspecialchars($feed['description'] ?? '') ?>">

    <!-- RSS Feed Discovery -->
    <link rel="alternate" type="application/rss+xml" title="<?= htmlspecialchars($feed['title']) ?>" href="<?= htmlspecialchars($view->url('/feed/' . $feed['slug'] . '/rss.xml')) ?>">

    <style>
        :root {
            --color-primary: #6366f1;
            --color-gray-50: #f8fafc;
            --color-gray-100: #f1f5f9;
            --color-gray-200: #e2e8f0;
            --color-gray-500: #64748b;
            --color-gray-600: #475569;
            --color-gray-700: #334155;
            --color-gray-900: #0f172a;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: var(--color-gray-900);
            background: var(--color-gray-50);
            margin: 0;
            padding: 2rem 1rem;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .cover {
            width: 200px;
            height: 200px;
            border-radius: 12px;
            object-fit: cover;
            background: var(--color-gray-200);
            flex-shrink: 0;
        }

        .cover-placeholder {
            width: 200px;
            height: 200px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--color-primary) 0%, #818cf8 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 5rem;
            flex-shrink: 0;
        }

        .info {
            flex: 1;
            min-width: 200px;
        }

        .title {
            margin: 0 0 0.5rem;
            font-size: 2rem;
        }

        .author {
            color: var(--color-gray-600);
            margin-bottom: 1rem;
        }

        .description {
            color: var(--color-gray-700);
            margin-bottom: 1.5rem;
        }

        .subscribe-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--color-primary);
            color: white;
            padding: 0.75rem 1.25rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
        }

        .subscribe-btn:hover {
            background: #4f46e5;
        }

        .rss-url {
            margin-top: 1rem;
            padding: 0.75rem;
            background: var(--color-gray-100);
            border-radius: 8px;
            font-size: 0.875rem;
            word-break: break-all;
        }

        .episodes {
            margin-top: 2rem;
        }

        .episodes-title {
            font-size: 1.25rem;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--color-gray-200);
        }

        .episode {
            background: white;
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .episode-title {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 0.25rem;
        }

        .episode-meta {
            color: var(--color-gray-500);
            font-size: 0.875rem;
            margin-bottom: 0.75rem;
        }

        .episode-description {
            color: var(--color-gray-600);
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .episode audio {
            width: 100%;
        }

        .footer {
            margin-top: 3rem;
            text-align: center;
            color: var(--color-gray-500);
            font-size: 0.875rem;
        }

        .footer a {
            color: var(--color-primary);
            text-decoration: none;
        }

        @media (max-width: 600px) {
            .header {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }

            .cover, .cover-placeholder {
                width: 150px;
                height: 150px;
            }

            .title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <?php if (!empty($feed['image'])): ?>
                <img src="<?= htmlspecialchars($view->url(ltrim($feed['image'], '/'))) ?>" alt="" class="cover">
            <?php else: ?>
                <div class="cover-placeholder">🎙️</div>
            <?php endif; ?>

            <div class="info">
                <h1 class="title"><?= htmlspecialchars($feed['title']) ?></h1>

                <?php if (!empty($feed['author'])): ?>
                    <p class="author">von <?= htmlspecialchars($feed['author']) ?></p>
                <?php endif; ?>

                <?php if (!empty($feed['description'])): ?>
                    <p class="description"><?= htmlspecialchars($feed['description']) ?></p>
                <?php endif; ?>

                <a href="<?= htmlspecialchars($view->url('/feed/' . $feed['slug'] . '/rss.xml')) ?>" class="subscribe-btn">
                    📡 RSS Feed abonnieren
                </a>

                <div class="rss-url">
                    <strong>Feed URL:</strong><br>
                    <?= htmlspecialchars($view->url('/feed/' . $feed['slug'] . '/rss.xml')) ?>
                </div>
            </div>
        </header>

        <section class="episodes">
            <h2 class="episodes-title"><?= count($feed['episodes'] ?? []) ?> Episoden</h2>

            <?php
            $episodes = $feed['episodes'] ?? [];
            usort($episodes, fn($a, $b) => strcmp($b['publish_date'] ?? '', $a['publish_date'] ?? ''));
            ?>

            <?php foreach ($episodes as $episode): ?>
                <?php if (($episode['status'] ?? 'published') !== 'published') continue; ?>
                <article class="episode">
                    <h3 class="episode-title"><?= htmlspecialchars($episode['title']) ?></h3>

                    <div class="episode-meta">
                        <?= date('d.m.Y', strtotime($episode['publish_date'] ?? $episode['created_at'])) ?>
                        <?php if (!empty($episode['duration'])): ?>
                            • <?= \LauschR\Models\Episode::formatDuration($episode['duration']) ?>
                        <?php endif; ?>
                        <?php if (!empty($episode['author'])): ?>
                            • <?= htmlspecialchars($episode['author']) ?>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($episode['description'])): ?>
                        <p class="episode-description"><?= htmlspecialchars($episode['description']) ?></p>
                    <?php endif; ?>

                    <?php if (!empty($episode['audio_url'])): ?>
                        <audio controls preload="none">
                            <source src="<?= htmlspecialchars($episode['audio_url']) ?>" type="<?= htmlspecialchars($episode['mime_type'] ?? 'audio/mpeg') ?>">
                            Ihr Browser unterstützt kein Audio.
                        </audio>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>

            <?php if (empty($episodes)): ?>
                <p style="text-align: center; color: var(--color-gray-500); padding: 2rem;">
                    Noch keine Episoden verfügbar.
                </p>
            <?php endif; ?>
        </section>

        <footer class="footer">
            <p>
                Powered by <a href="/">LauschR</a>
            </p>
        </footer>
    </div>
</body>
</html>
