<?php
declare(strict_types=1);

namespace Hestia\Domain\Service;

interface AiDocumentClassifier
{
    /**
     * @param string $text Texte extrait du document
     * @param array{
     *   name?: string,
     *   ext?: string,
     *   path?: string,
     *   categories?: array<int, string>
     * } $meta
     *
     * @return array{
     *   category: string,
     *   confidence: float,
     *   signals: array<int, string>
     * }
     */
    public function classify(string $text, array $meta = []): array;
}