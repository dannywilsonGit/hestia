<?php
declare(strict_types=1);

namespace Hestia\Infrastructure\Service;

use Hestia\Domain\Service\TextExtractor;

final class LocalTextExtractor implements TextExtractor
{
    // Taille max du preview (caractères)
    public function __construct(private int $maxChars = 2000) {}

    public function extractPreview(string $path): ?array
    {
        if (!is_file($path)) {
            return ['status' => 'failed', 'mime' => 'unknown', 'preview' => ''];
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        // MVP: txt + md uniquement
        if (!in_array($ext, ['txt', 'md'], true)) {
            return null; // non supporté pour l’instant
        }

        $mime = $ext === 'md' ? 'text/markdown' : 'text/plain';

        $content = @file_get_contents($path);
        if ($content === false) {
            return ['status' => 'failed', 'mime' => $mime, 'preview' => ''];
        }

        // Normalise un peu + coupe
        $content = str_replace(["\r\n", "\r"], "\n", $content);

        // Coupe au maxChars
        if (mb_strlen($content) > $this->maxChars) {
            $content = mb_substr($content, 0, $this->maxChars);
        }

        return ['status' => 'extracted', 'mime' => $mime, 'preview' => $content];
    }
}