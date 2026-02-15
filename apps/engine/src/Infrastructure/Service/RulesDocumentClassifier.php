<?php
declare(strict_types=1);

namespace Hestia\Infrastructure\Service;

use Hestia\Domain\Service\DocumentClassifier;

final class RulesDocumentClassifier implements DocumentClassifier
{
    public function classify(string $text, array $meta = []): array
    {
        $tRaw = $text;
        $t = $this->normalize($text);

        $scores = [
            'Facture'        => 0.0,
            'Recu/quittance'           => 0.0,
            'Devis'          => 0.0,
            'Contrat'        => 0.0,
            'BulletinPaie'   => 0.0,
            'Diplome'        => 0.0,
            'Cours'          => 0.0,
            'Administratif'  => 0.0,
            'RIB_Banque'     => 0.0,
            'Lettre_Email'   => 0.0,
            'Autres'         => 0.05,
        ];

        $signals = [];
        foreach (array_keys($scores) as $k) $signals[$k] = [];

        $add = function(string $cat, float $w, string $why) use (&$scores, &$signals) {
            $scores[$cat] += $w;
            $signals[$cat][] = $why;
        };

        // --------------------
        // "ANTI-BRUIT" : indices de lettre / email / conversation / notes
        // --------------------
        if ($this->hasAny($t, ['bonjour', 'salut', 'cher', 'chere', 'cordialement', 'bien a vous', 'je te', 'tu ', 'a bientot', 'merci beaucoup'])) {
            $add('Lettre_Email', 0.55, 'style: salutation/lettre');
            $add('Facture', -0.35, 'penalty: style lettre');
            $add('Devis', -0.15, 'penalty: style lettre');
            $add('Contrat', -0.10, 'penalty: style lettre');
        }
        if ($this->hasEmailHeaders($tRaw)) {
            $add('Lettre_Email', 0.70, 'pattern: headers email');
            $add('Facture', -0.35, 'penalty: email');
        }

        // --------------------
        // FACTURE (signaux forts + gating)
        // --------------------
        // Signaux "FORTS" (rarement ailleurs)
        $strongInvoice = 0;

        if ($this->hasInvoiceNumber($t))      { $add('Facture', 0.55, 'strong: numero facture'); $strongInvoice++; }
        if ($this->hasPaymentTerms($t))       { $add('Facture', 0.35, 'strong: conditions de reglement'); $strongInvoice++; }
        if ($this->hasNetToPay($t))           { $add('Facture', 0.35, 'strong: net a payer'); $strongInvoice++; }
        if ($this->hasVatBreakdown($t))       { $add('Facture', 0.35, 'strong: ventilation TVA'); $strongInvoice++; }
        if ($this->hasSupplierLegalMentions($t)) { $add('Facture', 0.25, 'strong: mentions legales fournisseur'); $strongInvoice++; }

        // Signaux "MOYENS" (souvent présents mais pas exclusifs seuls)
        if ($this->hasAny($t, ['facture']))   $add('Facture', 0.20, 'keyword: facture');
        if ($this->hasAny($t, ['montant ttc', 'total ttc', 'total ht', 'montant ht'])) $add('Facture', 0.18, 'invoice-like: totals ht/ttc');
        if ($this->hasCurrencyAmount($t))    $add('Facture', 0.12, 'pattern: montant €');
        if ($this->hasAny($t, ['siret', 'tva intracommunautaire', 'numero de tva', 'n tva'])) $add('Facture', 0.12, 'invoice-like: siret/TVA');
        if ($this->hasAny($t, ['client', 'adresse de facturation', 'adresse de livraison'])) $add('Facture', 0.10, 'invoice-like: client/adresses');

        // Gating: si pas assez de signaux forts, on limite fortement le score
        // (évite “total + €” => facture)
        if ($strongInvoice === 0) {
            $add('Facture', -0.45, 'gate: aucun signal fort facture');
        } elseif ($strongInvoice === 1) {
            $add('Facture', -0.20, 'gate: un seul signal fort facture');
        }

        // --------------------
        // REÇU / TICKET (différent d’une facture)
        // --------------------
        $receiptStrong = 0;
        if ($this->hasAny($t, ['ticket de caisse', 'recu', 'reçu', 'merci de votre achat'])) { $add('Recu/quittance', 0.45, 'keyword: ticket/recu'); $receiptStrong++; }
        if ($this->hasAny($t, ['cb', 'carte bancaire', 'autorisation', 'terminal', 'tpe'])) { $add('Recu/quittance', 0.25, 'receipt-like: paiement CB'); $receiptStrong++; }
        if ($this->hasAny($t, ['tva incluse', 'total a payer', 'total a régler']))          { $add('Recu/quittance', 0.15, 'receipt-like: total a payer'); }
        if ($this->hasAny($t, ['facture'])) $add('Recu/quittance', -0.10, 'penalty: mention facture');

        if ($receiptStrong === 0 && $this->hasAny($t, ['recu', 'reçu'])) {
            $add('Recu/quittance', -0.20, 'gate: recu sans autres indices');
        }

        // --------------------
        // DEVIS (proche facture mais vocabulaire spécifique)
        // --------------------
        $quoteStrong = 0;
        if ($this->hasAny($t, ['devis', 'quote'])) { $add('Devis', 0.35, 'keyword: devis'); $quoteStrong++; }
        if ($this->hasAny($t, ['valable', 'validite', 'valable jusqu', 'durée de validité'])) { $add('Devis', 0.25, 'strong: validite devis'); $quoteStrong++; }
        if ($this->hasAny($t, ['estimation', 'proposition commerciale'])) { $add('Devis', 0.15, 'quote-like: proposition'); }
        if ($this->hasAny($t, ['bon pour accord', 'signature du client'])) { $add('Devis', 0.15, 'quote-like: bon pour accord'); }

        // --------------------
        // CONTRAT
        // --------------------
        if ($this->hasContractStructure($t)) $add('Contrat', 0.55, 'strong: structure contrat');
        if ($this->hasAny($t, ['bail', 'contrat', 'garantie', 'entre les soussignes', 'entre les soussignés', 'contrat', 'article', 'clause', 'conditions generales', 'conditions particulières', 'resiliation', 'résiliation'])) {
            $add('Contrat', 0.25, 'keyword: contrat/articles/clauses');
        }
        if ($this->hasAny($t, ['entre les soussignes', 'entre les soussignés', 'ci-apres', 'ci après'])) $add('Contrat', 0.20, 'contract-like: formule juridique');
        if ($this->hasDateLike($t)) $add('Contrat', 0.08, 'pattern: date');

        // --------------------
        // BULLETIN DE PAIE (très distinct)
        // --------------------
        if ($this->hasAny($t, ['bulletin de paie', 'fiche de paie', 'salaire brut', 'salaire net', 'net imposable', 'urssaf', 'cotisations', 'heures travaillees', 'heures supplémentaires'])) {
            $add('BulletinPaie', 0.70, 'strong: bulletin paie');
            $add('Facture', -0.30, 'penalty: paie ≠ facture');
        }

        // --------------------
        // RIB / BANQUE
        // --------------------
        if ($this->hasAny($t, ['iban', 'bic'])) $add('RIB_Banque', 0.40, 'keyword: IBAN/BIC');
        if ($this->hasAny($t, ['releve d identite bancaire', 'relevé d identité bancaire', 'rib'])) $add('RIB_Banque', 0.40, 'keyword: RIB');
        if ($this->hasIbanPattern($t)) $add('RIB_Banque', 0.30, 'pattern: IBAN');

        // --------------------
        // DIPLOME
        // --------------------
        if ($this->hasDiplomaSignals($t)) $add('Diplome', 0.65, 'strong: diplôme/attestation scolaire');
        if ($this->hasAny($t, ['universite', 'université', 'ecole', 'école', 'master', 'licence', 'bachelor', 'doctorat'])) $add('Diplome', 0.15, 'keyword: établissement/niveau');

        // --------------------
        // COURS
        // --------------------
        if ($this->hasAny($t, ['cours', 'chapitre', 'exercice', 'td', 'tp'])) $add('Cours', 0.35, 'keyword: cours/TD/TP');
        if ($this->hasAny($t, ['theoreme', 'théorème', 'definition', 'définition'])) $add('Cours', 0.18, 'keyword: théorème/définition');
        if ($this->hasBulletLikeStructure($tRaw)) $add('Cours', 0.12, 'pattern: structure notes');

        // --------------------
        // ADMINISTRATIF
        // --------------------
        if ($this->hasAny($t, ['prefecture', 'préfecture', 'dossier', 'cerfa', 'demande', 'attestation', 'justificatif', 'numero de dossier', 'numéro de dossier'])) {
            $add('Administratif', 0.35, 'admin-like: pref/cerfa/dossier');
        }
        if ($this->hasAny($t, ['adresse', 'telephone', 'téléphone', 'courriel', 'email'])) $add('Administratif', 0.10, 'admin-like: coordonnées');

        // --------------------
        // Meta bonus: nom de fichier (faible, pas décisif)
        // --------------------
        $name = strtolower((string)($meta['name'] ?? ''));
        if ($name !== '') {
            if (str_contains($name, 'facture')) $add('Facture', 0.12, 'filename: facture');
            if (str_contains($name, 'devis'))   $add('Devis', 0.12, 'filename: devis');
            if (str_contains($name, 'recu') || str_contains($name, 'reçu')) $add('Recu', 0.12, 'filename: reçu');
            if (str_contains($name, 'contrat')) $add('Contrat', 0.10, 'filename: contrat');
            if (str_contains($name, 'paie') || str_contains($name, 'bulletin')) $add('BulletinPaie', 0.12, 'filename: paie');
            if (str_contains($name, 'rib') || str_contains($name, 'iban')) $add('RIB_Banque', 0.10, 'filename: rib/iban');
        }

        // --------------------
        // Décision finale
        // --------------------
        arsort($scores);
        $bestCat = array_key_first($scores) ?: 'Autres';
        $bestScore = (float)($scores[$bestCat] ?? 0.0);

        // Normalisation simple (clamp)
        $confidence = max(0.0, min(1.0, $bestScore));

        $bestSignals = $signals[$bestCat] ?? [];
        if ($bestCat === 'Autres' && empty($bestSignals)) $bestSignals = ['no strong signals'];

        return [
            'category'   => $bestCat,
            'confidence' => $confidence,
            'signals'    => $bestSignals,
        ];
    }

    // --------------------
    // Normalisation / helpers
    // --------------------
    private function normalize(string $text): string
    {
        $t = mb_strtolower($text);
        $t = str_replace(["\r\n", "\r"], "\n", $t);
        $t = strtr($t, [
            'é' => 'e', 'è' => 'e', 'ê' => 'e',
            'à' => 'a', 'â' => 'a',
            'î' => 'i', 'ï' => 'i',
            'ô' => 'o',
            'ù' => 'u', 'û' => 'u',
            'ç' => 'c',
            '’' => "'", '“' => '"', '”' => '"',
        ]);
        return $t;
    }

    private function hasAny(string $text, array $needles): bool
    {
        foreach ($needles as $n) {
            $n2 = $this->normalize($n);
            if ($n2 !== '' && str_contains($text, $n2)) return true;
        }
        return false;
    }

    private function hasCurrencyAmount(string $t): bool
    {
        // 42€, 42.00 €, 1 234,56 €
        return (bool)preg_match('/\b\d{1,3}(?:[\s.,]\d{3})*(?:[.,]\d{2})?\s*€\b/u', $t);
    }

    private function hasDateLike(string $t): bool
    {
        return (bool)preg_match('/\b(\d{2}[\/\-]\d{2}[\/\-]\d{4}|\d{4}[\/\-]\d{2}[\/\-]\d{2})\b/', $t);
    }

    private function hasEmailHeaders(string $raw): bool
    {
        // Indices très typés email
        return (bool)preg_match('/^(from:|de:)\s.+\n(to:|a:)\s.+\n(subject:|objet:)\s.+/mi', $raw);
    }

    // --------------------
    // Facture: signaux forts
    // --------------------
    private function hasInvoiceNumber(string $t): bool
    {
        // "facture n° F2026-001", "numero de facture : 12345", "invoice no"
        return (bool)preg_match('/\b(facture|invoice)\s*(n|no|n°|numero)\s*[:#]?\s*[a-z0-9][a-z0-9\-\/]{2,}\b/u', $t)
            || (bool)preg_match('/\b(numero de facture|no facture|n facture)\s*[:#]?\s*[a-z0-9][a-z0-9\-\/]{2,}\b/u', $t);
    }

    private function hasPaymentTerms(string $t): bool
    {
        // "conditions de règlement", "échéance", "payable sous 30 jours", "mode de règlement"
        return $this->hasAny($t, [
            'conditions de reglement',
            'mode de reglement',
            'echeance',
            'date d echeance',
            'payable sous',
            'delai de paiement',
            'penalites de retard',
            'escompte pour reglement anticipe',
        ]);
    }

    private function hasNetToPay(string $t): bool
    {
        // "net à payer", "montant net à payer", "solde à payer"
        return $this->hasAny($t, [
            'net a payer',
            'montant net a payer',
            'solde a payer',
            'reste a payer',
            'total a regler',
        ]);
    }

    private function hasVatBreakdown(string $t): bool
    {
        // "base ht", "taux tva", "montant tva", "total tva"
        // plutôt une combinaison : au moins 2 marqueurs TVA
        $hits = 0;
        foreach (['taux tva', 'montant tva', 'total tva', 'base ht', 'total ht', 'tva 20', 'tva 10', 'tva 5,5', 'tva 5.5'] as $k) {
            if (str_contains($t, $this->normalize($k))) $hits++;
        }
        return $hits >= 2;
    }

    private function hasSupplierLegalMentions(string $t): bool
    {
        // Mentions typiques bas de facture (pas 100% exclusif, mais très indicatif)
        return $this->hasAny($t, [
            'siret',
            'rcs',
            'code ape',
            'capital social',
            'tva intracommunautaire',
        ]);
    }

    // --------------------
    // Devis / Contrat / Diplôme
    // --------------------
    private function hasContractStructure(string $t): bool
    {
        // "Entre les soussignés" + "Article 1" etc.
        $a = $this->hasAny($t, ['bail', 'contrat', 'garantie', 'entre les soussignes', 'entre les soussignés']);
        $b = (bool)preg_match('/\barticle\s+\d+\b/u', $t);
        return $a && $b;
    }

    private function hasDiplomaSignals(string $t): bool
    {
        // "atteste que", "a obtenu le diplôme", "inscrit(e) en", etc.
        $hits = 0;
        foreach ([
            'diplome',
            'diplome national',
            'atteste que',
            'certifie que',
            'a obtenu',
            'est inscrit',
            'est inscrite',
            'annee universitaire',
            'releve de notes',
        ] as $k) {
            if (str_contains($t, $this->normalize($k))) $hits++;
        }
        return $hits >= 2;
    }

    private function hasIbanPattern(string $t): bool
    {
        // FR76 3000 6000 0112 3456 7890 189
        return (bool)preg_match('/\b[a-z]{2}\d{2}(?:\s?[a-z0-9]{4}){3,7}\b/i', $t);
    }

    private function hasBulletLikeStructure(string $raw): bool
{
    $lines = preg_split("/\r\n|\r|\n/", $raw) ?: [];
    $total = 0; $short = 0; $bullet = 0;

    foreach ($lines as $l) {
        $l = trim($l);
        if ($l === '') continue;
        $total++;

        if (mb_strlen($l) <= 60) $short++;

        // vraie forme de puce/numérotation
        if (preg_match('/^(\-|\*|•|\d+[.)])\s+/', $l)) $bullet++;
    }

    if ($total < 8) return false;

    $shortRatio = $short / $total;
    $bulletRatio = $bullet / $total;

    // Notes = beaucoup de lignes courtes ET un minimum de puces/numéros
    return $shortRatio > 0.6 && $bulletRatio > 0.15;
}

}

