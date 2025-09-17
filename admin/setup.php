<?php
/* Copyright (C) 2024 - Search Duplicates Module for Dolibarr */

// Load Dolibarr environment
if (!defined('DOL_DOCUMENT_ROOT')) {
    $res = 0;
    if (!$res && file_exists("../../../main.inc.php")) {
        $res = @include "../../../main.inc.php";
    }
    if (!$res && file_exists("../../main.inc.php")) {
        $res = @include "../../main.inc.php";
    }
    if (!$res && file_exists("../main.inc.php")) {
        $res = @include "../main.inc.php";
    }
    if (!$res && file_exists("main.inc.php")) {
        $res = @include "main.inc.php";
    }
}

// Access control
if (!$user->admin) {
    accessforbidden();
}

// Fix HTTP_REFERER warning
if (!isset($_SERVER['HTTP_REFERER'])) {
    $_SERVER['HTTP_REFERER'] = '';
}

// Load module libraries
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';

// Load module class
require_once DOL_DOCUMENT_ROOT.'/custom/searchduplicates/core/modules/modSearchDuplicates.class.php';

// Load module
$module = new modSearchDuplicates($db);

// Parameters
$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');

// Configuration parameters
$search_duplicates_enable_auto_scan = GETPOST('search_duplicates_enable_auto_scan', 'int');
$search_duplicates_similarity_threshold = GETPOST('search_duplicates_similarity_threshold', 'int');
$search_duplicates_scan_fields = GETPOST('search_duplicates_scan_fields', 'alpha');
$search_duplicates_notification_email = GETPOST('search_duplicates_notification_email', 'email');
$search_duplicates_language = GETPOST('search_duplicates_language', 'aZ09');
$search_duplicates_algorithm = GETPOST('search_duplicates_algorithm', 'aZ09');

// Set default values if empty
if (empty($search_duplicates_enable_auto_scan)) {
    $search_duplicates_enable_auto_scan = $conf->global->SEARCH_DUPLICATES_ENABLE_AUTO_SCAN ?? 1;
}
if (empty($search_duplicates_similarity_threshold)) {
    $search_duplicates_similarity_threshold = $conf->global->SEARCH_DUPLICATES_SIMILARITY_THRESHOLD ?? 80;
}
if (empty($search_duplicates_scan_fields)) {
    $search_duplicates_scan_fields = $conf->global->SEARCH_DUPLICATES_SCAN_FIELDS ?? 'name,description,ref';
}
if (empty($search_duplicates_notification_email)) {
    $search_duplicates_notification_email = $conf->global->SEARCH_DUPLICATES_NOTIFICATION_EMAIL ?? '';
}
if (empty($search_duplicates_language)) {
    $search_duplicates_language = $conf->global->SEARCH_DUPLICATES_LANGUAGE ?? 'es_ES';
}
if (empty($search_duplicates_algorithm)) {
    $search_duplicates_algorithm = $conf->global->SEARCH_DUPLICATES_ALGORITHM ?? 'hybrid';
}

// Set language for interface
$current_lang = $search_duplicates_language;

// Translation function
function t($key, $lang = 'es_ES') {
    $translations = array(
        'es_ES' => array(
            'ModuleData' => 'Datos del Módulo',
            'Status' => 'Estado',
            'Version' => 'Versión',
            'Author' => 'Autor',
            'Type' => 'Tipo',
            'CustomModule' => 'Módulo Personalizado',
            'Enabled' => 'Activado',
            'Disabled' => 'Desactivado',
            'QuickActions' => 'Acciones Rápidas',
            'AccessModule' => 'Acceder al Módulo',
            'ModuleManagement' => 'Gestión del Módulo',
            'DeleteModule' => 'Eliminar Módulo',
            'LanguageConfig' => 'Configuración de Idioma',
            'SelectLanguage' => 'Seleccione el Lenguaje:',
            'ConfigurationTitle' => 'Configuración de SearchDuplicates',
            'EnableAutoScan' => 'Habilitar Escaneo Automático',
            'EnableAutoScanDesc' => 'Activa el escaneo automático de productos duplicados',
            'SimilarityThreshold' => 'Umbral de Similitud',
            'SimilarityThresholdDesc' => 'Porcentaje de similitud para considerar productos duplicados (0-100%)',
            'ScanFields' => 'Campos a Escanear',
            'ScanFieldsDesc' => 'Campos separados por comas para buscar duplicados',
            'NotificationEmail' => 'Email de Notificación',
            'NotificationEmailDesc' => 'Email para recibir notificaciones de duplicados encontrados',
            'Algorithm' => 'Algoritmo de Búsqueda',
            'AlgorithmDesc' => 'Selecciona el algoritmo para detectar productos duplicados',
            'ExactMatch' => 'Coincidencia Exacta (EAN/UPC, SKU, Referencia)',
            'ExactMatchDesc' => 'La más fiable y rápida. Detecta duplicados exactos por códigos únicos.',
            'Levenshtein' => 'Distancia de Edición (Levenshtein)',
            'LevenshteinDesc' => 'Detecta errores tipográficos y variaciones en nombres.',
            'Ngrams' => 'Similitud por N-gramas/Trigramas',
            'NgramsDesc' => 'Divide palabras en fragmentos. Muy eficiente con PostgreSQL.',
            'TokenBased' => 'Token-based (Jaccard, Dice)',
            'TokenBasedDesc' => 'Compara conjuntos de palabras sin importar el orden.',
            'Vectorization' => 'Vectorización (TF-IDF + Cosine)',
            'VectorizationDesc' => 'Convierte texto a vectores para similitud semántica.',
            'Hybrid' => 'Híbrido (Combinación de todos)',
        'SimilarityThreshold' => 'Umbral de Similitud',
        'SimilarityThresholdDesc' => 'Porcentaje mínimo de similitud para considerar productos como duplicados (50-100%)',
            'HybridDesc' => 'Utiliza múltiples algoritmos para máxima precisión.'
        ),
        'en_US' => array(
            'ModuleData' => 'Module Data',
            'Status' => 'Status',
            'Version' => 'Version',
            'Author' => 'Author',
            'Type' => 'Type',
            'CustomModule' => 'Custom Module',
            'Enabled' => 'Enabled',
            'Disabled' => 'Disabled',
            'QuickActions' => 'Quick Actions',
            'AccessModule' => 'Access Module',
            'ModuleManagement' => 'Module Management',
            'DeleteModule' => 'Delete Module',
            'LanguageConfig' => 'Language Configuration',
            'SelectLanguage' => 'Select Language:',
            'ConfigurationTitle' => 'SearchDuplicates Configuration',
            'EnableAutoScan' => 'Enable Automatic Scan',
            'EnableAutoScanDesc' => 'Activates automatic scanning of duplicate products',
            'SimilarityThreshold' => 'Similarity Threshold',
            'SimilarityThresholdDesc' => 'Percentage of similarity to consider duplicate products (0-100%)',
            'ScanFields' => 'Fields to Scan',
            'ScanFieldsDesc' => 'Comma-separated fields to search for duplicates',
            'NotificationEmail' => 'Notification Email',
            'NotificationEmailDesc' => 'Email to receive notifications of found duplicates',
            'Algorithm' => 'Search Algorithm',
            'AlgorithmDesc' => 'Select the algorithm to detect duplicate products',
            'ExactMatch' => 'Exact Match (EAN/UPC, SKU, Reference)',
            'ExactMatchDesc' => 'Most reliable and fast. Detects exact duplicates by unique codes.',
            'Levenshtein' => 'Edit Distance (Levenshtein)',
            'LevenshteinDesc' => 'Detects typos and variations in names.',
            'Ngrams' => 'N-grams/Trigrams Similarity',
            'NgramsDesc' => 'Divides words into fragments. Very efficient with PostgreSQL.',
            'TokenBased' => 'Token-based (Jaccard, Dice)',
            'TokenBasedDesc' => 'Compares word sets regardless of order.',
            'Vectorization' => 'Vectorization (TF-IDF + Cosine)',
            'VectorizationDesc' => 'Converts text to vectors for semantic similarity.',
            'Hybrid' => 'Hybrid (Combination of all)',
            'SimilarityThreshold' => 'Similarity Threshold',
            'SimilarityThresholdDesc' => 'Minimum similarity percentage to consider products as duplicates (50-100%)',
            'HybridDesc' => 'Uses multiple algorithms for maximum precision.'
        ),
        'fr_FR' => array(
            'ModuleData' => 'Données du Module',
            'Status' => 'Statut',
            'Version' => 'Version',
            'Author' => 'Auteur',
            'Type' => 'Type',
            'CustomModule' => 'Module Personnalisé',
            'Enabled' => 'Activé',
            'Disabled' => 'Désactivé',
            'QuickActions' => 'Actions Rapides',
            'AccessModule' => 'Accéder au Module',
            'ModuleManagement' => 'Gestion du Module',
            'DeleteModule' => 'Supprimer le Module',
            'LanguageConfig' => 'Configuration de la Langue',
            'SelectLanguage' => 'Sélectionnez la Langue:',
            'ConfigurationTitle' => 'Configuration SearchDuplicates',
            'EnableAutoScan' => 'Activer le Scan Automatique',
            'EnableAutoScanDesc' => 'Active le scan automatique des produits dupliqués',
            'SimilarityThreshold' => 'Seuil de Similarité',
            'SimilarityThresholdDesc' => 'Pourcentage de similarité pour considérer les produits dupliqués (0-100%)',
            'ScanFields' => 'Champs à Scanner',
            'ScanFieldsDesc' => 'Champs séparés par des virgules pour rechercher les doublons',
            'NotificationEmail' => 'Email de Notification',
            'NotificationEmailDesc' => 'Email pour recevoir les notifications de doublons trouvés',
            'Algorithm' => 'Algorithme de Recherche',
            'AlgorithmDesc' => 'Sélectionnez l\'algorithme pour détecter les produits dupliqués',
            'ExactMatch' => 'Correspondance Exacte (EAN/UPC, SKU, Référence)',
            'ExactMatchDesc' => 'Le plus fiable et rapide. Détecte les doublons exacts par codes uniques.',
            'Levenshtein' => 'Distance d\'Édition (Levenshtein)',
            'LevenshteinDesc' => 'Détecte les fautes de frappe et variations dans les noms.',
            'Ngrams' => 'Similarité par N-grammes/Trigrammes',
            'NgramsDesc' => 'Divise les mots en fragments. Très efficace avec PostgreSQL.',
            'TokenBased' => 'Token-based (Jaccard, Dice)',
            'TokenBasedDesc' => 'Compare les ensembles de mots sans tenir compte de l\'ordre.',
            'Vectorization' => 'Vectorisation (TF-IDF + Cosine)',
            'VectorizationDesc' => 'Convertit le texte en vecteurs pour la similarité sémantique.',
            'Hybrid' => 'Hybride (Combinaison de tous)',
            'HybridDesc' => 'Utilise plusieurs algorithmes pour une précision maximale.'
        ),
        'de_DE' => array(
            'ModuleData' => 'Modul-Daten',
            'Status' => 'Status',
            'Version' => 'Version',
            'Author' => 'Autor',
            'Type' => 'Typ',
            'CustomModule' => 'Benutzerdefiniertes Modul',
            'Enabled' => 'Aktiviert',
            'Disabled' => 'Deaktiviert',
            'QuickActions' => 'Schnellaktionen',
            'AccessModule' => 'Modul Zugreifen',
            'ModuleManagement' => 'Modul-Verwaltung',
            'DeleteModule' => 'Modul Löschen',
            'LanguageConfig' => 'Sprachkonfiguration',
            'SelectLanguage' => 'Sprache Auswählen:',
            'ConfigurationTitle' => 'SearchDuplicates Konfiguration',
            'EnableAutoScan' => 'Automatischen Scan Aktivieren',
            'EnableAutoScanDesc' => 'Aktiviert den automatischen Scan nach doppelten Produkten',
            'SimilarityThreshold' => 'Ähnlichkeitsschwelle',
            'SimilarityThresholdDesc' => 'Prozentsatz der Ähnlichkeit für doppelte Produkte (0-100%)',
            'ScanFields' => 'Zu Scannende Felder',
            'ScanFieldsDesc' => 'Durch Kommas getrennte Felder zur Suche nach Duplikaten',
            'NotificationEmail' => 'Benachrichtigungs-E-Mail',
            'NotificationEmailDesc' => 'E-Mail für Benachrichtigungen über gefundene Duplikate',
            'Algorithm' => 'Suchalgorithmus',
            'AlgorithmDesc' => 'Wählen Sie den Algorithmus zur Erkennung doppelter Produkte',
            'ExactMatch' => 'Exakte Übereinstimmung (EAN/UPC, SKU, Referenz)',
            'ExactMatchDesc' => 'Zuverlässigste und schnellste Methode. Erkennt exakte Duplikate durch eindeutige Codes.',
            'Levenshtein' => 'Bearbeitungsdistanz (Levenshtein)',
            'LevenshteinDesc' => 'Erkennt Tippfehler und Variationen in Namen.',
            'Ngrams' => 'N-Gramm/Trigramm-Ähnlichkeit',
            'NgramsDesc' => 'Teilt Wörter in Fragmente auf. Sehr effizient mit PostgreSQL.',
            'TokenBased' => 'Token-basiert (Jaccard, Dice)',
            'TokenBasedDesc' => 'Vergleicht Wortsets unabhängig von der Reihenfolge.',
            'Vectorization' => 'Vektorisierung (TF-IDF + Cosine)',
            'VectorizationDesc' => 'Konvertiert Text zu Vektoren für semantische Ähnlichkeit.',
            'Hybrid' => 'Hybrid (Kombination aller)',
            'HybridDesc' => 'Verwendet mehrere Algorithmen für maximale Präzision.'
        )
    );
    
    return isset($translations[$lang][$key]) ? $translations[$lang][$key] : $key;
}

// Initialize technical objects
$form = new Form($db);

// Load language files
$langs->load("search_duplicates@search_duplicates");

// Process form submission
if ($action == 'update') {
    $error = 0;
    
    // Validate and save configuration
    if ($search_duplicates_similarity_threshold < 0 || $search_duplicates_similarity_threshold > 100) {
        setEventMessages($langs->trans("ErrorSimilarityThreshold"), null, 'errors');
        $error++;
    }
    
    if (!$error) {
        // Save configuration to database
        $result = dolibarr_set_const($db, "SEARCH_DUPLICATES_ENABLE_AUTO_SCAN", $search_duplicates_enable_auto_scan, 'chaine', 0, '', $conf->entity);
        $result = dolibarr_set_const($db, "SEARCH_DUPLICATES_SIMILARITY_THRESHOLD", $search_duplicates_similarity_threshold, 'chaine', 0, '', $conf->entity);
        $result = dolibarr_set_const($db, "SEARCH_DUPLICATES_SCAN_FIELDS", $search_duplicates_scan_fields, 'chaine', 0, '', $conf->entity);
        $result = dolibarr_set_const($db, "SEARCH_DUPLICATES_NOTIFICATION_EMAIL", $search_duplicates_notification_email, 'chaine', 0, '', $conf->entity);
        $result = dolibarr_set_const($db, "SEARCH_DUPLICATES_LANGUAGE", $search_duplicates_language, 'chaine', 0, '', $conf->entity);
        $result = dolibarr_set_const($db, "SEARCH_DUPLICATES_ALGORITHM", $search_duplicates_algorithm, 'chaine', 0, '', $conf->entity);
        
        if ($result > 0) {
            setEventMessages($langs->trans("ConfigurationUpdated"), null, 'mesgs');
        } else {
            setEventMessages($langs->trans("ErrorUpdatingConfiguration"), null, 'errors');
        }
    }
}

// Process language change
if ($action == 'change_language') {
    $result = dolibarr_set_const($db, "SEARCH_DUPLICATES_LANGUAGE", $search_duplicates_language, 'chaine', 0, '', $conf->entity);
    
    if ($result > 0) {
        // Change Dolibarr language
        $conf->global->MAIN_LANG_DEFAULT = $search_duplicates_language;
        $langs->setDefaultLang($search_duplicates_language);
        
        setEventMessages("Idioma cambiado a: " . $search_duplicates_language, null, 'mesgs');
        // Reload page to apply language change
        header("Location: " . $_SERVER["PHP_SELF"]);
        exit;
    } else {
        setEventMessages("Error al cambiar el idioma", null, 'errors');
    }
}

// Process module deletion
if ($action == 'delete_module') {
    $confirm = GETPOST('confirm', 'alpha');
    
    if ($confirm == 'yes') {
        // Disable module first
        $result = dolibarr_set_const($db, "MAIN_MODULE_SEARCHDUPLICATES", "0", 'chaine', 0, '', $conf->entity);
        
        if ($result > 0) {
            // Remove module configuration constants
            $sql = "DELETE FROM ".MAIN_DB_PREFIX."const WHERE name LIKE 'SEARCH_DUPLICATES_%' AND entity = ".$conf->entity;
            $db->query($sql);
            
            // Remove module menus
            $sql = "DELETE FROM ".MAIN_DB_PREFIX."menu WHERE module = 'search_duplicates'";
            $db->query($sql);
            
            setEventMessages($langs->trans("ModuleDeleted"), null, 'mesgs');
            
            // Redirect to module list
            header("Location: ".DOL_URL_ROOT."/admin/modules.php");
            exit;
        } else {
            setEventMessages($langs->trans("ErrorDeletingModule"), null, 'errors');
        }
    } else {
        setEventMessages($langs->trans("ModuleDeletionCancelled"), null, 'warnings');
    }
}

// Load current configuration values
$search_duplicates_enable_auto_scan = $conf->global->SEARCH_DUPLICATES_ENABLE_AUTO_SCAN ?? 1;
$search_duplicates_similarity_threshold = $conf->global->SEARCH_DUPLICATES_SIMILARITY_THRESHOLD ?? 80;
$search_duplicates_scan_fields = $conf->global->SEARCH_DUPLICATES_SCAN_FIELDS ?? 'name,description,ref';
$search_duplicates_notification_email = $conf->global->SEARCH_DUPLICATES_NOTIFICATION_EMAIL ?? '';

// Page title
$title = $langs->trans("ModuleSetup", "SearchDuplicates");
$help_url = '';
llxHeader('', $title, $help_url);

// Subheader
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($title, $linkback, 'title_setup');

// Configuration header - Solo pestaña SearchDuplicates
$head = array();
$head[0][0] = DOL_URL_ROOT.'/custom/searchduplicates/admin/setup.php';
$head[0][1] = $langs->trans("SearchDuplicates");
$head[0][2] = 'search_duplicates';

dol_fiche_head($head, 'search_duplicates', $langs->trans("SearchDuplicates"), -1, 'search_duplicates@search_duplicates');

// Configuration form
print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update">';

print '<div class="fichecenter" style="padding: 20px;">';
print '<div class="fichehalfleft" style="padding-right: 20px;">';

print '<table class="noborder centpercent" style="border-spacing: 0; border-collapse: separate;">';
print '<tr class="liste_titre">';
print '<td colspan="3" style="padding: 15px; font-size: 16px; font-weight: bold;">'.t('ConfigurationTitle', $current_lang).'</td>';
print '</tr>';

// Auto Scan Enable
print '<tr class="oddeven" style="height: 50px;">';
print '<td style="padding: 12px 15px; vertical-align: middle;"><strong>'.t('EnableAutoScan', $current_lang).'</strong></td>';
print '<td style="padding: 12px 15px; vertical-align: middle;">';
print $form->selectyesno("search_duplicates_enable_auto_scan", $search_duplicates_enable_auto_scan, 1);
print '</td>';
print '<td style="padding: 12px 15px; vertical-align: middle;">'.t('EnableAutoScanDesc', $current_lang).'</td>';
print '</tr>';

// Similarity Threshold
print '<tr class="oddeven" style="height: 50px;">';
print '<td style="padding: 12px 15px; vertical-align: middle;"><strong>'.t('SimilarityThreshold', $current_lang).'</strong></td>';
print '<td style="padding: 12px 15px; vertical-align: middle;">';
print '<input type="number" name="search_duplicates_similarity_threshold" value="'.$search_duplicates_similarity_threshold.'" min="0" max="100" class="flat" style="width: 80px; padding: 5px;">';
print ' %';
print '</td>';
print '<td style="padding: 12px 15px; vertical-align: middle;">'.t('SimilarityThresholdDesc', $current_lang).'</td>';
print '</tr>';

// Scan Fields
print '<tr class="oddeven" style="height: 50px;">';
print '<td style="padding: 12px 15px; vertical-align: middle;"><strong>'.t('ScanFields', $current_lang).'</strong></td>';
print '<td style="padding: 12px 15px; vertical-align: middle;">';
print '<input type="text" name="search_duplicates_scan_fields" value="'.$search_duplicates_scan_fields.'" class="flat" style="width: 200px; padding: 5px;">';
print '</td>';
print '<td style="padding: 12px 15px; vertical-align: middle;">'.t('ScanFieldsDesc', $current_lang).'</td>';
print '</tr>';

// Notification Email
print '<tr class="oddeven" style="height: 50px;">';
print '<td style="padding: 12px 15px; vertical-align: middle;"><strong>'.t('NotificationEmail', $current_lang).'</strong></td>';
print '<td style="padding: 12px 15px; vertical-align: middle;">';
print '<input type="email" name="search_duplicates_notification_email" value="'.$search_duplicates_notification_email.'" class="flat" style="width: 250px; padding: 5px;">';
print '</td>';
print '<td style="padding: 12px 15px; vertical-align: middle;">'.t('NotificationEmailDesc', $current_lang).'</td>';
print '</tr>';

        // Search Algorithm
        print '<tr class="oddeven" style="height: 50px;">';
        print '<td style="padding: 12px 15px; vertical-align: middle;"><strong>'.t('Algorithm', $current_lang).'</strong></td>';
        print '<td style="padding: 12px 15px; vertical-align: middle;">';
        print '<select name="search_duplicates_algorithm" class="flat" style="width: 300px; padding: 5px;">';
        print '<option value="exact_match"'.($search_duplicates_algorithm == 'exact_match' ? ' selected' : '').'>'.t('ExactMatch', $current_lang).'</option>';
        print '<option value="levenshtein"'.($search_duplicates_algorithm == 'levenshtein' ? ' selected' : '').'>'.t('Levenshtein', $current_lang).'</option>';
        print '<option value="ngrams"'.($search_duplicates_algorithm == 'ngrams' ? ' selected' : '').'>'.t('Ngrams', $current_lang).'</option>';
        print '<option value="token_based"'.($search_duplicates_algorithm == 'token_based' ? ' selected' : '').'>'.t('TokenBased', $current_lang).'</option>';
        print '<option value="vectorization"'.($search_duplicates_algorithm == 'vectorization' ? ' selected' : '').'>'.t('Vectorization', $current_lang).'</option>';
        print '<option value="hybrid"'.($search_duplicates_algorithm == 'hybrid' ? ' selected' : '').'>'.t('Hybrid', $current_lang).'</option>';
        print '</select>';
        print '</td>';
        print '<td style="padding: 12px 15px; vertical-align: middle;">'.t('AlgorithmDesc', $current_lang).'</td>';
        print '</tr>';
        
        // Similarity Threshold
        print '<tr class="oddeven" style="height: 50px;">';
        print '<td style="padding: 12px 15px; vertical-align: middle;"><strong>'.t('SimilarityThreshold', $current_lang).'</strong></td>';
        print '<td style="padding: 12px 15px; vertical-align: middle;">';
        print '<input type="range" name="search_duplicates_similarity_threshold" min="50" max="100" value="'.$search_duplicates_similarity_threshold.'" class="flat" style="width: 200px;" oninput="this.nextElementSibling.value = this.value + \'%\'">';
        print '<span style="margin-left: 10px; font-weight: bold; color: #007bff;">'.$search_duplicates_similarity_threshold.'%</span>';
        print '</td>';
        print '<td style="padding: 12px 15px; vertical-align: middle;">'.t('SimilarityThresholdDesc', $current_lang).'</td>';
        print '</tr>';


print '</table>';

print '</div>';
print '<div class="fichehalfright" style="width: 20%; padding-left: 10px; margin-top: 0px; padding-top: 0;">';

// Datos del módulo (arriba)
print '<div class="info" style="margin-bottom: 15px; padding: 15px;">';
print '<h4 style="font-size: 14px; margin-bottom: 12px; color: #333;">'.t('ModuleData', $current_lang).'</h4>';
print '<table class="noborder" style="font-size: 12px; width: 100%;">';
print '<tr style="height: 25px;"><td style="padding: 3px 0;"><strong>'.t('Status', $current_lang).':</strong></td><td style="padding: 3px 0;">'.($conf->global->MAIN_MODULE_SEARCHDUPLICATES ? '<span style="color: green; font-weight: bold;">'.t('Enabled', $current_lang).'</span>' : '<span style="color: red; font-weight: bold;">'.t('Disabled', $current_lang).'</span>').'</td></tr>';
print '<tr style="height: 25px;"><td style="padding: 3px 0;"><strong>'.t('Version', $current_lang).':</strong></td><td style="padding: 3px 0;">1.22.0</td></tr>';
print '<tr style="height: 25px;"><td style="padding: 3px 0;"><strong>'.t('Author', $current_lang).':</strong></td><td style="padding: 3px 0;">Antonio Benalcázar</td></tr>';
print '<tr style="height: 25px;"><td style="padding: 3px 0;"><strong>'.t('Type', $current_lang).':</strong></td><td style="padding: 3px 0;">'.t('CustomModule', $current_lang).'</td></tr>';
print '</table>';
print '</div>';

// Selección de idioma (medio)
print '<div class="info" style="margin-bottom: 15px; padding: 15px; border: 1px solid #e9ecef; border-radius: 6px; background: #f8f9fa;">';
print '<h4 style="font-size: 14px; margin-bottom: 12px; color: #333; border-bottom: 1px solid #dee2e6; padding-bottom: 8px;">'.t('LanguageConfig', $current_lang).'</h4>';
print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" style="margin: 0;">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="change_language">';
print '<p style="margin: 8px 0; font-size: 13px; font-weight: bold; color: #555;">'.t('SelectLanguage', $current_lang).'</p>';
print '<p style="margin: 8px 0;">';
print '<select name="search_duplicates_language" class="flat" style="width: 100%; padding: 8px; border: 1px solid #ced4da; border-radius: 4px; font-size: 13px;" onchange="this.form.submit()">';
print '<option value="es_ES"'.($search_duplicates_language == 'es_ES' ? ' selected' : '').'>Español (es_ES)</option>';
print '<option value="en_US"'.($search_duplicates_language == 'en_US' ? ' selected' : '').'>English (en_US)</option>';
print '<option value="fr_FR"'.($search_duplicates_language == 'fr_FR' ? ' selected' : '').'>Français (fr_FR)</option>';
print '<option value="de_DE"'.($search_duplicates_language == 'de_DE' ? ' selected' : '').'>Deutsch (de_DE)</option>';
print '</select>';
print '</p>';
print '</form>';
print '</div>';

// Acciones rápidas (medio)
print '<div class="info" style="margin-bottom: 15px; padding: 15px; border: 1px solid #e9ecef; border-radius: 6px; background: #f8f9fa;">';
print '<h4 style="font-size: 14px; margin-bottom: 12px; color: #333; border-bottom: 1px solid #dee2e6; padding-bottom: 8px;">'.t('QuickActions', $current_lang).'</h4>';
print '<p style="margin: 8px 0;"><a href="'.DOL_URL_ROOT.'/custom/searchduplicates/index.php" class="button" style="background: #007bff; color: white; font-size: 13px; padding: 10px 15px; display: block; text-align: center; text-decoration: none; border-radius: 5px; font-weight: bold; transition: background 0.3s;">'.t('AccessModule', $current_lang).'</a></p>';
print '</div>';

// Botón de eliminar módulo (abajo)
print '<div class="info" style="margin-bottom: 15px; padding: 15px; border: 1px solid #e9ecef; border-radius: 6px; background: #f8f9fa; text-align: center;">';
print '<h4 style="font-size: 14px; margin-bottom: 12px; color: #333; border-bottom: 1px solid #dee2e6; padding-bottom: 8px;">'.t('ModuleManagement', $current_lang).'</h4>';
print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" onsubmit="return confirm(\'¿Está seguro de que desea eliminar este módulo?\')" style="margin: 0;">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="delete_module">';
print '<input type="hidden" name="confirm" value="yes">';
print '<input type="submit" class="butActionDelete" value="'.t('DeleteModule', $current_lang).'" style="background: #dc3545; color: white; font-size: 13px; padding: 10px 15px; width: 100%; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; transition: background 0.3s;">';
print '</form>';
print '</div>';

print '</div>';
print '</div>';

print '</form>';

// Sin botones de navegación

// Close page
llxFooter();
?>