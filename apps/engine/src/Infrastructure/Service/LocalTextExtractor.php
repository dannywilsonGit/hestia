<?php
declare(strict_types=1);

namespace Hestia\Infrastructure\Service;

use Hestia\Domain\Service\TextExtractor;
use PhpOffice\PhpWord\IOFactory;

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

    // TXT / MD (déjà OK)
    if (in_array($ext, ['txt', 'md'], true)) {
        $mime = $ext === 'md' ? 'text/markdown' : 'text/plain';
        $content = @file_get_contents($path);
        if ($content === false) {
            return ['status' => 'failed', 'mime' => $mime, 'preview' => ''];
        }

        $content = str_replace(["\r\n", "\r"], "\n", $content);
        if (mb_strlen($content) > $this->maxChars) {
            $content = mb_substr($content, 0, $this->maxChars);
        }

        return ['status' => 'extracted', 'mime' => $mime, 'preview' => $content];
    }

    // DOCX
    if ($ext === 'docx') {
        try {
            $phpWord = IOFactory::load($path);
            $text = '';

            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $el) {
                    if (method_exists($el, 'getText')) {
                        $text .= $el->getText() . "\n";
                    }
                }
            }

            if ($text === '') {
                return ['status' => 'failed', 'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'preview' => ''];
            }

            if (mb_strlen($text) > $this->maxChars) {
                $text = mb_substr($text, 0, $this->maxChars);
            }

                return [
                    'status' => 'extracted',
                    'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'preview' => $text,
                ];
            } catch (\Throwable $e) {
                return [
                    'status' => 'failed',
                    'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'preview' => 'DOCX_ERROR: ' . $e->getMessage(),
                ];
            }
        }

    // Non supporté
    return null;
}
}