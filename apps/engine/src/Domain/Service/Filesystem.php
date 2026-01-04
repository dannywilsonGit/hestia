<?php
declare(strict_types=1);

namespace Hestia\Domain\Service;

interface Filesystem
{
    /**
     * Liste récursive des fichiers.
     * @return array<int, array{path:string, name:string, ext:string, size:int}>
     */
    public function listFiles(string $root, int $maxDepth, array $excludeNames): array;

    /** Chemin absolu normalisé (résout .. et liens si possible). */
    public function normalizePath(string $path): string;

    /** Vérifie que $path est dans $root (sécurité: ne jamais sortir du dossier choisi). */
    public function isPathInsideRoot(string $root, string $path): bool;

    /** Crée un dossier (récursif). */
    public function ensureDir(string $dir): void;

    /** Déplace/renomme un fichier (atomic si possible). */
    public function move(string $from, string $to): void;

    /** Retourne true si fichier existe. */
    public function fileExists(string $path): bool;

    /** Supprime un dossier si vide (sinon ne fait rien). */
    public function removeDirIfEmpty(string $dir): void;
}
