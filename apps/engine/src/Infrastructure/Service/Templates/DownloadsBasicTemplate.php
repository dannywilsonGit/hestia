<?php
declare(strict_types=1);

namespace Hestia\Infrastructure\Service\Templates;

use Hestia\Domain\Service\PlanTemplate;

final class DownloadsBasicTemplate implements PlanTemplate
{
    private const IMAGE_EXT = ['jpg', 'jpeg', 'jpe', 'jps', 'png', 'gif', 'tif', 'tiff', 'ms3d', 'odg', 'otg', 'pct'];
    private const DOC_EXT   = ['odt','doc','docx','pdf','txt','rtf','md', 'ppt', 'pptx', 'pot', 'potx', 'potm', 'pps', 'ppsx', 'pptm'];
    private const ARCH_EXT  = ['zip','rar','7z','tar','gz'];
    private const EXEC_EXT  = ['exe', 'msi'];
    private const VIDEO_EXT = ['avi', 'flv', 'mov', 'movie', 'mp4', 'mpe', 'mpeg', 'mpg', 'qt', 'rm', 'rmvb', 'rv', 'vob', 'wmv', 'm4a'];
    private const AUDIO_EXT = ['aac', 'ac3', 'aif', 'aifc', 'aiff', 'au', 'bwf', 'mp2', 'mp3', 'm4r', 'ogg', 'ogm', 'ra', 'ram', 'wma', 'wav'];

    public function id(): string
    {
        return 'downloads_basic';
    }

    public function buildActions(string $root, array $files): array
    {
        $root = rtrim($root, "\\/");

        $actions = [];
        $mkdirNeeded = [];
        $dirNames = [];

        foreach ($files as $f) {
            if (!isset($f['path'], $f['name'], $f['ext'])) continue;

            $ext = strtolower((string)$f['ext']);
            $category = $this->categoryForExt($ext);

            $destDir = $root . DIRECTORY_SEPARATOR . $category;
            $mkdirNeeded[$destDir] = true;
            $dirNames[$destDir] = $category;

            $actions[] = [
                'type' => 'move',
                'from' => (string)$f['path'],
                'to'   => $destDir . DIRECTORY_SEPARATOR . (string)$f['name'],
            ];
        }

        foreach (array_keys($mkdirNeeded) as $dir) {
            $actions[] = [
                'type' => 'mkdir',
                'to'   => $dir,
                'name' => $dirNames[$dir] ?? 'NewFolder',
            ];
        }

        // mkdir d'abord
        usort($actions, fn($a, $b) => ($a['type'] === 'mkdir' ? 0 : 1) <=> ($b['type'] === 'mkdir' ? 0 : 1));

        return $actions;
    }

    private function categoryForExt(string $ext): string
    {
        if ($ext === '' || $ext === 'no_ext') return 'Autres';
        if (in_array($ext, self::IMAGE_EXT, true)) return 'Images';
        if (in_array($ext, self::DOC_EXT, true)) return 'Documents';
        if (in_array($ext, self::ARCH_EXT, true)) return 'Archives';
        if (in_array($ext, self::EXEC_EXT, true)) return 'Applications';
        if (in_array($ext, self::VIDEO_EXT, true)) return 'Videos';
        if (in_array($ext, self::AUDIO_EXT, true)) return 'Audios';
        return 'Autres';
    }
}