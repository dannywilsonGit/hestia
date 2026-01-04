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
            FilesystemIterator::SKIP_DOTS
            | FilesystemIterator::FOLLOW_SYMLINKS
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
                // si c'est un dossier exclu, on saute tout son contenu
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
}
