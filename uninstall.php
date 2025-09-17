<?php
/* Copyright (C) 2024 - Search Duplicates Module for Dolibarr */

// Load Dolibarr environment
if (!defined('DOL_DOCUMENT_ROOT')) {
    $res = 0;
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

// Load module libraries
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

// Load module
$module = new modSearchDuplicates($db);

// Parameters
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');

// Page title
$title = "Desinstalar Módulo SearchDuplicates";
llxHeader('', $title);

// Subheader
print load_fiche_titre($title, '', 'title_setup');

print '<div class="fichecenter">';
print '<div class="fichehalfleft">';

if ($action == 'uninstall' && $confirm == 'yes') {
    // Desinstalar módulo
    print '<div class="info">';
    print '<h4>Desinstalando módulo...</h4>';
    print '</div>';
    
    // Desactivar constante
    $sql = "UPDATE llx_const SET value = 0 WHERE name = 'MAIN_MODULE_SEARCHDUPLICATES'";
    $db->query($sql);
    
    // Eliminar menús
    $sql = "DELETE FROM llx_menu WHERE mainmenu = 'search_duplicates'";
    $db->query($sql);
    
    // Eliminar permisos
    $sql = "DELETE FROM llx_rights_def WHERE module = 'search_duplicates'";
    $db->query($sql);
    
    // Eliminar constantes del módulo
    $sql = "DELETE FROM llx_const WHERE name LIKE 'MAIN_MODULE_SEARCHDUPLICATES%'";
    $db->query($sql);
    
    print '<div class="ok">';
    print '<h4>✅ Módulo desinstalado correctamente</h4>';
    print '<p>El módulo SearchDuplicates ha sido desinstalado de la base de datos.</p>';
    print '</div>';
    
    print '<div class="info">';
    print '<h4>⚠️ Archivos del módulo</h4>';
    print '<p>Los archivos del módulo siguen en el servidor. Para eliminarlos completamente, debes hacerlo manualmente desde el servidor.</p>';
    print '<p><strong>Ruta:</strong> ' . DOL_DOCUMENT_ROOT . '/custom/search_duplicates/</p>';
    print '</div>';
    
} else {
    // Mostrar formulario de confirmación
    print '<div class="warning">';
    print '<h4>⚠️ Advertencia</h4>';
    print '<p>Estás a punto de desinstalar el módulo SearchDuplicates. Esta acción:</p>';
    print '<ul>';
    print '<li>Desactivará el módulo</li>';
    print '<li>Eliminará los menús del módulo</li>';
    print '<li>Eliminará los permisos del módulo</li>';
    print '<li>Eliminará las constantes del módulo</li>';
    print '</ul>';
    print '<p><strong>Los archivos del módulo NO se eliminarán automáticamente.</strong></p>';
    print '</div>';
    
    print '<div class="info">';
    print '<h4>Información del módulo</h4>';
    print '<p><strong>Nombre:</strong> SearchDuplicates</p>';
    print '<p><strong>Versión:</strong> 1.3</p>';
    print '<p><strong>Estado:</strong> ' . ($conf->global->MAIN_MODULE_SEARCHDUPLICATES ? 'Activo' : 'Inactivo') . '</p>';
    print '</div>';
    
    print '<form method="post" action="' . $_SERVER['PHP_SELF'] . '">';
    print '<input type="hidden" name="action" value="uninstall">';
    print '<input type="hidden" name="confirm" value="yes">';
    print '<div class="tabsAction">';
    print '<input type="submit" class="butAction" value="Desinstalar Módulo" onclick="return confirm(\'¿Estás seguro de desinstalar el módulo?\')">';
    print '<a href="' . DOL_URL_ROOT . '/admin/modules.php" class="butAction">Cancelar</a>';
    print '</div>';
    print '</form>';
}

print '</div>';
print '</div>';

// Close page
llxFooter();
?>


