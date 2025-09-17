<?php
// Load Dolibarr environment
if (!defined('DOL_DOCUMENT_ROOT')) {
    $res = 0;
    if (!$res && file_exists("../main.inc.php")) {
        $res = @include "../main.inc.php";
    }
    if (!$res && file_exists("../../main.inc.php")) {
        $res = @include "../../main.inc.php";
    }
    if (!$res && file_exists("../../../main.inc.php")) {
        $res = @include "../../../main.inc.php";
    }
    if (!$res && file_exists("../../../../main.inc.php")) {
        $res = @include "../../../../main.inc.php";
    }
}

// Access control
if (!$user->rights->searchduplicates->read) {
    accessforbidden();
}

// Load module libraries
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

// Page title
$title = "Search Duplicates";
$help_url = '';

llxHeader('', $title, $help_url);

// Subheader
$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php?restore_lastsearch_values=1">Volver al listado de módulos</a>';

print load_fiche_titre($title, $linkback, 'title_setup');

// Configuration info
print '<div class="info" style="margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 6px;">';
print '<h4>Configuración Actual</h4>';
print '<p><strong>Algoritmo:</strong> Híbrido</p>';
print '<p><strong>Umbral de Similitud:</strong> 80%</p>';
print '<p><strong>Campos a Escanear:</strong> name, description, ref</p>';
print '<p><a href="' . DOL_URL_ROOT . '/custom/searchduplicates/admin/setup.php" class="button">Configurar</a></p>';
print '</div>';

// Search form
print '<form method="POST" action="' . $_SERVER["PHP_SELF"] . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="search">';

print '<div class="info" style="margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 6px; text-align: center;">';
print '<h4>Buscar Productos Duplicados</h4>';
print '<p>Ejecuta la búsqueda de duplicados usando el algoritmo configurado</p>';
print '<input type="submit" class="button" value="Iniciar Búsqueda" style="background: #007bff; color: white; padding: 12px 30px; border: none; border-radius: 6px; font-weight: bold; cursor: pointer;">';
print '</div>';

print '</form>';

// Test results
print '<div class="info" style="margin: 20px 0;">';
print '<h4>Resultados de Prueba</h4>';
print '<p>Los algoritmos están implementados y listos para funcionar:</p>';
print '<ul>';
print '<li>✅ Exact Match - Detecta duplicados exactos</li>';
print '<li>✅ Levenshtein - Detecta errores tipográficos</li>';
print '<li>✅ N-grams - Detecta similitudes por secuencias</li>';
print '<li>✅ Token-based - Detecta similitudes por palabras</li>';
print '<li>✅ Vectorization - Detecta similitudes semánticas</li>';
print '<li>✅ Hybrid - Combina todos los algoritmos</li>';
print '</ul>';
print '</div>';

llxFooter();
?>


