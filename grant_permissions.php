<?php
// Script para otorgar permisos del módulo a todos los usuarios
require_once '../../../main.inc.php';

if (!$user->admin) {
    accessforbidden();
}

echo "<h1>Otorgando permisos del módulo Search Duplicates a todos los usuarios</h1>";

// Obtener todos los usuarios
$sql = "SELECT rowid, login FROM llx_user WHERE statut = 1";
$resql = $db->query($sql);

if ($resql) {
    $num_users = $db->num_rows($resql);
    echo "<p>Encontrados $num_users usuarios activos</p>";
    
    // Obtener los IDs de los permisos del módulo
    $sql_rights = "SELECT id FROM llx_rights_def WHERE module = 'search_duplicates'";
    $resql_rights = $db->query($sql_rights);
    
    if ($resql_rights) {
        $rights_ids = array();
        while ($obj = $db->fetch_object($resql_rights)) {
            $rights_ids[] = $obj->id;
        }
        
        echo "<p>Encontrados " . count($rights_ids) . " permisos del módulo</p>";
        
        $granted_count = 0;
        $error_count = 0;
        
        // Otorgar permisos a cada usuario
        while ($obj_user = $db->fetch_object($resql)) {
            $user_id = $obj_user->rowid;
            $user_login = $obj_user->login;
            
            echo "<p>Procesando usuario: $user_login (ID: $user_id)</p>";
            
            foreach ($rights_ids as $right_id) {
                // Verificar si el permiso ya existe
                $sql_check = "SELECT COUNT(*) as count FROM llx_user_rights WHERE fk_user = $user_id AND fk_id = $right_id";
                $resql_check = $db->query($sql_check);
                $obj_check = $db->fetch_object($resql_check);
                
                if ($obj_check->count == 0) {
                    // Otorgar el permiso
                    $sql_grant = "INSERT INTO llx_user_rights (fk_user, fk_id) VALUES ($user_id, $right_id)";
                    if ($db->query($sql_grant)) {
                        $granted_count++;
                        echo "  ✅ Permiso otorgado<br>";
                    } else {
                        $error_count++;
                        echo "  ❌ Error otorgando permiso: " . $db->lasterror() . "<br>";
                    }
                } else {
                    echo "  ⚠️ Permiso ya existía<br>";
                }
            }
        }
        
        echo "<h2>Resumen:</h2>";
        echo "<p>✅ Permisos otorgados: $granted_count</p>";
        echo "<p>❌ Errores: $error_count</p>";
        echo "<p>👥 Usuarios procesados: $num_users</p>";
        
    } else {
        echo "<p>❌ Error obteniendo permisos del módulo: " . $db->lasterror() . "</p>";
    }
} else {
    echo "<p>❌ Error obteniendo usuarios: " . $db->lasterror() . "</p>";
}

echo "<p><a href='/dolibarr/admin/modules.php'>← Volver a la configuración de módulos</a></p>";
?>




