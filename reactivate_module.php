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

echo "<h2>Reactivando Módulo Search Duplicates</h2>";

// Desactivar módulo
$sql = "UPDATE llx_const SET value = 0 WHERE name = 'MAIN_MODULE_SEARCHDUPLICATES'";
$result = $db->query($sql);
if ($result) {
    echo "<p>✅ Módulo desactivado</p>";
} else {
    echo "<p>❌ Error desactivando módulo: " . $db->lasterror() . "</p>";
}

// Eliminar menús existentes
$sql = "DELETE FROM llx_menu WHERE mainmenu = 'search_duplicates'";
$result = $db->query($sql);
if ($result) {
    echo "<p>✅ Menús existentes eliminados</p>";
} else {
    echo "<p>❌ Error eliminando menús: " . $db->lasterror() . "</p>";
}

// Eliminar permisos existentes
$sql = "DELETE FROM llx_rights_def WHERE module = 'search_duplicates'";
$result = $db->query($sql);
if ($result) {
    echo "<p>✅ Permisos existentes eliminados</p>";
} else {
    echo "<p>❌ Error eliminando permisos: " . $db->lasterror() . "</p>";
}

// Activar módulo
$sql = "UPDATE llx_const SET value = 1 WHERE name = 'MAIN_MODULE_SEARCHDUPLICATES'";
$result = $db->query($sql);
if ($result) {
    echo "<p>✅ Módulo activado</p>";
} else {
    echo "<p>❌ Error activando módulo: " . $db->lasterror() . "</p>";
}

// Insertar permisos
$sql = "INSERT INTO llx_rights_def (entity, libelle, module, perms, subperms, type, bydefault, advanced, module_position) VALUES 
        (1, 'Leer objetos de búsqueda avanzada', 'search_duplicates', 'read', '', 'object', 1, 0, 0),
        (1, 'Crear/Actualizar objetos de búsqueda avanzada', 'search_duplicates', 'write', '', 'object', 1, 0, 0),
        (1, 'Eliminar objetos de búsqueda avanzada', 'search_duplicates', 'delete', '', 'object', 1, 0, 0)";
$result = $db->query($sql);
if ($result) {
    echo "<p>✅ Permisos insertados</p>";
} else {
    echo "<p>❌ Error insertando permisos: " . $db->lasterror() . "</p>";
}

// Insertar menús
$sql = "INSERT INTO llx_menu (entity, menu_handler, type, titre, mainmenu, leftmenu, url, langs, position, enabled, perms, target, user, usertype, entity) VALUES 
        (1, 'search_duplicates', 'top', 'Productos Duplicados', 'search_duplicates', '0', '/custom/searchduplicates/index.php', 'search_duplicates@search_duplicates', 105, 1, 1, '', 0, 0, 1),
        (1, 'search_duplicates', 'left', 'Productos Duplicados', 'search_duplicates', 'search_duplicates', '/custom/searchduplicates/index.php', 'search_duplicates@search_duplicates', 1000, 1, 1, '', 0, 0, 1)";
$result = $db->query($sql);
if ($result) {
    echo "<p>✅ Menús insertados</p>";
} else {
    echo "<p>❌ Error insertando menús: " . $db->lasterror() . "</p>";
}

// Asignar permisos a todos los usuarios existentes
$sql = "SELECT rowid FROM llx_user WHERE statut = 1";
$result = $db->query($sql);
if ($result) {
    $users = array();
    while ($obj = $db->fetch_object($result)) {
        $users[] = $obj->rowid;
    }
    
    foreach ($users as $user_id) {
        // Asignar permisos de lectura
        $sql = "INSERT INTO llx_user_rights (fk_user, fk_id, module, perms) VALUES 
                ($user_id, 500000, 'search_duplicates', 'read'),
                ($user_id, 500001, 'search_duplicates', 'write'),
                ($user_id, 500002, 'search_duplicates', 'delete')";
        $db->query($sql);
    }
    
    echo "<p>✅ Permisos asignados a " . count($users) . " usuarios</p>";
} else {
    echo "<p>❌ Error obteniendo usuarios: " . $db->lasterror() . "</p>";
}

echo "<h3>Reactivación Completada</h3>";
echo "<p><a href='index.php'>Ir al módulo</a></p>";
echo "<p><a href='" . DOL_URL_ROOT . "/admin/modules.php'>Ver módulos</a></p>";
?>


