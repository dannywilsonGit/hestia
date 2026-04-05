<?php
declare(strict_types=1);

namespace Hestia\Infrastructure\Service;

use Hestia\Domain\Service\DocumentClassifier;

final class RulesDocumentClassifier implements DocumentClassifier
{
    // Catégories avec priorités (pour trancher les égalités)
    private const CATEGORIES = [
        'Bulletins de salaire'   => 100,
        'Diplômes & Formations'        => 95, 
        'Relevés bancaires'     => 90,
        'Factures et Quittances'   => 80,
        'Reçus & justificatifs' => 70,
        'Mes contrats'   => 60, 
        'Lettres et Emails'   => 40,
        'Documents administratifs'  => 30,
        'Santé'     => 50,
        'Autres'     => 0,
    ];

    public function classify(string $text, array $meta = []): array
    {
        $tRaw = $text;
        $t = $this->normalize($text);
        $filename = strtolower($meta['name'] ?? '');

        // Extraction des features (une seule fois)
        $features = $this->extractFeatures($t, $tRaw, $filename);

        // Scoring par catégorie avec signaux
        $results = [];
        $results['Bulletins de salaire']   = $this->scoreBulletinPaie($features);
        $results['Diplômes & Formations']        = $this->scoreDiplome($features);
        $results['Relevés bancaires']     = $this->scoreRIB($features);
        $results['Factures et Quittances']        = $this->scoreFacture($features);
        $results['Reçus & justificatifs'] = $this->scoreRecu($features);
        $results['Mes contrats']        = $this->scoreContrat($features);
        $results['Lettres et Emails']   = $this->scoreLettre($features);
        $results['Documents administratifs']  = $this->scoreAdministratif($features);
        $results['Santé']  = $this->scoreSante($features);

        // Anti-patterns globaux (réduction inter-catégories)
        $this->applyGlobalConstraints($results, $features);

        // Déterminer le meilleur
        $best = $this->selectBest($results);

        return [
            'category'   => $best['category'],
            'confidence' => $best['confidence'],
            'signals'    => $best['signals'],
        ];
    }

    // ========================================
    // EXTRACTION DE FEATURES (UNE SEULE FOIS)
    // ========================================
    private function extractFeatures(string $t, string $tRaw, string $filename): array
    {
        return [
            // Patterns financiers
            'has_invoice_number'     => $this->hasInvoiceNumber($t),
            'has_quote_number'       => $this->hasQuoteNumber($t),
            'has_currency_amount'    => $this->hasCurrencyAmount($t),
            'has_vat_breakdown'      => $this->hasVatBreakdown($t),
            'has_net_to_pay'         => $this->hasNetToPay($t),
            'has_payment_terms'      => $this->hasPaymentTerms($t),
            'has_payment_due_date'   => $this->hasPaymentDueDate($t),
            
            // Patterns commerciaux
            'has_supplier_info'      => $this->hasSupplierInfo($t),
            'has_client_address'     => $this->hasClientAddress($t),
            'has_itemized_list'      => $this->hasItemizedList($tRaw),
            
            // Patterns devis spécifiques
            'has_validity_period'    => $this->hasValidityPeriod($t),
            'has_quote_acceptance'   => $this->hasQuoteAcceptance($t),
            'has_estimate_wording'   => $this->hasEstimateWording($t),
            
            // Patterns paiement physique (ticket)
            'has_cb_payment'         => $this->hasCBPayment($t),
            'has_receipt_wording'    => $this->hasReceiptWording($t),
            'has_thank_you'          => $this->hasAny($t, ['merci de votre achat', 'merci pour votre visite', 'merci de votre commande']),
            
            // Patterns juridiques
            'has_contract_structure' => $this->hasContractStructure($t),
            'has_legal_clauses'      => $this->hasLegalClauses($t),
            'has_signature_block'    => $this->hasSignatureBlock($t),
            
            // Patterns RH
            'has_payslip_wording'    => $this->hasPayslipWording($t),
            'has_salary_breakdown'   => $this->hasSalaryBreakdown($t),
            'has_social_charges'     => $this->hasSocialCharges($t),
            
            // Patterns bancaires
            'has_iban'               => $this->hasIbanPattern($t),
            'has_rib_wording'        => $this->hasAny($t, ['IBAN', 'banque','releve de compte','cle rib', 'releve d identite bancaire', 'titulaire du compte', 'domiciliation']),
            
            // Patterns éducation
            'has_diploma_wording'    => $this->hasDiplomaWording($t),
            'has_academic_institution' => $this->hasAcademicInstitution($t),
            'has_grade_mention'      => $this->hasGradeMention($t),
            
            // Patterns communication
            'has_greeting'           => $this->hasGreeting($t),
            'has_email_headers'      => $this->hasEmailHeaders($tRaw),
            
            // Patterns santé
            'has_health_wording'     => $this->hasHealthWording($t),


            // Patterns admin
            'has_admin_wording'      => $this->hasAdminWording($t),
            'has_form_number'        => $this->hasFormNumber($t),


            
            // Metadata
            'has_date'               => $this->hasDateLike($t),
            'filename'               => $filename,
            
            // Structure générale
            'is_short'               => str_word_count($t) < 50,
            'is_long'                => str_word_count($t) > 800,
            'has_table_structure'    => $this->hasTableStructure($tRaw),
        ];
    }

    // ========================================
    // SCORING PAR CATÉGORIE
    // ========================================
    
    private function scoreBulletinPaie(array $f): array
    {
        $score = 0.0;
        $signals = [];
        
        // Signaux EXCLUSIFS (très discriminants)
        if ($f['has_payslip_wording']) { $score += 1.0; $signals[] = 'EXCLUSIF: mentions paie'; }
        if ($f['has_salary_breakdown']) { $score += 0.8; $signals[] = 'EXCLUSIF: ventilation salaire'; }
        if ($f['has_social_charges']) { $score += 0.7; $signals[] = 'FORT: cotisations sociales'; }
        
        // Gate: au moins 1 signal exclusif requis
        if ($score < 0.7) return ['score' => 0.0, 'signals' => []];
        
        return ['score' => min(1.0, $score), 'signals' => $signals];
    }
    
    private function scoreDiplome(array $f): array
    {
        $score = 0.0;
        $signals = [];
        
        if ($f['has_diploma_wording']) { $score += 0.9; $signals[] = 'EXCLUSIF: attestation diplôme'; }
        if ($f['has_academic_institution']) { $score += 0.5; $signals[] = 'FORT: établissement académique'; }
        if ($f['has_grade_mention']) { $score += 0.4; $signals[] = 'mention/grade'; }
        if ($f['has_signature_block']) { $score += 0.2; $signals[] = 'bloc signature'; }

        if (str_contains($f['filename'], 'diplome')) {
            $score += 0.2; $signals[] = 'filename: diplôme';
        }
        
        // Gate: sans wording diplôme, score max = 0.3
        if (!$f['has_diploma_wording'] && $score > 0) {
            $score = min(0.3, $score);
            $signals[] = 'GATE: absence certification explicite';
        }
        
        return ['score' => $score, 'signals' => $signals];
    }
    
    private function scoreRIB(array $f): array
    {
        $score = 0.0;
        $signals = [];
        
        if ($f['has_iban']) { $score += 0.8; $signals[] = 'FORT: pattern IBAN'; }
        if ($f['has_rib_wording']) { $score += 0.7; $signals[] = 'EXCLUSIF: RIB explicite'; }
        
        // RIB = document ultra-court et très structuré
        if ($f['is_short']) { $score += 0.3; $signals[] = 'pattern: document court'; }
        
        // Gate: besoin d'au moins 1 signal fort
        if ($score < 0.5) return ['score' => 0.0, 'signals' => []];
        
        return ['score' => min(1.0, $score), 'signals' => $signals];
    }
    
    private function scoreFacture(array $f): array
    {
        $score = 0.0;
        $signals = [];
        $strongSignals = 0;
        
        // Signaux FORTS (discriminants Facture vs Devis)
        if ($f['has_invoice_number']) { $score += 0.7; $signals[] = 'FORT: N° facture'; $strongSignals++; }
        if ($f['has_payment_due_date']) { $score += 0.5; $signals[] = 'FORT: échéance paiement'; $strongSignals++; }
        if ($f['has_net_to_pay']) { $score += 0.5; $signals[] = 'FORT: net à payer'; $strongSignals++; }
        if ($f['has_payment_terms']) { $score += 0.4; $signals[] = 'conditions règlement'; $strongSignals++; }
        
        // Signaux MOYENS (partagés avec Devis)
        if ($f['has_vat_breakdown']) { $score += 0.25; $signals[] = 'ventilation TVA'; }
        if ($f['has_supplier_info']) { $score += 0.2; $signals[] = 'infos fournisseur'; }
        if ($f['has_itemized_list']) { $score += 0.2; $signals[] = 'liste prestations'; }

        // Signaux EXCLUSIFS devis
        if ($f['has_quote_number']) { $score += 0.7; $signals[] = 'FORT: N° devis'; $strongSignals++; }
        if ($f['has_quote_acceptance']) { $score += 0.5; $signals[] = 'FORT: bon pour accord'; $strongSignals++; }
        if ($f['has_estimate_wording']) { $score += 0.4; $signals[] = 'wording: devis/estimation'; $strongSignals++; }
        

        if ($f['has_supplier_info']) { $score += 0.15; $signals[] = 'infos fournisseur'; }
        
        // Filename bonus
        if (str_contains($f['filename'], 'devis') || str_contains($f['filename'], 'quote')) {
            $score += 0.25; $signals[] = 'filename: devis';
        }
        
        // Filename bonus
        if (str_contains($f['filename'], 'facture') || str_contains($f['filename'], 'invoice')) {
            $score += 0.2; $signals[] = 'filename: facture';
        }
        
        // GATE: sans signal fort, cap à 0.4
        if ($strongSignals === 0) {
            $score = min(0.35, $score);
            $signals[] = 'GATE: manque signaux forts facture';
        } elseif ($strongSignals === 1) {
            $score = min(0.65, $score);
        }
        
        return ['score' => $score, 'signals' => $signals];
    }
    

    
    private function scoreRecu(array $f): array
    {
        $score = 0.0;
        $signals = [];
        
        // Signaux EXCLUSIFS ticket/reçu
        if ($f['has_receipt_wording']) { $score += 0.7; $signals[] = 'EXCLUSIF: ticket/reçu'; }
        if ($f['has_cb_payment']) { $score += 0.6; $signals[] = 'FORT: paiement CB/TPE'; }
        if ($f['has_thank_you']) { $score += 0.3; $signals[] = 'merci visite'; }
        
        
        // Filename
        if (str_contains($f['filename'], 'recu') || str_contains($f['filename'], 'ticket')) {
            $score += 0.2; $signals[] = 'filename: reçu';
        }
        
        // GATE: besoin d'au moins 1 signal exclusif
        if (!$f['has_receipt_wording'] && !$f['has_cb_payment']) {
            $score = min(0.25, $score);
            $signals[] = 'GATE: absence signaux reçu';
        }
        
        // Anti-signal: numéro facture => pas un simple reçu
        if ($f['has_invoice_number']) {
            $score *= 0.3;
            $signals[] = 'PENALITE: numéro facture présent';
        }
        
        return ['score' => $score, 'signals' => $signals];
    }
    
    private function scoreContrat(array $f): array
    {
        $score = 0.0;
        $signals = [];
        
        if ($f['has_contract_structure']) { $score += 0.8; $signals[] = 'FORT: structure contrat'; }
        if ($f['has_legal_clauses']) { $score += 0.5; $signals[] = 'clauses juridiques'; }
        if ($f['has_signature_block']) { $score += 0.3; $signals[] = 'bloc signature'; }
        
        // Généralement long
        if ($f['is_long']) { $score += 0.2; $signals[] = 'pattern: document long'; }
        
        // Filename
        if (str_contains($f['filename'], 'contrat') || str_contains($f['filename'], 'bail')) {
            $score += 0.2; $signals[] = 'filename: contrat';
        }
        
        // Gate
        if ($score < 0.4) return ['score' => 0.0, 'signals' => []];
        
        return ['score' => $score, 'signals' => $signals];
    }
    
    
    private function scoreLettre(array $f): array
    {
        $score = 0.0;
        $signals = [];
        
        if ($f['has_email_headers']) { $score += 0.9; $signals[] = 'EXCLUSIF: headers email'; }
        if ($f['has_greeting']) { $score += 0.9; $signals[] = 'salutations'; }
        
        // Gate
        if ($score < 0.4) return ['score' => 0.0, 'signals' => []];
        
        return ['score' => $score, 'signals' => $signals];
    }


    private function scoreSante(array $f): array
    {
        $score = 0.0;
        $signals = [];
        
        if ($f['has_health_wording']) { $score += 0.8; $signals[] = 'EXCLUSIF: mentions santé'; }
        
        // Filename bonus
        if (str_contains($f['filename'], 'sante') || str_contains($f['filename'], 'medical') || str_contains($f['filename'], 'maladie')) {
            $score += 0.2; $signals[] = 'filename: santé';
        }
        
        // Gate: besoin d'au moins 1 signal exclusif
        if (!$f['has_health_wording']) {
            $score = min(0.2, $score);
            $signals[] = 'GATE: absence wording santé';
        }
        
        return ['score' => $score, 'signals' => $signals];
    }

    private function scoreAdministratif(array $f): array
    {
        $score = 0.0;
        $signals = [];
        
        if ($f['has_admin_wording']) { $score += 0.6; $signals[] = 'vocabulaire admin'; }
        if ($f['has_form_number']) { $score += 0.5; $signals[] = 'numéro formulaire'; }
        
        // Catch-all faible
        if ($f['has_date'] && !$f['has_currency_amount']) {
            $score += 0.2; $signals[] = 'pattern: admin générique';
        }
        
        return ['score' => $score, 'signals' => $signals];
    }

    // ========================================
    // CONTRAINTES GLOBALES
    // ========================================
    private function applyGlobalConstraints(array &$results, array $f): void
    {
        // Si email/lettre détecté => pénaliser les docs commerciaux
        if ($results['Lettres et Emails']['score'] > 0.5) {
            $results['Factures et Quittances']['score'] *= 0.3;
            $results['Mes contrats']['score'] *= 0.5;
            $results['Factures et Quittances']['signals'][] = 'PENALITE: style lettre';
        }
        
        
    }

    // ========================================
    // SÉLECTION FINALE
    // ========================================
    private function selectBest(array $results): array
    {
        $maxScore = 0.0;
        $candidates = [];
        
        foreach ($results as $cat => $data) {
            $s = $data['score'];
            if ($s > $maxScore) {
                $maxScore = $s;
                $candidates = [$cat];
            } elseif ($s === $maxScore && $s > 0) {
                $candidates[] = $cat;
            }
        }
        
        // Pas de signal significatif
        if ($maxScore < 0.15) {
            return [
                'category' => 'Autres',
                'confidence' => 0.05,
                'signals' => ['aucun signal significatif'],
            ];
        }
        
        // Tie-break par priorité
        if (count($candidates) > 1) {
            usort($candidates, fn($a, $b) => self::CATEGORIES[$b] <=> self::CATEGORIES[$a]);
        }
        
        $best = $candidates[0];
        $confidence = max(0.0, min(1.0, $maxScore));
        
        return [
            'category' => $best,
            'confidence' => $confidence,
            'signals' => $results[$best]['signals'],
        ];
    }

    // ========================================
    // DÉTECTEURS DE Fiches de paies (optimisés)
    // ========================================

    private function hasPayslipWording(string $t): bool
    {
        return $this->hasAny($t, ['bulletin de salaire','bulletin de paie','bulletin de paye', 'Charges patronales', 'Sécurité Sociale plafonnée','Net à payer avant impôt sur le revenu', 'Autres contributions dues par l\'employeur', 'bulletin de paie', 'fiche de paie', 'salaire brut', 'salaire net', 'net imposable', 'net a payer avant impot']);
    }
    
    private function hasSalaryBreakdown(string $t): bool
    {
        $count = 0;
        foreach (['salaire de base','Montant net social', 'heures supplémentaires', 'avantages en nature'] as $term) {
            if (str_contains($t, $this->normalize($term))) $count++;
        }
        return $count >= 2;
    }
    
    private function hasSocialCharges(string $t): bool
    {
        return $this->hasAny($t, ['urssaf/Msa']);
    }


    // ========================================
    // DÉTECTEURS DE FACTURES (optimisés)
    // ========================================
    
    private function hasInvoiceNumber(string $t): bool
    {
        return (bool)preg_match('/\b(facture|invoice|fact)\s*(n°?|no|num|numero|number)\s*[:\-]?\s*[a-z0-9]{3,}/ui', $t);
    }
    
    private function hasQuoteNumber(string $t): bool
    {
        return (bool)preg_match('/\b(devis|quote)\s*(n°?|no|num|numero|number)\s*[:\-]?\s*[a-z0-9]{3,}/ui', $t);
    }
    
    private function hasPaymentDueDate(string $t): bool
    {
        return $this->hasAny($t, ['facture','quittance','date d echeance', 'echeance', 'echeance de paiement', 'a regler avant le', 'payable avant le']);
    }

        private function hasClientAddress(string $t): bool
    {
        return $this->hasAny($t, ['adresse de facturation', 'adresse de livraison', 'client', 'facture a']);
    }
    
    private function hasItemizedList(string $raw): bool
    {
        // Détecte un tableau de lignes : Désignation | Qté | PU | Total
        return (bool)preg_match('/\b(designation|description|libelle|article|prestation)\b.*(quantite|qte|qty).*(prix|montant|total)/ius', $raw);
    }
    
    // Inchangés ou légèrement optimisés
    private function hasSupplierInfo(string $t): bool
    {
        $count = 0;
        foreach (['siret', 'siren', 'rcs', 'tva intracommunautaire', 'capital social', 'code ape', 'naf'] as $term) {
            if (str_contains($t, $this->normalize($term))) $count++;
        }
        return $count >= 2;
    }
    
    private function hasNetToPay(string $t): bool
    {
        return $this->hasAny($t, ['net a payer', 'montant net a payer', 'solde a payer', 'reste a payer', 'total a regler']);
    }
    
    private function hasPaymentTerms(string $t): bool
    {
        return $this->hasAny($t, ['conditions de reglement', 'mode de reglement', 'delai de paiement', 'penalites de retard', 'escompte']);
    }
    
    private function hasVatBreakdown(string $t): bool
    {
        $count = 0;
        foreach (['base ht', 'taux tva', 'montant tva', 'total tva', 'tva 20', 'tva 10', 'tva 5'] as $term) {
            if (str_contains($t, $this->normalize($term))) $count++;
        }
        return $count >= 2;
    }


    // ========================================
    // DÉTECTEURS DE RECU (optimisés)
    // ========================================


    private function hasCBPayment(string $t): bool
    {
        return $this->hasAny($t, [' cb ', 'payement par carte bancaire', 'tpe']);
    }
    
    private function hasReceiptWording(string $t): bool
    {
        return $this->hasAny($t, ['Votre reçu','Numéro du reçu',' ticket de caisse ', 'justificatif d achat','billet', 'reçu de paiement']);
    }


    // ========================================
    // DÉTECTEURS DE Diplômes (optimisés)
    // ========================================

    private function hasDiplomaWording(string $t): bool
    {
        return $this->hasAny($t, ['diplome','relevé de notes','notes et resultats','semestre', 'bulletin de notes', 'lycée', 'baccalaureat', 'a obtenu le diplome','diplome national','bulletin de notes', 'attestation de réussite', 'certificat de scolarité', 'certificat de réussite','année universitaire']);
    }
    
    private function hasAcademicInstitution(string $t): bool
    {
        return $this->hasAny($t, ['universite', 'université', 'ecole', 'école', 'institut', 'academie', 'académie', 'lycee', 'lycée', 'college', 'collège']);
    }
    
    private function hasGradeMention(string $t): bool
    {
        return $this->hasAny($t, ['master', 'licence', 'doctorat', 'baccalaureat']);
    }
    

    // ========================================
    // DÉTECTEURS DE L'ADMINISTRATIF (optimisés)
    // ========================================

    private function hasAdminWording(string $t): bool
    {
        return $this->hasAny($t, ['ministere','titre de sejour',' visa ','outre-mer','gouv.fr','prefecture','direction generale','finances publiques','republique française', 'préfecture', 'administration', 'cerfa','numero de dossier']);
    }
    
    private function hasFormNumber(string $t): bool
    {
        return (bool)preg_match('/\bcerfa\s*(n°?|no)?\s*\d{5}/ui', $t);
    }

    // ========================================
    // DÉTECTEURS DE Lettres et Emails (optimisés)
    // ========================================

    private function hasEmailHeaders(string $raw): bool
    {
        return (bool)preg_match('/^(from:|de:|to:|a:|subject:|objet:)/mi', $raw);
    }
    
    private function hasGreeting(string $t): bool
    {
        return $this->hasAny($t, ['bonjour', 'bonsoir', 'salut', 'cher', 'chere',' de vous adresser ', 'cordialement', 'bien a vous', 'sincères salutations']);
    }



    // ========================================
    // DÉTECTEURS DE Contract (optimisés)
    // ========================================

    private function hasContractStructure(string $t): bool
    {
        $hasIntro = $this->hasAny($t, ['entre les soussignes', 'entre les soussignés', 'contrat', 'bail']);
        $hasArticles = (bool)preg_match('/\barticle\s+\d+/ui', $t);
        return $hasIntro && $hasArticles;
    }

    
    private function hasLegalClauses(string $t): bool
    {
        $count = 0;
        foreach (['clause', 'article', 'resiliation', 'résiliation', 'conditions generales', 'conditions particulieres', 'engagement', 'duree', 'durée'] as $term) {
            if (str_contains($t, $this->normalize($term))) $count++;
        }
        return $count >= 3;
    }
    
    private function hasSignatureBlock(string $t): bool
    {
        return $this->hasAny($t, ['signature', 'signe a', 'signé à', 'lu et approuve', 'fait a', 'fait à']);
    }
    

    private function hasHealthWording(string $t): bool
    {
    return $this->hasAny($t, ['santé','maladie', 'assurance maladie','medecin','certificat medical', 'attestation de sante', 'rapport medical', 'ordonnance', 'prescription', 'resultats medical', 'analyse medicale']);
    }



    private function hasValidityPeriod(string $t): bool
    {
        return $this->hasAny($t, ['valable', 'validite', 'valable jusqu', 'duree de validite', 'valable 30 jours', 'offre valable']);
    }
    
    private function hasQuoteAcceptance(string $t): bool
    {
        return $this->hasAny($t, ['bon pour accord', 'signature du client', 'acceptation du devis', 'lu et approuve']);
    }
    
    private function hasEstimateWording(string $t): bool
    {
        return $this->hasAny($t, [' devis ', ' estimation ', 'proposition commerciale', 'proposition tarifaire']);
    }
         


    private function hasTableStructure(string $raw): bool
    {
        // Détecte présence de séparateurs type tableau
        $tabCount = substr_count($raw, "\t");
        $pipeCount = substr_count($raw, "|");
        return $tabCount > 10 || $pipeCount > 15;
    }
 
    


    
    private function hasCurrencyAmount(string $t): bool
    {
        return (bool)preg_match('/\d{1,3}(?:[\s.,]\d{3})*(?:[.,]\d{2})?\s*€/u', $t);
    }
    
    private function hasDateLike(string $t): bool
    {
        return (bool)preg_match('/\d{2}[\/\-]\d{2}[\/\-]\d{4}/', $t);
    }
    

    
    private function hasIbanPattern(string $t): bool
    {
        return (bool)preg_match('/\b[a-z]{2}\d{2}(?:\s?[a-z0-9]{4}){3,7}\b/i', $t);
    }
    
    private function normalize(string $text): string
    {
        $t = mb_strtolower($text);
        $t = strtr($t, [
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'à' => 'a', 'â' => 'a', 'ä' => 'a',
            'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ö' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c',
             '"' => '"'
        ]);
        return $t;
    }
    
    private function hasAny(string $text, array $needles): bool
    {
        foreach ($needles as $n) {
            if (str_contains($text, $this->normalize($n))) return true;
        }
        return false;
    }
}
