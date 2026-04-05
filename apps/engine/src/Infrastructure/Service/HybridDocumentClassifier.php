<?php
declare(strict_types=1);

namespace Hestia\Infrastructure\Service;

use Hestia\Domain\Service\DocumentClassifier;
use Hestia\Domain\Service\AiDocumentClassifier;

final class HybridDocumentClassifier implements DocumentClassifier
{
    public function __construct(
        private DocumentClassifier $rulesClassifier,
        private ?AiDocumentClassifier $aiClassifier = null,
        private float $rulesConfidenceThreshold = 0.75
    ) {}

    public function classify(string $text, array $meta = []): array
    {
        $rules = $this->rulesClassifier->classify($text, $meta);

        $rulesConfidence = (float)($rules['confidence'] ?? 0.0);

        // Si les règles sont déjà sûres, on les garde
        if ($rulesConfidence >= $this->rulesConfidenceThreshold) {
            $rules['signals'][] = 'hybrid: rules accepted';
            return $rules;
        }

        // Si pas d'IA branchée, on retourne les règles
        if ($this->aiClassifier === null) {
            $rules['signals'][] = 'hybrid: ai unavailable';
            return $rules;
        }

        // Sinon on demande à l'IA
        $allowedCategories = [
            'Factures',
            'Diplomes',
            'Cours',
            'Contrats',
            'Administratif',
            'Autres',
        ];

        $ai = $this->aiClassifier->classify($text, array_merge($meta, [
            'categories' => $allowedCategories,
        ]));

        $aiConfidence = (float)($ai['confidence'] ?? 0.0);

        // Si l'IA est plus convaincante, on la prend
        if ($aiConfidence > $rulesConfidence) {
            $ai['signals'][] = 'hybrid: ai selected';
            return $ai;
        }

        $rules['signals'][] = 'hybrid: rules kept over ai';
        return $rules;
    }
}