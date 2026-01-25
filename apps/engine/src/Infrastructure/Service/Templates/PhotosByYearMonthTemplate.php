<?php
declare(strict_types=1);

namespace Hestia\Infrastructure\Service\Templates;

use Hestia\Domain\Service\PlanTemplate;

final class PhotosByYearMonthTemplate implements PlanTemplate
{
    private const IMAGE_EXT = ['jpg', 'jpeg', 'jpe', 'jps', 'png', 'gif', 'tif', 'tiff', 'heic', 'webp', 'bmp'];

    public function id(): string
    {
        return 'photos_by_year_month';
    }

    public function buildActions(string $root, array $files): array
    {
        $root = rtrim($root, "\\/");

        $actions = [];
        $mkdirNeeded = [];

        foreach ($files as $f) {
            if (!isset($f['path'], $f['name'], $f['ext'])) continue;

            $ext = strtolower((string)$f['ext']);
            if (!in_array($ext, self::IMAGE_EXT, true)) {
                continue; // ce template ignore tout ce qui n'est pas image
            }

            $from = (string)$f['path'];

            // Détermine année/mois à partir de la date de modification du fichier (simple + fiable MVP)
            $ts = @filemtime($from);
            if (!$ts) {
                $ts = time();
            }
            $year = date('Y', $ts);
            $month = date('m', $ts);

            $destDir = $root . DIRECTORY_SEPARATOR . 'Images' . DIRECTORY_SEPARATOR . $year . DIRECTORY_SEPARATOR . $month;

            $mkdirNeeded[$destDir] = true;

            $actions[] = [
                'type' => 'move',
                'from' => $from,
                'to'   => $destDir . DIRECTORY_SEPARATOR . (string)$f['name'],
            ];
        }

        foreach (array_keys($mkdirNeeded) as $dir) {
            $actions[] = [
                'type' => 'mkdir',
                'to'   => $dir,
                'name' => basename($dir),
            ];
        }

        // mkdir d'abord
        usort($actions, fn($a, $b) => ($a['type'] === 'mkdir' ? 0 : 1) <=> ($b['type'] === 'mkdir' ? 0 : 1));

        return $actions;
    }
}