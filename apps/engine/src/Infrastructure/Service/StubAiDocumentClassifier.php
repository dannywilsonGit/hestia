<?php
declare(strict_types=1);

namespace Hestia\Infrastructure\Service;

use Hestia\Domain\Service\AiDocumentClassifier;

final class StubAiDocumentClassifier implements AiDocumentClassifier
{
    public function classify(string $text, array $meta = []): array
    {
        $allowed = $meta['categories'] ?? [
            'Factures',
            'Diplomes',
            'Cours',
            'Contrats',
            'Administratif',
            'Autres',
        ];

        return [
            'category' => in_array('Autres', $allowed, true) ? 'Autres' : (string)$allowed[0],
            'confidence' => 0.0,
            'signals' => ['ai stub: not implemented yet'],
        ];
    }
}