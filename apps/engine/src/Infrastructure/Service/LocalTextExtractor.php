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

        // TXT / MD
        if (in_array($ext, ['txt', 'md'], true)) {
            $mime = $ext === 'md' ? 'text/markdown' : 'text/plain';
            $content = @file_get_contents($path);
            if ($content === false) {
                return ['status' => 'failed', 'mime' => $mime, 'preview' => ''];
            }

            $content = str_replace(["\r\n", "\r"], "\n", $content);
            $content = $this->truncate($content);

            return ['status' => 'extracted', 'mime' => $mime, 'preview' => $content];
        }

        // DOCX
        if (in_array($ext, ['docx', 'odt'], true)) {
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

                $text = trim($text);
                if ($text === '') {
                    return [
                        'status' => 'failed',
                        'mime' => $ext === 'docx' ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' : 'application/vnd.oasis.opendocument.text',
                        'preview' => ''
                    ];
                }

                $text = $this->truncate($text);

                return [
                    'status' => 'extracted',
                    'mime' => $ext === 'docx' ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' : 'application/vnd.oasis.opendocument.text',
                    'preview' => $text,
                ];
            } catch (\Throwable $e) {
                return [
                    'status' => 'failed',
                    'mime' => $ext === 'docx' ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' : 'application/vnd.oasis.opendocument.text',
                    'preview' => 'DOCX_ERROR: ' . $e->getMessage()
                ];
            }
        }

        // PDF via pdftotext embarqué
        if ($ext === 'pdf') {
            try {
                $text = $this->extractPdfText($path);
                $text = trim($text);

                if ($text === '') {
                    return ['status' => 'failed', 'mime' => 'application/pdf', 'preview' => ''];
                }

                $text = $this->truncate($text);

                return [
                    'status' => 'extracted',
                    'mime' => 'application/pdf',
                    'preview' => $text
                    ];
            } catch (\Throwable $e) {
                return [
                    'status' => 'failed',
                    'mime' => 'application/pdf', 
                    'preview' => 'PDF_ERROR: ' . $e->getMessage()];
            }
        }

        // Non supporté
        return null;
    }

    private function truncate(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        if (mb_strlen($text) > $this->maxChars) {
            $text = mb_substr($text, 0, $this->maxChars);
        }

        return $text;
    }

    private function extractPdfText(string $path): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'hestia_pdf_');
        if ($tmp === false) {
            throw new \RuntimeException("Cannot create temp file");
        }

        $tmpTxt = $tmp . '.txt';

        //Chemin du binaire embarqué dans notre repo
        // Ce fichier doit exister : apps/engine/bin/windows/pdftotext.exe
        $bin = dirname(__DIR__, 3)
            . DIRECTORY_SEPARATOR . 'bin'
            . DIRECTORY_SEPARATOR . 'windows'
            . DIRECTORY_SEPARATOR . 'pdftotext.exe';

        if (!is_file($bin)) {
            @unlink($tmp);
            throw new \RuntimeException("pdftotext binary not found: " . $bin);
        }

        $binDir = dirname($bin);

        $cmd = 'cd /d ' . escapeshellarg($binDir)
            . ' && ' . escapeshellarg($bin)
            . ' -layout ' . escapeshellarg($path) . ' ' . escapeshellarg($tmpTxt)
            . ' 2>&1';


        exec($cmd, $out, $code);

        if ($code !== 0) {
            @unlink($tmpTxt);
            @unlink($tmp);
            throw new \RuntimeException("pdftotext failed: " . implode("\n", $out));
        }

        $content = @file_get_contents($tmpTxt);

        @unlink($tmpTxt);
        @unlink($tmp);

        if ($content === false) {
            throw new \RuntimeException("Cannot read pdftotext output");
        }

        return str_replace(["\r\n", "\r"], "\n", $content);
    }
}
