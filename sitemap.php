<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/data.php';

$base = rtrim(SITE_URL, '/');
$hanzi = array_keys(manifest()['hanzi_to_shard'] ?? []);

header('Content-Type: application/xml; charset=UTF-8');
header('Cache-Control: public, max-age=3600');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
echo '<url><loc>' . h($base . '/') . '</loc></url>' . "\n";
echo '<url><loc>' . h($base . '/about') . '</loc></url>' . "\n";
foreach ($hanzi as $ch) {
    echo '<url><loc>' . h($base . '/x-' . rawurlencode($ch) . '/') . '</loc></url>' . "\n";
}
echo '</urlset>' . "\n";
