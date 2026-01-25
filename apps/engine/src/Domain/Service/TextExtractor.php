<?php
declare(strict_types=1);

namespace Hestia\Domain\Service;

interface TextExtractor
{
    /**
     * Extrait un texte “preview” d’un fichier.
     * Retourne null si non supporté.
     *
     * @return array{status:string, mime:string, preview:string}|null
     */
    public function extractPreview(string $path): ?array;
}