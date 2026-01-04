<?php
declare(strict_types=1);

namespace Hestia\Domain\Service;

interface Filesystem
{
    /**
     * Retourne une liste de fichiers sous $root (récursif) en respectant les exclusions et la profondeur.
     *
     * @return array<int, array{path:string, name:string, ext:string, size:int}>
     */
    public function listFiles(string $root, int $maxDepth, array $excludeNames): array;
}
