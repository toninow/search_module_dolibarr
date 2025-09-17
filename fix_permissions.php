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

echo "<h2>Configurando Permisos del Módulo Search Duplicates</h2>";

// Verificar si el módulo está activo
$sql = "SELECT value FROM llx_const WHERE name = 'MAIN_MODULE_SEARCHDUPLICATES'";
$result = $db->query($sql);
if ($result) {
    $obj = $db->fetch_object($result);
    if ($obj && $obj->value == 1) {
        echo "<p>✅ Módulo está activo</p>";
    } else {
        echo "<p>❌ Módulo no está activo. Activando...</p>";
        $sql = "UPDATE llx_const SET value = 1 WHERE name = 'MAIN_MODULE_SEARCHDUPLICATES'";
        $db->query($sql);
        echo "<p>✅ Módulo activado</p>";
    }
} else {
    echo "<p>❌ Error verificando estado del módulo</p>";
}

// Verificar permisos existentes
$sql = "SELECT COUNT(*) as count FROM llx_rights_def WHERE module = 'search_duplicates'";
$result = $db->query($sql);
if ($result) {
    $obj = $db->fetch_object($result);
    if ($obj->count == 0) {
        echo "<p>❌ No hay permisos definidos. Creando...</p>";
        
        // Insertar permisos
        $sql = "INSERT INTO llx_rights_def (entity, libelle, module, perms, subperms, type, bydefault, advanced, module_position) VALUES 
                (1, 'Leer objetos de búsqueda avanzada', 'search_duplicates', 'read', '', 'object', 1, 0, 0),
                (1, 'Crear/Actualizar objetos de búsqueda avanzada', 'search_duplicates', 'write', '', 'object', 1, 0, 0),
                (1, 'Eliminar objetos de búsqueda avanzada', 'search_duplicates', 'delete', '', 'object', 1, 0, 0)";
        $result = $db->query($sql);
        if ($result) {
            echo "<p>✅ Permisos creados</p>";
        } else {
            echo "<p>❌ Error creando permisos: " . $db->lasterror() . "</p>";
        }
    } else {
        echo "<p>✅ Permisos ya existen</p>";
    }
}

// Verificar menús existentes
$sql = "SELECT COUNT(*) as count FROM llx_menu WHERE mainmenu = 'search_duplicates'";
$result = $db->query($sql);
if ($result) {
    $obj = $db->fetch_object($result);
    if ($obj->count == 0) {
        echo "<p>❌ No hay menús definidos. Creando...</p>";
        
        // Insertar menús
        $sql = "INSERT INTO llx_menu (entity, menu_handler, type, titre, mainmenu, leftmenu, url, langs, position, enabled, perms, target, user, usertype, entity) VALUES 
                (1, 'search_duplicates', 'top', 'Productos Duplicados', 'search_duplicates', '0', '/custom/searchduplicates/index.php', 'search_duplicates@search_duplicates', 105, 1, 1, '', 0, 0, 1),
                (1, 'search_duplicates', 'left', 'Productos Duplicados', 'search_duplicates', 'search_duplicates', '/custom/searchduplicates/index.php', 'search_duplicates@search_duplicates', 1000, 1, 1, '', 0, 0, 1)";
        $result = $db->query($sql);
        if ($result) {
            echo "<p>✅ Menús creados</p>";
        } else {
            echo "<p>❌ Error creando menús: " . $db->lasterror() . "</p>";
        }
    } else {
        echo "<p>✅ Menús ya existen</p>";
    }
}

// Asignar permisos a todos los usuarios existentes
$sql = "SELECT rowid FROM llx_user WHERE statut = 1";
$result = $db->query($sql);
if ($result) {
    $users = array();
    while ($obj = $db->fetch_object($result)) {
        $users[] = $obj->rowid;
    }
    
    $assigned = 0;
    foreach ($users as $user_id) {
        // Verificar si ya tiene permisos
        $sql = "SELECT COUNT(*) as count FROM llx_user_rights WHERE fk_user = $user_id AND module = 'search_duplicates'";
        $check = $db->query($sql);
        if ($check) {
            $obj = $db->fetch_object($check);
            if ($obj->count == 0) {
                // Asignar permisos
                $sql = "INSERT INTO llx_user_rights (fk_user, fk_id, module, perms) VALUES 
                        ($user_id, 500000, 'search_duplicates', 'read'),
                        ($user_id, 500001, 'search_duplicates', 'write'),
                        ($user_id, 500002, 'search_duplicates', 'delete')";
                $db->query($sql);
                $assigned++;
            }
        }
    }
    
    echo "<p>✅ Permisos asignados a $assigned usuarios nuevos</p>";
} else {
    echo "<p>❌ Error obteniendo usuarios: " . $db->lasterror() . "</p>";
}

echo "<h3>Configuración Completada</h3>";
echo "<p><a href='index.php'>Ir al módulo</a></p>";
echo "<p><a href='" . DOL_URL_ROOT . "/admin/modules.php'>Ver módulos</a></p>";
?>


