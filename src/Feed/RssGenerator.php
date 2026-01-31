<?php
/**
 * RSS Feed Generator
 *
 * Generates valid RSS 2.0 feeds with iTunes podcast extensions.
 */

declare(strict_types=1);

namespace LauschR\Feed;

use LauschR\Core\App;
use LauschR\Models\Episode;

class RssGenerator
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = App::getInstance()->config('app.url');
    }

    /**
     * Generate RSS feed for a podcast feed
     */
    public function generate(array $feed): string
    {
        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->setIndentString('  ');

        $xml->startDocument('1.0', 'UTF-8');

        // RSS root element with namespaces
        $xml->startElement('rss');
        $xml->writeAttribute('version', '2.0');
        $xml->writeAttribute('xmlns:itunes', 'http://www.itunes.com/dtds/podcast-1.0.dtd');
        $xml->writeAttribute('xmlns:content', 'http://purl.org/rss/1.0/modules/content/');
        $xml->writeAttribute('xmlns:atom', 'http://www.w3.org/2005/Atom');

        $xml->startElement('channel');

        // Basic channel info
        $this->writeElement($xml, 'title', $feed['title'] ?? 'Untitled Feed');
        $this->writeElement($xml, 'description', $feed['description'] ?? '');
        $this->writeElement($xml, 'language', $feed['language'] ?? 'de');
        $this->writeElement($xml, 'link', $this->getFeedUrl($feed));
        $this->writeElement($xml, 'lastBuildDate', date('r'));
        $this->writeElement($xml, 'generator', 'LauschR');

        // Atom self link
        $xml->startElement('atom:link');
        $xml->writeAttribute('href', $this->getRssUrl($feed));
        $xml->writeAttribute('rel', 'self');
        $xml->writeAttribute('type', 'application/rss+xml');
        $xml->endElement();

        // iTunes specific elements
        $this->writeElement($xml, 'itunes:author', $feed['author'] ?? '');
        $this->writeElement($xml, 'itunes:summary', $feed['description'] ?? '');
        $this->writeElement($xml, 'itunes:explicit', ($feed['explicit'] ?? false) ? 'yes' : 'no');

        // iTunes owner
        if (!empty($feed['author']) || !empty($feed['email'])) {
            $xml->startElement('itunes:owner');
            $this->writeElement($xml, 'itunes:name', $feed['author'] ?? '');
            $this->writeElement($xml, 'itunes:email', $feed['email'] ?? '');
            $xml->endElement();
        }

        // Feed image
        if (!empty($feed['image'])) {
            $imageUrl = $this->resolveUrl($feed['image']);

            // Standard RSS image
            $xml->startElement('image');
            $this->writeElement($xml, 'url', $imageUrl);
            $this->writeElement($xml, 'title', $feed['title'] ?? '');
            $this->writeElement($xml, 'link', $this->getFeedUrl($feed));
            $xml->endElement();

            // iTunes image
            $xml->startElement('itunes:image');
            $xml->writeAttribute('href', $imageUrl);
            $xml->endElement();
        }

        // Category
        if (!empty($feed['category'])) {
            $xml->startElement('itunes:category');
            $xml->writeAttribute('text', $feed['category']);
            $xml->endElement();
        }

        // Episodes
        $episodes = $feed['episodes'] ?? [];

        // Sort episodes by publish_date descending
        usort($episodes, fn($a, $b) => strcmp($b['publish_date'] ?? '', $a['publish_date'] ?? ''));

        // Limit episodes
        $maxEpisodes = App::getInstance()->config('feed.max_episodes_in_feed', 100);
        $episodes = array_slice($episodes, 0, $maxEpisodes);

        foreach ($episodes as $episode) {
            if (($episode['status'] ?? 'published') !== 'published') {
                continue;
            }

            $this->writeEpisode($xml, $episode, $feed);
        }

        $xml->endElement(); // channel
        $xml->endElement(); // rss

        return $xml->outputMemory();
    }

    /**
     * Write an episode item to the feed
     */
    private function writeEpisode(\XMLWriter $xml, array $episode, array $feed): void
    {
        $xml->startElement('item');

        $this->writeElement($xml, 'title', $episode['title'] ?? 'Untitled');
        $this->writeElement($xml, 'description', $episode['description'] ?? '');
        $this->writeElement($xml, 'guid', $episode['guid'] ?? $episode['id']);

        // Publication date
        $pubDate = $episode['publish_date'] ?? $episode['created_at'] ?? date('c');
        if (strtotime($pubDate)) {
            $this->writeElement($xml, 'pubDate', date('r', strtotime($pubDate)));
        }

        // Author
        $author = $episode['author'] ?? $feed['author'] ?? '';
        if (!empty($author)) {
            $this->writeElement($xml, 'itunes:author', $author);
        }

        // Duration
        if (!empty($episode['duration'])) {
            $this->writeElement($xml, 'itunes:duration', Episode::formatDuration((int)$episode['duration']));
        }

        // Explicit
        $this->writeElement($xml, 'itunes:explicit', ($episode['explicit'] ?? false) ? 'yes' : 'no');

        // Enclosure (audio file)
        if (!empty($episode['audio_url'])) {
            $xml->startElement('enclosure');
            $xml->writeAttribute('url', $this->resolveUrl($episode['audio_url']));
            $xml->writeAttribute('length', (string)($episode['file_size'] ?? 0));
            $xml->writeAttribute('type', $episode['mime_type'] ?? 'audio/mpeg');
            $xml->endElement();
        }

        // iTunes summary (same as description)
        if (!empty($episode['description'])) {
            $this->writeElement($xml, 'itunes:summary', $episode['description']);
        }

        $xml->endElement(); // item
    }

    /**
     * Write an XML element with proper escaping
     */
    private function writeElement(\XMLWriter $xml, string $name, string $content): void
    {
        $xml->startElement($name);

        // Use CDATA for content that might contain HTML
        if (str_contains($content, '<') || str_contains($content, '&') || str_contains($content, ']]>')) {
            $xml->writeCdata($content);
        } else {
            $xml->text($content);
        }

        $xml->endElement();
    }

    /**
     * Get the public feed page URL
     */
    private function getFeedUrl(array $feed): string
    {
        $slug = $feed['slug'] ?? $feed['id'];
        return rtrim($this->baseUrl, '/') . '/feed/' . $slug;
    }

    /**
     * Get the RSS feed URL
     */
    private function getRssUrl(array $feed): string
    {
        $slug = $feed['slug'] ?? $feed['id'];
        return rtrim($this->baseUrl, '/') . '/feed/' . $slug . '/rss.xml';
    }

    /**
     * Resolve a URL (make absolute if relative)
     */
    private function resolveUrl(string $url): string
    {
        // Already absolute
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        // Make absolute
        return rtrim($this->baseUrl, '/') . '/' . ltrim($url, '/');
    }

    /**
     * Validate a generated RSS feed
     */
    public function validate(string $xml): array
    {
        $errors = [];
        $warnings = [];

        libxml_use_internal_errors(true);

        $doc = new \DOMDocument();
        if (!$doc->loadXML($xml)) {
            foreach (libxml_get_errors() as $error) {
                $errors[] = "Line {$error->line}: {$error->message}";
            }
            libxml_clear_errors();
            return ['valid' => false, 'errors' => $errors, 'warnings' => $warnings];
        }

        libxml_clear_errors();

        // Check required elements
        $xpath = new \DOMXPath($doc);
        $xpath->registerNamespace('itunes', 'http://www.itunes.com/dtds/podcast-1.0.dtd');

        // Check channel title
        if (!$xpath->query('//channel/title')->length) {
            $errors[] = 'Missing channel title';
        }

        // Check for at least one item
        if (!$xpath->query('//channel/item')->length) {
            $warnings[] = 'Feed has no episodes';
        }

        // Check each item has required elements
        $items = $xpath->query('//channel/item');
        foreach ($items as $i => $item) {
            $itemNum = $i + 1;

            if (!$xpath->query('title', $item)->length) {
                $errors[] = "Episode {$itemNum} is missing a title";
            }

            if (!$xpath->query('enclosure', $item)->length) {
                $warnings[] = "Episode {$itemNum} has no audio enclosure";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Get iTunes category list
     */
    public static function getCategories(): array
    {
        return [
            'Arts' => [
                'Books', 'Design', 'Fashion & Beauty', 'Food', 'Performing Arts', 'Visual Arts'
            ],
            'Business' => [
                'Careers', 'Entrepreneurship', 'Investing', 'Management', 'Marketing', 'Non-Profit'
            ],
            'Comedy' => [
                'Comedy Interviews', 'Improv', 'Stand-Up'
            ],
            'Education' => [
                'Courses', 'How To', 'Language Learning', 'Self-Improvement'
            ],
            'Fiction' => [
                'Comedy Fiction', 'Drama', 'Science Fiction'
            ],
            'Government' => [],
            'History' => [],
            'Health & Fitness' => [
                'Alternative Health', 'Fitness', 'Medicine', 'Mental Health', 'Nutrition', 'Sexuality'
            ],
            'Kids & Family' => [
                'Education for Kids', 'Parenting', 'Pets & Animals', 'Stories for Kids'
            ],
            'Leisure' => [
                'Animation & Manga', 'Automotive', 'Aviation', 'Crafts', 'Games', 'Hobbies', 'Home & Garden', 'Video Games'
            ],
            'Music' => [
                'Music Commentary', 'Music History', 'Music Interviews'
            ],
            'News' => [
                'Business News', 'Daily News', 'Entertainment News', 'News Commentary', 'Politics', 'Sports News', 'Tech News'
            ],
            'Religion & Spirituality' => [
                'Buddhism', 'Christianity', 'Hinduism', 'Islam', 'Judaism', 'Religion', 'Spirituality'
            ],
            'Science' => [
                'Astronomy', 'Chemistry', 'Earth Sciences', 'Life Sciences', 'Mathematics', 'Natural Sciences', 'Nature', 'Physics', 'Social Sciences'
            ],
            'Society & Culture' => [
                'Documentary', 'Personal Journals', 'Philosophy', 'Places & Travel', 'Relationships'
            ],
            'Sports' => [
                'Baseball', 'Basketball', 'Cricket', 'Fantasy Sports', 'Football', 'Golf', 'Hockey', 'Rugby', 'Running', 'Soccer', 'Swimming', 'Tennis', 'Volleyball', 'Wilderness', 'Wrestling'
            ],
            'Technology' => [],
            'True Crime' => [],
            'TV & Film' => [
                'After Shows', 'Film History', 'Film Interviews', 'Film Reviews', 'TV Reviews'
            ],
        ];
    }
}
