<?php
declare(strict_types=1);

namespace Hestia\Infrastructure\Service;

use Hestia\Domain\Service\AiDocumentClassifier;

final class LlamaCppDocumentClassifier implements AiDocumentClassifier
{
    public function __construct(
        private string $binPath,
        private string $modelPath,
        private string $promptTemplatePath,
        private int $timeoutSeconds = 60,
        private float $temperature = 0.0
    ) {}

    public function classify(string $text, array $meta = []): array
    {
        $categories = $meta['categories'] ?? [
            'Factures',
            'Diplomes',
            'Cours',
            'Contrats',
            'Administratif',
            'Autres',
        ];

        if (!is_file($this->binPath)) {
            return [
                'category' => 'Autres',
                'confidence' => 0.0,
                'signals' => ['llama cpp binary missing'],
            ];
        }

        if (!is_file($this->modelPath)) {
            return [
                'category' => 'Autres',
                'confidence' => 0.0,
                'signals' => ['llama model missing'],
            ];
        }

        if (!is_file($this->promptTemplatePath)) {
            return [
                'category' => 'Autres',
                'confidence' => 0.0,
                'signals' => ['prompt template missing'],
            ];
        }

        $promptTemplate = file_get_contents($this->promptTemplatePath);
        if ($promptTemplate === false) {
            return [
                'category' => 'Autres',
                'confidence' => 0.0,
                'signals' => ['prompt template unreadable'],
            ];
        }

        $prompt = str_replace('{CATEGORIES}', implode(', ', $categories), $promptTemplate);
        $prompt .= "\n\n";
        $prompt .= "Nom du fichier: " . ($meta['name'] ?? '') . "\n";
        $prompt .= "Extension: " . ($meta['ext'] ?? '') . "\n";
        $prompt .= "Texte du document:\n";
        $prompt .= $text . "\n";

        $raw = $this->runLlama($prompt);
        if ($raw === '') {
            return [
                'category' => 'Autres',
                'confidence' => 0.0,
                'signals' => ['llama empty response'],
            ];
        }

        $json = $this->extractJson($raw);
        if ($json === null) {
            return [
                'category' => 'Autres',
                'confidence' => 0.0,
                'signals' => ['llama invalid json'],
            ];
        }

        $category = (string)($json['category'] ?? 'Autres');
        $confidence = (float)($json['confidence'] ?? 0.0);
        $signals = $json['signals'] ?? ['llama no signals'];

        if (!in_array($category, $categories, true)) {
            $category = 'Autres';
            $confidence = 0.0;
            $signals = ['llama returned forbidden category'];
        }

        if (!is_array($signals)) {
            $signals = ['llama signals malformed'];
        }

        return [
            'category' => $category,
            'confidence' => max(0.0, min(1.0, $confidence)),
            'signals' => array_values(array_map('strval', $signals)),
        ];
    }

    private function runLlama(string $prompt): string
    {
        $binDir = dirname($this->binPath);

        $tmpPrompt = tempnam(sys_get_temp_dir(), 'hestia_llama_prompt_');
        if ($tmpPrompt === false) {
            throw new \RuntimeException('Cannot create temp prompt file');
        }

        file_put_contents($tmpPrompt, $prompt);

        $cmd = 'cd /d ' . escapeshellarg($binDir)
            . ' && ' . escapeshellarg($this->binPath)
            . ' -m ' . escapeshellarg($this->modelPath)
            . ' -f ' . escapeshellarg($tmpPrompt)
            . ' -n 256'
            . ' --temp ' . escapeshellarg((string)$this->temperature)
            . ' 2>&1';

        exec($cmd, $out, $code);

        @unlink($tmpPrompt);

        if ($code !== 0) {
            throw new \RuntimeException('llama-cli failed: ' . implode("\n", $out));
        }

        return trim(implode("\n", $out));
    }

    private function extractJson(string $raw): ?array
    {
        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');

        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $candidate = substr($raw, $start, $end - $start + 1);
        $decoded = json_decode($candidate, true);

        return is_array($decoded) ? $decoded : null;
    }
}