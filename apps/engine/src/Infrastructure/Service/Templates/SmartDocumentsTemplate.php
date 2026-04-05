<?php
declare(strict_types=1);

namespace Hestia\Infrastructure\Service\Templates;

use Hestia\Domain\Service\PlanTemplate;

final class SmartDocumentsTemplate implements PlanTemplate
{
    private const IMAGE_EXT = ['jpg','jpeg','jpe','jps','png','gif','tif','tiff','heic','webp','bmp'];
    private const DOC_EXT   = ['odt','doc','docx','pdf','txt','rtf','md','ppt','pptx','pot','potx','potm','pps','ppsx','pptm'];
    private const ARCH_EXT  = ['zip','rar','7z','tar','gz'];
    private const EXEC_EXT  = ['exe','msi'];
    private const VIDEO_EXT = ['avi','flv','mov','movie','mp4','mpe','mpeg','mpg','qt','rm','rmvb','rv','vob','wmv','m4a'];
    private const AUDIO_EXT = ['aac','ac3','aif','aifc','aiff','au','bwf','mp2','mp3','m4r','ogg','ogm','ra','ram','wma','wav'];

    // Sous-catégories “documents intelligents”
    
    private const DOC_CATS = [
        'Bulletins de salaire',  
        'Diplômes & Formations'  ,    
        'Relevés bancaires',  
        'Factures et Quittances' ,
        'Reçus & justificatifs' ,
        'Mes contrats', 
        'Lettres et Emails',
        'Documents administratifs',
        'Santé',
        'Autres'
    ];


    public function __construct(private float $minConfidence = 0.25) {}

    public function id(): string
    {
        return 'smart_documents';
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
            $from = (string)$f['path'];

            // 1) Catégorie “par type”
            $top = $this->topCategoryForExt($ext);

            // 2) Si document, on tente une sous-catégorie par classification
            if ($top === 'Documents') {
                [$sub, $subWhy] = $this->docSubCategory($f);

                // On range sous Documents/<sub>
                $destDir = $root . DIRECTORY_SEPARATOR . 'Documents' . DIRECTORY_SEPARATOR . $sub;

                $mkdirNeeded[$destDir] = true;
                $dirNames[$destDir] = $sub;

                $actions[] = [
                    'type' => 'move',
                    'from' => $from,
                    'to'   => $destDir . DIRECTORY_SEPARATOR . (string)$f['name'],
                    // bonus debug (facultatif, utile pour UI plus tard)
                    'meta' => ['docReason' => $subWhy],
                ];
                continue;
            }

            // 3) Le reste (images/audio/video/etc) reste simple
            $destDir = $root . DIRECTORY_SEPARATOR . $top;

            $mkdirNeeded[$destDir] = true;
            $dirNames[$destDir] = $top;

            $actions[] = [
                'type' => 'move',
                'from' => $from,
                'to'   => $destDir . DIRECTORY_SEPARATOR . (string)$f['name'],
            ];
        }

        // mkdir (1 fois par dossier)
        foreach (array_keys($mkdirNeeded) as $dir) {
            $actions[] = [
                'type' => 'mkdir',
                'to'   => $dir,
                'name' => $dirNames[$dir] ?? basename($dir),
            ];
        }

        // mkdir d'abord
        usort($actions, fn($a, $b) => ($a['type'] === 'mkdir' ? 0 : 1) <=> ($b['type'] === 'mkdir' ? 0 : 1));

        return $actions;
    }

    private function topCategoryForExt(string $ext): string
    {
        if ($ext === '' || $ext === 'no_ext') return 'Autres';
        if (in_array($ext, self::IMAGE_EXT, true)) return 'Images';
        if (in_array($ext, self::DOC_EXT, true))   return 'Documents';
        if (in_array($ext, self::ARCH_EXT, true))  return 'Archives';
        if (in_array($ext, self::EXEC_EXT, true))  return 'Applications';
        if (in_array($ext, self::VIDEO_EXT, true)) return 'Videos';
        if (in_array($ext, self::AUDIO_EXT, true)) return 'Audios';
        return 'Autres';
    }

    /**
     * Retourne [subCategory, reason]
     */
    private function docSubCategory(array $file): array
    {
        $c = $file['classification'] ?? null;
        if (!is_array($c)) {
            return ['Reste a trier', 'no classification'];
        }

        $cat = (string)($c['category'] ?? '');
        $conf = (float)($c['confidence'] ?? 0.0);

        if ($cat !== '' && in_array($cat, self::DOC_CATS, true) && $conf >= $this->minConfidence) {
            return [$cat, "classified:$cat conf=$conf"];
        }

        // si on a une catégorie mais pas assez sûr
        if ($cat !== '' && $conf > 0.0) {
            return ['Reste a trier', "low_conf:$cat conf=$conf"];
        }

        return ['Reste a trier', 'unknown'];
    }
}
