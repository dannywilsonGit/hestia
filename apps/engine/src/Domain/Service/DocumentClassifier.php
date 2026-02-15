<?php
declare(strict_types=1);

namespace Hestia\Domain\Service;

interface DocumentClassifier
{
    /**
     * @param string $text Texte “preview” (déjà extrait)
     * @param array  $meta Meta optionnelle (ext, name, path…)
     *
     * @return array{
     *   category: string,
     *   confidence: float,
     *   signals: array<int, string>
     * }
     */
    public function classify(string $text, array $meta = []): array;
}
