<?php
declare(strict_types=1);

namespace Hestia\Infrastructure\Filesystem;

use Hestia\Domain\Service\Filesystem;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;

final class LocalFilesystem implements Filesystem
{
    public function listFiles(string $root, int $maxDepth, array $excludeNames): array
    {
        $root = rtrim($root, "\\/");

        if (!is_dir($root)) {
            return [];
        }

        $exclude = array_flip(array_map('strtolower', $excludeNames));

        $dirIt = new RecursiveDirectoryIterator(
            $root,
            FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS
        );

        $it = new RecursiveIteratorIterator($dirIt, RecursiveIteratorIterator::SELF_FIRST);

        $files = [];

        foreach ($it as $info) {
            $depth = $it->getDepth();
            if ($depth > $maxDepth) {
                continue;
            }

            $name = $info->getFilename();
            if (isset($exclude[strtolower($name)])) {
                if ($info->isDir()) {
                    $it->next();
                }
                continue;
            }

            if ($info->isFile()) {
                $path = $info->getPathname();
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                $files[] = [
                    'path' => $path,
                    'name' => $info->getFilename(),
                    'ext' => $ext,
                    'size' => (int)$info->getSize(),
                ];
            }
        }

        return $files;
    }

    public function normalizePath(string $path): string
    {
        // realpath échoue si le path n’existe pas encore (ex: destination).
        // On normalise au mieux: slash + suppression "/./"
        $p = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $p = preg_replace('#' . preg_quote(DIRECTORY_SEPARATOR) . '+#', DIRECTORY_SEPARATOR, $p) ?? $p;
        $p = str_replace(DIRECTORY_SEPARATOR . '.' . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, $p);

        $rp = realpath($p);
        return $rp !== false ? $rp : $p;
    }

    public function isPathInsideRoot(string $root, string $path): bool
    {
        $rootN = rtrim($this->normalizePath($root), "\\/") . DIRECTORY_SEPARATOR;

        // pour la destination (qui peut ne pas exister), on check sur le parent existant
        $pathN = $this->normalizePath($path);

        // Si le path n'existe pas, on check son parent
        if (!file_exists($pathN)) {
            $parent = dirname($pathN);
            $parentN = $this->normalizePath($parent) . DIRECTORY_SEPARATOR;
            return strncmp($parentN, $rootN, strlen($rootN)) === 0;
        }

        // Path existe
        if (is_dir($pathN)) {
            $pathN = rtrim($pathN, "\\/") . DIRECTORY_SEPARATOR;
        }

        return strncmp($pathN, $rootN, strlen($rootN)) === 0;
    }

    public function ensureDir(string $dir): void
    {
        if (is_dir($dir)) {
            return;
        }
        if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new \RuntimeException("Failed to create dir: $dir");
        }
    }

    public function move(string $from, string $to): void
    {
        $parent = dirname($to);
        $this->ensureDir($parent);

        // rename() fait move+rename sur même disque
        if (!rename($from, $to)) {
            throw new \RuntimeException("Failed to move: $from -> $to");
        }
    }

    public function fileExists(string $path): bool
    {
        return is_file($path);
    }

    public function removeDirIfEmpty(string $dir): void
    {
        if (!is_dir($dir)) return;

        $items = scandir($dir);
        if ($items === false) return;

        // vide = . et ..
        if (count($items) <= 2) {
            @rmdir($dir);
        }
    }
}
