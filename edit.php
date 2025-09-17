<?php
/* Copyright (C) 2024 - Search Duplicates Module for Dolibarr
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file       edit.php
 * \ingroup    search_duplicates
 * \brief      Product edit page within the module
 */

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php";
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) {
    echo "Error: No se pudo cargar main.inc.php";
    exit;
}

// Load Product class
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

// Load translation files
if (isset($langs)) {
    $langs->load("search_duplicates@search_duplicates");
}

// Parameters
$product_id = GETPOST('id', 'int');
if (empty($product_id)) {
    $product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
}
$search_term = GETPOST('search_term', 'alpha');
$search_id = GETPOST('search_id', 'int');
$search_ref = GETPOST('search_ref', 'alpha');
$search_name = GETPOST('search_name', 'alpha');
$search_desc = GETPOST('search_desc', 'alpha');
$limit = GETPOST('limit', 'int') ?: 50;
$action = GETPOST('action', 'aZ09');

// Parámetros de paginado
$stock_page = GETPOST('stock_page', 'int') ?: 1;
$price_page = GETPOST('price_page', 'int') ?: 1;
$stock_limit = 10; // Registros por página
$price_limit = 10; // Registros por página

if (empty($product_id)) {
    echo "Error: No se proporcionó ID de producto";
    echo "<br><a href='index.php'>Volver al índice</a>";
    exit;
}

// Procesar actualización de precio
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $action == 'update_price') {
    $new_price = GETPOST('new_price', 'float');
    $price_type = GETPOST('price_type', 'aZ09'); // 'ht' o 'ttc'
    
    $success = false;
    $message = '';
    
    if ($new_price < 0) {
        $message = "El precio no puede ser negativo";
    } else {
        // Actualizar precio en la base de datos
        $sql = "UPDATE " . MAIN_DB_PREFIX . "product SET 
                price = " . (float)$new_price . ",
                tms = NOW()
                WHERE rowid = " . (int)$product_id;
        
        if ($db->query($sql)) {
            $success = true;
            $message = "Precio actualizado correctamente a " . number_format($new_price, 2, '.', '') . " €";
            
            // Calcular precios con y sin IVA
            $price_ht = $new_price;
            $price_ttc = $new_price;
            
            if ($price_type == 'ttc') {
                // Si es precio con IVA incluido, calcular precio sin IVA (dividir por 1.21)
                $price_ht = $new_price / 1.21;
                $price_ttc = $new_price; // El precio ingresado ya incluye IVA
            } else {
                // Si es precio sin IVA, calcular precio con IVA (multiplicar por 1.21)
                $price_ht = $new_price; // El precio ingresado es sin IVA
                $price_ttc = $new_price * 1.21;
            }
            
            // Actualizar tabla llx_product_price para sincronizar con Dolibarr
            $sql_product_price = "INSERT INTO " . MAIN_DB_PREFIX . "product_price 
                                 (entity, fk_product, date_price, price_level, price, price_ttc, price_base_type, fk_user_author, tosell) 
                                 VALUES (1, " . (int)$product_id . ", NOW(), 1, " . (float)$price_ht . ", " . (float)$price_ttc . ", '" . ($price_type == 'ht' ? 'HT' : 'TTC') . "', " . (int)$user->id . ", 1)
                                 ON DUPLICATE KEY UPDATE 
                                 price = " . (float)$price_ht . ", 
                                 price_ttc = " . (float)$price_ttc . ", 
                                 price_base_type = '" . ($price_type == 'ht' ? 'HT' : 'TTC') . "',
                                 fk_user_author = " . (int)$user->id . ",
                                 tms = NOW()";
            $db->query($sql_product_price); // No importa si falla, es opcional
            
            // Registrar en historial de precios usando tabla estándar de Dolibarr
            $sql_price_history = "INSERT INTO " . MAIN_DB_PREFIX . "product_customer_price_log 
                                 (entity, fk_product, fk_soc, fk_user, datec, price, price_base, price_base_type, tms) 
                                 VALUES (1, " . (int)$product_id . ", NULL, " . (int)$user->id . ", NOW(), " . (float)$price_ht . ", " . (float)$price_ttc . ", '" . ($price_type == 'ht' ? 'HT' : 'TTC') . "', NOW())";
            $db->query($sql_price_history); // No importa si falla, es opcional
            
            // Actualizar datos del producto (cargar objeto si no existe)
            if (!isset($product) || $product === null) {
                $product = new Product($db);
                $product->fetch($product_id);
            }
            $product->price = $new_price;
        } else {
            $message = "Error al actualizar el precio: " . $db->lasterror();
        }
    }
    
    // Mostrar mensaje y recargar la página
    if ($success) {
        echo "<script>alert('✅ " . addslashes($message) . "'); window.location.href = window.location.href.split('?')[0] + '?id=" . $product_id . "';</script>";
    } else {
        echo "<script>alert('❌ " . addslashes($message) . "');</script>";
    }
}

// Procesar gestión de stock
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $action == 'manage_stock') {
    $action_type = GETPOST('action_type', 'aZ09');
    $quantity = GETPOST('quantity', 'int');
    $single_store = GETPOST('single_store', 'alphanohtml');
    $from_store = GETPOST('from_store', 'alphanohtml');
    $to_store = GETPOST('to_store', 'alphanohtml');
    
    $success = false;
    $message = '';
    
    // Debug consolidado
    echo "<script>console.log('DEBUG POST:', " . json_encode($_POST) . ");</script>";
    echo "<!-- DEBUG SINGLE_STORE RAW: '" . $_POST['single_store'] . "' -->";
    echo "<!-- DEBUG SINGLE_STORE FILTERED: '" . $single_store . "' -->";
    echo "<!-- DEBUG ACTION_TYPE: '" . $action_type . "' -->";
    echo "<!-- DEBUG QUANTITY: " . $quantity . " -->";
    
    if ($quantity <= 0) {
        $message = "La cantidad debe ser mayor a 0";
        echo "<!-- DEBUG: Cantidad inválida -->";
    } elseif ($action_type == 'add' && !empty($single_store)) {
        echo "<!-- DEBUG: Entrando a agregar stock con single_store: '" . $single_store . "' -->";
        // Agregar stock - Buscar tienda con diferentes métodos
        $entrepot_id = 0;
        
        // Método 1: Buscar por ref exacta
        $single_store_clean = trim($single_store);
        $sql_entrepot = "SELECT rowid FROM " . MAIN_DB_PREFIX . "entrepot WHERE ref = '" . $db->escape($single_store_clean) . "'";
        $resql_entrepot = $db->query($sql_entrepot);
        $entrepot_obj = $db->fetch_object($resql_entrepot);
        $entrepot_id = $entrepot_obj ? $entrepot_obj->rowid : 0;
        
        // Método 2: Si no encuentra, buscar por coincidencia parcial en ref
        if ($entrepot_id == 0) {
            $sql_entrepot2 = "SELECT rowid FROM " . MAIN_DB_PREFIX . "entrepot WHERE ref LIKE '%" . $db->escape($single_store) . "%'";
            $resql_entrepot2 = $db->query($sql_entrepot2);
            $entrepot_obj2 = $db->fetch_object($resql_entrepot2);
            $entrepot_id = $entrepot_obj2 ? $entrepot_obj2->rowid : 0;
        }
        
        // Método 3: Buscar por coincidencia parcial en ref
        if ($entrepot_id == 0) {
            $sql_entrepot3 = "SELECT rowid FROM " . MAIN_DB_PREFIX . "entrepot WHERE ref LIKE '%" . $db->escape($single_store) . "%'";
            $resql_entrepot3 = $db->query($sql_entrepot3);
            $entrepot_obj3 = $db->fetch_object($resql_entrepot3);
            $entrepot_id = $entrepot_obj3 ? $entrepot_obj3->rowid : 0;
        }
        
        // Debug de la búsqueda de tienda
        echo "<!-- DEBUG TIENDA: Buscando '$single_store', ID encontrado: $entrepot_id -->";
        echo "<!-- DEBUG SQL1: " . $sql_entrepot . " -->";
        echo "<!-- DEBUG SQL2: " . $sql_entrepot2 . " -->";
        echo "<!-- DEBUG SQL3: " . $sql_entrepot3 . " -->";
        
        if ($entrepot_id == 0) {
            $message = "Error: Tienda '" . $single_store . "' no encontrada con ningún método";
        } else {
            $sql = "INSERT INTO " . MAIN_DB_PREFIX . "stock_mouvement 
                    (fk_product, fk_entrepot, value, datem, type_mouvement, label, fk_user_author) 
                    VALUES (" . (int)$product_id . ", " . (int)$entrepot_id . ", " . (int)$quantity . ", NOW(), 0, 'Agregado desde Gestión de Stock', " . (int)$user->id . ")";
            
            // Debug de la consulta de inserción
            echo "<!-- DEBUG INSERT SQL: " . $sql . " -->";
            
            if ($db->query($sql)) {
                // Calcular stock real basado en movimientos y actualizar product_stock
                $sql_calc_stock = "SELECT SUM(value) as total_stock FROM " . MAIN_DB_PREFIX . "stock_mouvement 
                                  WHERE fk_product = " . (int)$product_id . " AND fk_entrepot = " . (int)$entrepot_id;
                $res_calc_stock = $db->query($sql_calc_stock);
                $calc_stock_obj = $db->fetch_object($res_calc_stock);
                $real_stock = $calc_stock_obj ? $calc_stock_obj->total_stock : 0;
                
                $sql_product_stock = "INSERT INTO " . MAIN_DB_PREFIX . "product_stock (fk_product, fk_entrepot, reel) 
                                     VALUES (" . (int)$product_id . ", " . (int)$entrepot_id . ", " . (int)$real_stock . ")
                                     ON DUPLICATE KEY UPDATE reel = " . (int)$real_stock;
                
                if ($db->query($sql_product_stock)) {
                    $success = true;
                    $message = "Se agregaron " . $quantity . " unidades a " . $single_store . " (sincronizado con POS)";
                    echo "<!-- DEBUG: Inserción exitosa y sincronizado con POS -->";
                } else {
                    $success = true;
                    $message = "Se agregaron " . $quantity . " unidades a " . $single_store . " (error sincronizando con POS: " . $db->lasterror() . ")";
                    echo "<!-- DEBUG: Inserción exitosa pero error sincronizando con POS -->";
                }
            } else {
                $message = "Error al agregar stock: " . $db->lasterror();
                echo "<!-- DEBUG ERROR INSERT: " . $db->lasterror() . " -->";
            }
        }
        
    } elseif ($action_type == 'remove' && !empty($single_store)) {
        // Quitar stock - Buscar tienda con diferentes métodos
        $entrepot_id = 0;
        
        // Método 1: Buscar por ref exacta
        $single_store_clean = trim($single_store);
        $sql_entrepot = "SELECT rowid FROM " . MAIN_DB_PREFIX . "entrepot WHERE ref = '" . $db->escape($single_store_clean) . "'";
        $resql_entrepot = $db->query($sql_entrepot);
        $entrepot_obj = $db->fetch_object($resql_entrepot);
        $entrepot_id = $entrepot_obj ? $entrepot_obj->rowid : 0;
        
        // Método 2: Si no encuentra, buscar por coincidencia parcial en ref
        if ($entrepot_id == 0) {
            $sql_entrepot2 = "SELECT rowid FROM " . MAIN_DB_PREFIX . "entrepot WHERE ref LIKE '%" . $db->escape($single_store) . "%'";
            $resql_entrepot2 = $db->query($sql_entrepot2);
            $entrepot_obj2 = $db->fetch_object($resql_entrepot2);
            $entrepot_id = $entrepot_obj2 ? $entrepot_obj2->rowid : 0;
        }
        
        // Método 3: Buscar por coincidencia parcial en ref
        if ($entrepot_id == 0) {
            $sql_entrepot3 = "SELECT rowid FROM " . MAIN_DB_PREFIX . "entrepot WHERE ref LIKE '%" . $db->escape($single_store) . "%'";
            $resql_entrepot3 = $db->query($sql_entrepot3);
            $entrepot_obj3 = $db->fetch_object($resql_entrepot3);
            $entrepot_id = $entrepot_obj3 ? $entrepot_obj3->rowid : 0;
        }
        
        // Debug de la búsqueda de tienda
        echo "<!-- DEBUG TIENDA REMOVE: Buscando '$single_store', ID encontrado: $entrepot_id -->";
        
        if ($entrepot_id == 0) {
            $message = "Error: Tienda '" . $single_store . "' no encontrada con ningún método";
        } else {
            $sql = "INSERT INTO " . MAIN_DB_PREFIX . "stock_mouvement 
                    (fk_product, fk_entrepot, value, datem, type_mouvement, label, fk_user_author) 
                    VALUES (" . (int)$product_id . ", " . (int)$entrepot_id . ", -" . (int)$quantity . ", NOW(), 0, 'Quitado desde Gestión de Stock', " . (int)$user->id . ")";
            
            // Debug de la consulta de inserción
            echo "<!-- DEBUG REMOVE SQL: " . $sql . " -->";
            
            if ($db->query($sql)) {
                // Calcular stock real basado en movimientos y actualizar product_stock
                $sql_calc_stock = "SELECT SUM(value) as total_stock FROM " . MAIN_DB_PREFIX . "stock_mouvement 
                                  WHERE fk_product = " . (int)$product_id . " AND fk_entrepot = " . (int)$entrepot_id;
                $res_calc_stock = $db->query($sql_calc_stock);
                $calc_stock_obj = $db->fetch_object($res_calc_stock);
                $real_stock = $calc_stock_obj ? $calc_stock_obj->total_stock : 0;
                
                $sql_product_stock = "INSERT INTO " . MAIN_DB_PREFIX . "product_stock (fk_product, fk_entrepot, reel) 
                                     VALUES (" . (int)$product_id . ", " . (int)$entrepot_id . ", " . (int)$real_stock . ")
                                     ON DUPLICATE KEY UPDATE reel = " . (int)$real_stock;
                
                if ($db->query($sql_product_stock)) {
                    $success = true;
                    $message = "Se quitaron " . $quantity . " unidades de " . $single_store . " (sincronizado con POS)";
                    echo "<!-- DEBUG: Inserción remove exitosa y sincronizado con POS -->";
                } else {
                    $success = true;
                    $message = "Se quitaron " . $quantity . " unidades de " . $single_store . " (error sincronizando con POS: " . $db->lasterror() . ")";
                    echo "<!-- DEBUG: Inserción remove exitosa pero error sincronizando con POS -->";
                }
            } else {
                $message = "Error al quitar stock: " . $db->lasterror();
                echo "<!-- DEBUG ERROR REMOVE: " . $db->lasterror() . " -->";
            }
        }
        
    } elseif ($action_type == 'transfer' && !empty($from_store) && !empty($to_store) && $from_store != $to_store) {
        // Debug de transferir
        echo "<!-- DEBUG TRANSFER: from_store='$from_store', to_store='$to_store', quantity='$quantity' -->";
        
        // Transferir stock - Obtener IDs reales de las tiendas
        $sql_from = "SELECT rowid FROM " . MAIN_DB_PREFIX . "entrepot WHERE ref = '" . $db->escape($from_store) . "'";
        $resql_from = $db->query($sql_from);
        $from_obj = $db->fetch_object($resql_from);
        $from_entrepot_id = $from_obj ? $from_obj->rowid : 0;
        
        $sql_to = "SELECT rowid FROM " . MAIN_DB_PREFIX . "entrepot WHERE ref = '" . $db->escape($to_store) . "'";
        $resql_to = $db->query($sql_to);
        $to_obj = $db->fetch_object($resql_to);
        $to_entrepot_id = $to_obj ? $to_obj->rowid : 0;
        
        // Debug de búsqueda de tiendas
        echo "<!-- DEBUG TRANSFER TIENDAS: from_id=$from_entrepot_id, to_id=$to_entrepot_id -->";
        
        if ($from_entrepot_id == 0 || $to_entrepot_id == 0) {
            $message = "Error: Una o ambas tiendas no encontradas (from_id=$from_entrepot_id, to_id=$to_entrepot_id)";
        } else {
            // Quitar de origen
            $sql1 = "INSERT INTO " . MAIN_DB_PREFIX . "stock_mouvement 
                     (fk_product, fk_entrepot, value, datem, type_mouvement, label, fk_user_author) 
                     VALUES (" . (int)$product_id . ", " . (int)$from_entrepot_id . ", -" . (int)$quantity . ", NOW(), 0, 'Transferido a " . $to_store . "', " . (int)$user->id . ")";
            
            // Agregar a destino
            $sql2 = "INSERT INTO " . MAIN_DB_PREFIX . "stock_mouvement 
                     (fk_product, fk_entrepot, value, datem, type_mouvement, label, fk_user_author) 
                     VALUES (" . (int)$product_id . ", " . (int)$to_entrepot_id . ", " . (int)$quantity . ", NOW(), 0, 'Transferido desde " . $from_store . "', " . (int)$user->id . ")";
            
            // Debug de las consultas SQL
            echo "<!-- DEBUG TRANSFER SQL1: " . $sql1 . " -->";
            echo "<!-- DEBUG TRANSFER SQL2: " . $sql2 . " -->";
            
            if ($db->query($sql1) && $db->query($sql2)) {
                // Calcular stock real basado en movimientos y actualizar product_stock para ambas tiendas
                
                // Stock de tienda origen
                $sql_calc_stock_from = "SELECT SUM(value) as total_stock FROM " . MAIN_DB_PREFIX . "stock_mouvement 
                                       WHERE fk_product = " . (int)$product_id . " AND fk_entrepot = " . (int)$from_entrepot_id;
                $res_calc_stock_from = $db->query($sql_calc_stock_from);
                $calc_stock_from_obj = $db->fetch_object($res_calc_stock_from);
                $real_stock_from = $calc_stock_from_obj ? $calc_stock_from_obj->total_stock : 0;
                
                // Stock de tienda destino
                $sql_calc_stock_to = "SELECT SUM(value) as total_stock FROM " . MAIN_DB_PREFIX . "stock_mouvement 
                                     WHERE fk_product = " . (int)$product_id . " AND fk_entrepot = " . (int)$to_entrepot_id;
                $res_calc_stock_to = $db->query($sql_calc_stock_to);
                $calc_stock_to_obj = $db->fetch_object($res_calc_stock_to);
                $real_stock_to = $calc_stock_to_obj ? $calc_stock_to_obj->total_stock : 0;
                
                $sql_product_stock_from = "INSERT INTO " . MAIN_DB_PREFIX . "product_stock (fk_product, fk_entrepot, reel) 
                                          VALUES (" . (int)$product_id . ", " . (int)$from_entrepot_id . ", " . (int)$real_stock_from . ")
                                          ON DUPLICATE KEY UPDATE reel = " . (int)$real_stock_from;
                
                $sql_product_stock_to = "INSERT INTO " . MAIN_DB_PREFIX . "product_stock (fk_product, fk_entrepot, reel) 
                                        VALUES (" . (int)$product_id . ", " . (int)$to_entrepot_id . ", " . (int)$real_stock_to . ")
                                        ON DUPLICATE KEY UPDATE reel = " . (int)$real_stock_to;
                
                if ($db->query($sql_product_stock_from) && $db->query($sql_product_stock_to)) {
                    $success = true;
                    $message = "Se transfirieron " . $quantity . " unidades de " . $from_store . " a " . $to_store . " (sincronizado con POS)";
                    echo "<!-- DEBUG: Transferencia exitosa y sincronizado con POS -->";
                } else {
                    $success = true;
                    $message = "Se transfirieron " . $quantity . " unidades de " . $from_store . " a " . $to_store . " (error sincronizando con POS: " . $db->lasterror() . ")";
                    echo "<!-- DEBUG: Transferencia exitosa pero error sincronizando con POS -->";
                }
            } else {
                $message = "Error al transferir stock: " . $db->lasterror();
                echo "<!-- DEBUG ERROR TRANSFER: " . $db->lasterror() . " -->";
            }
        }
        
    } else {
        // Validación más específica
        if (empty($action_type)) {
            $message = "Por favor selecciona una acción.";
        } elseif ($quantity <= 0) {
            $message = "La cantidad debe ser mayor a 0.";
        } elseif (($action_type == 'add' || $action_type == 'remove') && empty($single_store)) {
            echo "<!-- DEBUG: single_store está vacío para $action_type -->";
            echo "<!-- DEBUG: POST single_store raw: '" . $_POST['single_store'] . "' -->";
            $message = "Por favor selecciona una tienda para " . ($action_type == 'add' ? 'agregar' : 'quitar') . " stock. DEBUG: single_store='$single_store'";
        } elseif ($action_type == 'transfer' && (empty($from_store) || empty($to_store))) {
            $message = "Por favor selecciona tienda origen y destino para transferir stock. DEBUG: from_store='$from_store', to_store='$to_store'";
        } elseif ($action_type == 'transfer' && $from_store == $to_store) {
            $message = "La tienda origen y destino no pueden ser la misma.";
        } else {
            $message = "Error desconocido en la validación.";
        }
    }
    
    // Mostrar mensaje y recargar la página
    if ($success) {
        echo "<script>alert('✅ " . addslashes($message) . "'); window.location.href = window.location.href.split('?')[0] + '?id=" . $product_id . "';</script>";
    } else {
        echo "<script>alert('❌ " . addslashes($message) . "');</script>";
    }
}

// Construir URL de retorno después del procesamiento del formulario
$return_url = buildReturnUrl('index.php', $_GET);

// Debug temporal - mostrar la URL de retorno
echo "<!-- DEBUG: URL de retorno: " . $return_url . " -->";
echo "<!-- DEBUG: Parámetros GET: " . print_r($_GET, true) . " -->";

$linkback = '<a href="' . $return_url . '">← Volver a la búsqueda</a>';

// Get product details with real stock information
$sql = "SELECT p.*, 
               COALESCE(SUM(sm.value), 0) as total_stock,
               MAX(sm.datem) as last_stock_date
        FROM " . MAIN_DB_PREFIX . "product p
        LEFT JOIN " . MAIN_DB_PREFIX . "stock_mouvement sm ON p.rowid = sm.fk_product
        WHERE p.rowid = " . (int)$product_id . "
        GROUP BY p.rowid";
$resql = $db->query($sql);
if (!$resql) {
    echo "Error en consulta SQL: " . $db->lasterror();
    echo "<br>SQL: " . $sql;
    echo "<br><a href='index.php'>Volver al índice</a>";
    exit;
}

$product = $db->fetch_object($resql);
if (!$product) {
    echo "Producto no encontrado con ID: " . $product_id;
    echo "<br>SQL: " . $sql;
    echo "<br><a href='index.php'>Volver al índice</a>";
    exit;
}

// Obtener todas las tiendas para debug
$sql_all_stores = "SELECT rowid, ref FROM " . MAIN_DB_PREFIX . "entrepot ORDER BY ref";
$resql_all_stores = $db->query($sql_all_stores);
$all_stores = [];

// Debug de la consulta SQL
echo "<!-- DEBUG SQL: " . $sql_all_stores . " -->";

if ($resql_all_stores) {
    echo "<!-- DEBUG: Consulta SQL exitosa -->";
        while ($store = $db->fetch_object($resql_all_stores)) {
            $all_stores[] = [
                'id' => $store->rowid,
                'ref' => $store->ref
            ];
        }
    echo "<!-- DEBUG: Se encontraron " . count($all_stores) . " tiendas -->";
        } else {
    echo "<!-- DEBUG ERROR SQL: " . $db->lasterror() . " -->";
}

// Debug: mostrar todas las tiendas encontradas
echo "<!-- DEBUG ALL STORES: " . print_r($all_stores, true) . " -->";

// Buscar tiendas específicas (HERNAN CORTES y CARPETANA)
$available_stores = [];
foreach ($all_stores as $store) {
    $ref_upper = strtoupper(trim($store['ref']));
    $ref_clean = preg_replace('/\s+/', ' ', $ref_upper); // Normalizar espacios
    
    echo "<!-- DEBUG COMPARANDO: '" . $store['ref'] . "' -> '" . $ref_upper . "' -> '" . $ref_clean . "' -->";
    
    if ($ref_clean == 'HERNAN CORTES' || $ref_clean == 'CARPETANA') {
        $available_stores[] = $store;
        echo "<!-- DEBUG: AGREGADO '" . $store['ref'] . "' -->";
    }
}

// Si no encontramos las tiendas específicas, usar las primeras 2 disponibles
if (empty($available_stores) && !empty($all_stores)) {
    $available_stores = array_slice($all_stores, 0, 2);
}

// Si aún no hay tiendas, crear tiendas por defecto
if (empty($available_stores)) {
    $available_stores = [
        ['id' => 4, 'ref' => 'HERNAN CORTES'],
        ['id' => 5, 'ref' => 'CARPETANA']
    ];
    echo "<!-- DEBUG: Usando tiendas por defecto -->";
}

// Debug: mostrar las tiendas que usaremos
echo "<!-- DEBUG AVAILABLE STORES: " . print_r($available_stores, true) . " -->";

// Get stock by warehouse
$sql_entrepots = "SELECT e.ref as entrepot_name, e.rowid as entrepot_id, 
                         COALESCE(SUM(sm.value), 0) as stock_qty
                  FROM " . MAIN_DB_PREFIX . "entrepot e
                  LEFT JOIN " . MAIN_DB_PREFIX . "stock_mouvement sm ON e.rowid = sm.fk_entrepot AND sm.fk_product = " . (int)$product_id . "
                  GROUP BY e.rowid, e.ref
                  ORDER BY e.ref";
$resql_entrepots = $db->query($sql_entrepots);
$stock_by_entrepot = [];
if ($resql_entrepots) {
    while ($obj_entrepot = $db->fetch_object($resql_entrepots)) {
        $stock_by_entrepot[$obj_entrepot->entrepot_id] = [
            'name' => $obj_entrepot->entrepot_name,
            'stock' => $obj_entrepot->stock_qty
        ];
    }
}

// Handle form submission
if ($action == 'update') {
    $total_stock = 0;
    $stock_updates = [];
    
    // Recopilar todos los valores de stock
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'stock_entrepot_') === 0) {
            $entrepot_id = str_replace('stock_entrepot_', '', $key);
            $stock_qty = (int)$value;
            $total_stock += $stock_qty;
            $stock_updates[$entrepot_id] = $stock_qty;
        }
    }
    
    // Si no hay campos dinámicos, usar los campos por defecto
    if (empty($stock_updates)) {
        $stock_carpetana = GETPOST('stock_carpetana', 'int');
        $stock_hernan_cortes = GETPOST('stock_hernan_cortes', 'int');
        $total_stock = $stock_carpetana + $stock_hernan_cortes;
    }
    
    // Actualizar stock en la tabla llx_product
    $sql = "UPDATE " . MAIN_DB_PREFIX . "product SET 
            stock = " . (int)$total_stock . ",
            tms = NOW()
            WHERE rowid = " . (int)$product_id;
    
    $result = $db->query($sql);
    if ($result) {
        $success_message = "Stock actualizado correctamente. Total: " . $total_stock . " unidades";
        
        // Actualizar movimientos de stock si hay datos por tienda
        if (!empty($stock_updates)) {
            foreach ($stock_updates as $entrepot_id => $stock_qty) {
                // Aquí se podría agregar lógica para actualizar llx_stock_mouvement
                // Por ahora solo mostramos el mensaje de éxito
            }
        }
    } else {
        $error_message = "Error al actualizar el stock: " . $db->lasterror();
    }
    
    // Refresh product data
    $sql = "SELECT p.*, 
                   COALESCE(SUM(sm.qty), 0) as total_stock,
                   MAX(sm.datem) as last_stock_date
            FROM " . MAIN_DB_PREFIX . "product p
            LEFT JOIN " . MAIN_DB_PREFIX . "stock_mouvement sm ON p.rowid = sm.fk_product
            WHERE p.rowid = " . (int)$product_id . "
            GROUP BY p.rowid";
    $resql = $db->query($sql);
    $product = $db->fetch_object($resql);
}

// Page header
if (function_exists('llxHeader')) {
    llxHeader('', "Editar Producto");
} else {
echo "<!DOCTYPE html><html><head><title>Editar Producto</title>";
echo "<style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .fichecenter { max-width: 1200px; margin: 0 auto; }
        .fichehalfleft, .fichehalfright { width: 48%; float: left; margin: 1%; }
        .clearboth { clear: both; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        .liste_titre { background: #f8f9fa; font-weight: bold; padding: 0; }
        td { padding: 8px; border-bottom: 1px solid #dee2e6; }
        input[type=number] { width: 100px; padding: 5px; border: 1px solid #ced4da; border-radius: 4px; }
        .button { background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; margin: 5px; display: inline-block; }
        .buttongen { background: #28a745; }
        .button:hover { opacity: 0.8; }
    </style></head><body>";
}

// Construir URL de retorno usando la misma lógica que index.php
function buildReturnUrl($base_url, $params = array()) {
    $url_params = array();
    
    // Parámetros básicos de búsqueda
    if (!empty($params['search_term'])) $url_params[] = 'search_term=' . urlencode($params['search_term']);
    if (!empty($params['search_id'])) $url_params[] = 'search_id=' . urlencode($params['search_id']);
    if (!empty($params['search_ref'])) $url_params[] = 'search_ref=' . urlencode($params['search_ref']);
    if (!empty($params['search_name'])) $url_params[] = 'search_name=' . urlencode($params['search_name']);
    if (!empty($params['search_desc'])) $url_params[] = 'search_desc=' . urlencode($params['search_desc']);
    if (!empty($params['limit'])) $url_params[] = 'limit=' . $params['limit'];
    if (!empty($params['tab'])) $url_params[] = 'tab=' . urlencode($params['tab']);
    if (!empty($params['group'])) $url_params[] = 'group=' . urlencode($params['group']);
    
    // Parámetros adicionales
    foreach ($params as $key => $value) {
        if (!in_array($key, ['search_term', 'search_id', 'search_ref', 'search_name', 'search_desc', 'limit', 'tab', 'group', 'id', 'action']) && !empty($value)) {
            $url_params[] = $key . '=' . urlencode($value);
        }
    }
    
    // Agregar action=search para que funcione la búsqueda
    $url_params[] = 'action=search';
    
    if (!empty($url_params)) {
        return $base_url . '?' . implode('&', $url_params);
    }
    
    return $base_url . '?action=search';
}

// La URL de retorno se construirá después del procesamiento del formulario

if (function_exists('load_fiche_titre')) {
    print load_fiche_titre("Editar Producto - " . htmlspecialchars($product->label), $linkback, 'title_setup');
} else {
    echo "<h1>Editar Producto - " . htmlspecialchars($product->label) . "</h1>";
    echo "<p>" . $linkback . "</p>";
}

// Show messages
if (isset($success_message)) {
    echo '<div style="background: #d4edda; color: #155724; padding: 10px; border: 1px solid #c3e6cb; border-radius: 4px; margin: 10px 0;">';
    echo '✅ ' . $success_message;
    echo '</div>';
}

if (isset($error_message)) {
    echo '<div style="background: #f8d7da; color: #721c24; padding: 10px; border: 1px solid #f5c6cb; border-radius: 4px; margin: 10px 0;">';
    echo '❌ ' . $error_message;
    echo '</div>';
}

// Edit form
print '<div class="fichecenter">';
print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '">';
print '<input type="hidden" name="action" value="update">';
print '<input type="hidden" name="id" value="' . $product_id . '">';
print '<input type="hidden" name="search_term" value="' . htmlspecialchars($search_term) . '">';
print '<input type="hidden" name="limit" value="' . $limit . '">';

// LAYOUT PRINCIPAL: 2 columnas
print '<div style="display: flex; height: 100vh; gap: 20px;">';

// COLUMNA IZQUIERDA
print '<div style="flex: 1; display: flex; flex-direction: column; gap: 20px;">';

// 1. Información del Producto (arriba izquierda)
print '<div style="flex: 0 0 auto;">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="4">📋 Información del Producto</td></tr>';

print '<tr><td style="width: 15%;">ID:</td><td style="width: 20%;"><strong>' . $product->rowid . '</strong></td><td style="width: 15%;">Referencia:</td><td style="width: 50%;"><strong>' . htmlspecialchars($product->ref) . '</strong></td></tr>';
print '<tr><td>Nombre:</td><td colspan="3"><strong>' . htmlspecialchars($product->label) . '</strong></td></tr>';

// Obtener EAN del producto
$ean = '';
$sql_ean = "SELECT barcode FROM " . MAIN_DB_PREFIX . "product WHERE rowid = " . (int)$product_id;
$res_ean = $db->query($sql_ean);
if ($res_ean && $db->num_rows($res_ean) > 0) {
    $obj_ean = $db->fetch_object($res_ean);
    $ean = $obj_ean->barcode;
}

print '<tr><td>EAN:</td><td colspan="3"><strong>' . ($ean ? htmlspecialchars($ean) : '<em>No disponible</em>') . '</strong></td></tr>';
print '<tr><td>Descripción:</td><td colspan="3"><div style="max-height: 120px; overflow-y: auto; border: 1px solid #dee2e6; padding: 8px; background: #f8f9fa; border-radius: 4px; font-size: 12px; line-height: 1.4;">' . $product->description . '</div></td></tr>';
print '<tr><td>Precio:</td><td colspan="3">';
print '<div style="display: flex; align-items: center; gap: 10px; flex-wrap: nowrap;">';

// Formulario de edición de precio - Solo campo editable
print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '" style="display: flex; align-items: center; gap: 8px; flex-wrap: nowrap;">';
print '<input type="hidden" name="action" value="update_price">';
print '<input type="hidden" name="id" value="' . $product_id . '">';

// Campo de precio más grande
print '<input type="number" id="new_price" name="new_price" value="' . number_format($product->price, 2, '.', '') . '" step="0.01" min="0" style="width: 100px; padding: 8px 12px; border: 2px solid #007bff; border-radius: 6px; font-size: 16px; font-weight: bold; text-align: center; background: #f8f9fa;">';

// Selector de tipo
print '<select id="price_type" name="price_type" style="padding: 8px 12px; border: 2px solid #ced4da; border-radius: 6px; font-size: 14px; background: white; cursor: pointer; min-width: 120px;">';
print '<option value="ttc">IVA Incluido</option>';
print '<option value="ht">Sin IVA</option>';
print '</select>';

// Botón de actualización
print '<button type="submit" style="background: #007bff; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: bold; display: flex; align-items: center; gap: 6px;">';
print '<span style="font-size: 12px;">💾</span>';
print '<span>Actualizar</span>';
print '</button>';

print '</form>';
print '</div>';
print '</td></tr>';

print '</table>';
print '</div>';

// 2. Historial de Movimientos y Precios en Pestañas (abajo izquierda)
print '<div style="flex: 1; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; overflow: hidden;">';

// Navegación de pestañas para historiales
print '<div style="display: flex; background: #e9ecef; border-bottom: 1px solid #dee2e6;">';
print '<button type="button" id="tab-stock-history" onclick="switchHistoryTab(\'stock-history\')" style="flex: 1; padding: 12px 16px; border: none; background: #007bff; color: white; cursor: pointer; font-weight: bold; display: flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.3s; font-size: 14px;">';
print '<span style="font-size: 16px;">📋</span>';
print '<span>Historial Stock</span>';
print '</button>';
print '<button type="button" id="tab-price-history" onclick="switchHistoryTab(\'price-history\')" style="flex: 1; padding: 12px 16px; border: none; background: #6c757d; color: white; cursor: pointer; font-weight: bold; display: flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.3s; font-size: 14px;">';
print '<span style="font-size: 16px;">💰</span>';
print '<span>Historial Precios</span>';
print '</button>';
print '</div>';

// Contenido de pestañas de historiales
print '<div style="padding: 20px; height: calc(100% - 60px); display: flex; flex-direction: column;">';

// Pestaña Historial de Stock
print '<div id="content-stock-history" style="display: flex; flex: 1; flex-direction: column;">';
        print '<div style="height: 500px; overflow-y: auto; margin-bottom: 15px;">';
print '<table class="noborder centpercent" style="margin: 0;">';

// Obtener total de movimientos para paginado
$sql_stock_count = "SELECT COUNT(*) as total FROM " . MAIN_DB_PREFIX . "stock_mouvement WHERE fk_product = " . (int)$product_id;
$res_stock_count = $db->query($sql_stock_count);
$total_stock = 0;
if ($res_stock_count) {
    $obj_count = $db->fetch_object($res_stock_count);
    $total_stock = $obj_count->total;
}
$total_stock_pages = ceil($total_stock / $stock_limit);
$stock_offset = ($stock_page - 1) * $stock_limit;

// Obtener historial de movimientos con paginado
$sql_stock_history = "SELECT sm.*, e.ref as entrepot_name, u.login as user_name,
                             CASE 
                                 WHEN sm.value > 0 THEN 'Agregó'
                                 WHEN sm.value < 0 THEN 'Quitó'
                                 ELSE 'Modificó'
                             END as action_type
                      FROM " . MAIN_DB_PREFIX . "stock_mouvement sm
                      LEFT JOIN " . MAIN_DB_PREFIX . "entrepot e ON e.rowid = sm.fk_entrepot
                      LEFT JOIN " . MAIN_DB_PREFIX . "user u ON u.rowid = sm.fk_user_author
                      WHERE sm.fk_product = " . (int)$product_id . "
                      ORDER BY sm.datem DESC
                      LIMIT " . (int)$stock_limit . " OFFSET " . (int)$stock_offset;

$res_stock_history = $db->query($sql_stock_history);
$stock_movements = [];
if ($res_stock_history) {
    while ($movement = $db->fetch_object($res_stock_history)) {
        $stock_movements[] = $movement;
    }
}

if (!empty($stock_movements)) {
    print '<tr style="background: #f8f9fa; font-weight: bold; position: sticky; top: 0; z-index: 10;">';
    print '<td style="padding: 8px; border-bottom: 1px solid #dee2e6;">Fecha</td>';
    print '<td style="padding: 8px; border-bottom: 1px solid #dee2e6;">Tienda</td>';
    print '<td style="padding: 8px; border-bottom: 1px solid #dee2e6;">Cantidad</td>';
    print '<td style="padding: 8px; border-bottom: 1px solid #dee2e6;">Usuario</td>';
    print '<td style="padding: 8px; border-bottom: 1px solid #dee2e6;">Descripción</td>';
    print '</tr>';
    
    foreach ($stock_movements as $movement) {
        $formatted_date = date('d/m/Y H:i', strtotime($movement->datem));
        $value_color = $movement->value > 0 ? '#28a745' : ($movement->value < 0 ? '#dc3545' : '#6c757d');
        $value_prefix = $movement->value > 0 ? '+' : '';
        
        $action_color = $movement->value > 0 ? '#28a745' : ($movement->value < 0 ? '#dc3545' : '#6c757d');
        $user_info = $movement->user_name ?: 'Sistema';
        $action_text = $movement->action_type ?: 'Modificó';
        
        print '<tr style="border-bottom: 1px solid #f1f3f4;">';
        print '<td style="padding: 6px 8px; font-size: 12px; color: #6c757d;">' . $formatted_date . '</td>';
        print '<td style="padding: 6px 8px; font-size: 12px;">' . htmlspecialchars($movement->entrepot_name ?: 'N/A') . '</td>';
        print '<td style="padding: 6px 8px; font-size: 12px; font-weight: bold; color: ' . $value_color . ';">' . $value_prefix . $movement->value . '</td>';
        print '<td style="padding: 6px 8px; font-size: 12px; color: #6c757d;">';
        print '<strong>' . htmlspecialchars($user_info) . '</strong><br>';
        print '<span style="color: ' . $action_color . '; font-size: 11px; font-weight: bold;">' . $action_text . '</span>';
        print '</td>';
        print '<td style="padding: 6px 8px; font-size: 12px;">' . htmlspecialchars($movement->label ?: 'Sin descripción') . '</td>';
        print '</tr>';
    }
} else {
    print '<tr><td colspan="5" style="padding: 20px; text-align: center; color: #6c757d;">No hay movimientos de stock registrados</td></tr>';
}

print '</table>';

// Paginado para historial de stock
if ($total_stock > 0) {
    print '<div style="padding: 20px 15px; text-align: center; border-top: 1px solid #dee2e6; background: #f8f9fa; margin-top: 10px;">';
    print '<div style="display: flex; justify-content: center; align-items: center; gap: 10px; flex-wrap: wrap;">';
    
    // Información de paginado
    print '<span style="color: #6c757d; font-size: 14px;">Página ' . $stock_page . ' de ' . $total_stock_pages . ' (' . $total_stock . ' registros)</span>';
    
    // Solo mostrar navegación si hay más de una página
    if ($total_stock_pages > 1) {
        // Botón anterior
        if ($stock_page > 1) {
            $prev_page = $stock_page - 1;
            print '<a href="?id=' . $product_id . '&stock_page=' . $prev_page . '&price_page=' . $price_page . '&search_term=' . urlencode($search_term) . '&search_id=' . $search_id . '&search_ref=' . urlencode($search_ref) . '&search_name=' . urlencode($search_name) . '&search_desc=' . urlencode($search_desc) . '&limit=' . $limit . '" style="padding: 8px 12px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; font-size: 14px;">← Anterior</a>';
        }
        
        // Números de página
        $start_page = max(1, $stock_page - 2);
        $end_page = min($total_stock_pages, $stock_page + 2);
        
        for ($i = $start_page; $i <= $end_page; $i++) {
            if ($i == $stock_page) {
                print '<span style="padding: 8px 12px; background: #28a745; color: white; border-radius: 4px; font-size: 14px; font-weight: bold;">' . $i . '</span>';
            } else {
                print '<a href="?id=' . $product_id . '&stock_page=' . $i . '&price_page=' . $price_page . '&search_term=' . urlencode($search_term) . '&search_id=' . $search_id . '&search_ref=' . urlencode($search_ref) . '&search_name=' . urlencode($search_name) . '&search_desc=' . urlencode($search_desc) . '&limit=' . $limit . '" style="padding: 8px 12px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px; font-size: 14px;">' . $i . '</a>';
            }
        }
        
        // Botón siguiente
        if ($stock_page < $total_stock_pages) {
            $next_page = $stock_page + 1;
            print '<a href="?id=' . $product_id . '&stock_page=' . $next_page . '&price_page=' . $price_page . '&search_term=' . urlencode($search_term) . '&search_id=' . $search_id . '&search_ref=' . urlencode($search_ref) . '&search_name=' . urlencode($search_name) . '&search_desc=' . urlencode($search_desc) . '&limit=' . $limit . '" style="padding: 8px 12px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; font-size: 14px;">Siguiente →</a>';
        }
    }
    
    print '</div>';
    print '</div>';
}

print '</div>';
print '</div>';

// Pestaña Historial de Precios
print '<div id="content-price-history" style="display: none; flex: 1; flex-direction: column;">';
        print '<div style="height: 500px; overflow-y: auto; margin-bottom: 15px;">';
print '<table class="noborder centpercent" style="margin: 0;">';

// Obtener total de precios para paginado
$sql_price_count = "SELECT COUNT(*) as total FROM " . MAIN_DB_PREFIX . "product_price WHERE fk_product = " . (int)$product_id;
$res_price_count = $db->query($sql_price_count);
$total_price = 0;
if ($res_price_count) {
    $obj_count = $db->fetch_object($res_price_count);
    $total_price = $obj_count->total;
}
$total_price_pages = ceil($total_price / $price_limit);
$price_offset = ($price_page - 1) * $price_limit;

// Obtener historial de precios con paginado
$sql_price_history = "SELECT pp.*, u.login as user_name
                      FROM " . MAIN_DB_PREFIX . "product_price pp
                      LEFT JOIN " . MAIN_DB_PREFIX . "user u ON u.rowid = pp.fk_user_author
                      WHERE pp.fk_product = " . (int)$product_id . "
                      ORDER BY pp.date_price DESC
                      LIMIT " . (int)$price_limit . " OFFSET " . (int)$price_offset;

$res_price_history = $db->query($sql_price_history);
$price_history = [];
if ($res_price_history) {
    while ($price = $db->fetch_object($res_price_history)) {
        $price_history[] = $price;
    }
}

if (!empty($price_history)) {
    print '<tr style="background: #f8f9fa; font-weight: bold; position: sticky; top: 0; z-index: 10;">';
    print '<td style="padding: 8px; border-bottom: 1px solid #dee2e6;">Fecha</td>';
    print '<td style="padding: 8px; border-bottom: 1px solid #dee2e6;">Precio Sin IVA</td>';
    print '<td style="padding: 8px; border-bottom: 1px solid #dee2e6;">Precio Con IVA</td>';
    print '<td style="padding: 8px; border-bottom: 1px solid #dee2e6;">Tipo</td>';
    print '<td style="padding: 8px; border-bottom: 1px solid #dee2e6;">Usuario</td>';
    print '</tr>';
    
    foreach ($price_history as $price) {
        $formatted_date = date('d/m/Y H:i', strtotime($price->date_price));
        $price_ht = $price->price ?: 0;
        $price_ttc = $price->price_ttc ?: 0;
        $price_type = $price->price_base_type == 'HT' ? 'Sin IVA' : 'IVA Incluido';
        $user_info = $price->user_name ?: 'Sistema';
        
        print '<tr style="border-bottom: 1px solid #f1f3f4;">';
        print '<td style="padding: 6px 8px; font-size: 12px; color: #6c757d;">' . $formatted_date . '</td>';
        print '<td style="padding: 6px 8px; font-size: 12px; font-weight: bold; color: #28a745;">' . number_format($price_ht, 2, '.', '') . ' €</td>';
        print '<td style="padding: 6px 8px; font-size: 12px; font-weight: bold; color: #007bff;">' . number_format($price_ttc, 2, '.', '') . ' €</td>';
        print '<td style="padding: 6px 8px; font-size: 12px; color: #6c757d;">' . $price_type . '</td>';
        print '<td style="padding: 6px 8px; font-size: 12px; color: #6c757d;">' . htmlspecialchars($user_info) . '</td>';
        print '</tr>';
    }
} else {
    print '<tr><td colspan="5" style="padding: 20px; text-align: center; color: #6c757d;">No hay historial de precios registrado</td></tr>';
}

print '</table>';

// Paginado para historial de precios
if ($total_price > 0) {
    print '<div style="padding: 20px 15px; text-align: center; border-top: 1px solid #dee2e6; background: #f8f9fa; margin-top: 10px;">';
    print '<div style="display: flex; justify-content: center; align-items: center; gap: 10px; flex-wrap: wrap;">';
    
    // Información de paginado
    print '<span style="color: #6c757d; font-size: 14px;">Página ' . $price_page . ' de ' . $total_price_pages . ' (' . $total_price . ' registros)</span>';
    
    // Solo mostrar navegación si hay más de una página
    if ($total_price_pages > 1) {
        // Botón anterior
        if ($price_page > 1) {
            $prev_page = $price_page - 1;
            print '<a href="?id=' . $product_id . '&stock_page=' . $stock_page . '&price_page=' . $prev_page . '&search_term=' . urlencode($search_term) . '&search_id=' . $search_id . '&search_ref=' . urlencode($search_ref) . '&search_name=' . urlencode($search_name) . '&search_desc=' . urlencode($search_desc) . '&limit=' . $limit . '" style="padding: 8px 12px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; font-size: 14px;">← Anterior</a>';
        }
        
        // Números de página
        $start_page = max(1, $price_page - 2);
        $end_page = min($total_price_pages, $price_page + 2);
        
        for ($i = $start_page; $i <= $end_page; $i++) {
            if ($i == $price_page) {
                print '<span style="padding: 8px 12px; background: #28a745; color: white; border-radius: 4px; font-size: 14px; font-weight: bold;">' . $i . '</span>';
            } else {
                print '<a href="?id=' . $product_id . '&stock_page=' . $stock_page . '&price_page=' . $i . '&search_term=' . urlencode($search_term) . '&search_id=' . $search_id . '&search_ref=' . urlencode($search_ref) . '&search_name=' . urlencode($search_name) . '&search_desc=' . urlencode($search_desc) . '&limit=' . $limit . '" style="padding: 8px 12px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px; font-size: 14px;">' . $i . '</a>';
            }
        }
        
        // Botón siguiente
        if ($price_page < $total_price_pages) {
            $next_page = $price_page + 1;
            print '<a href="?id=' . $product_id . '&stock_page=' . $stock_page . '&price_page=' . $next_page . '&search_term=' . urlencode($search_term) . '&search_id=' . $search_id . '&search_ref=' . urlencode($search_ref) . '&search_name=' . urlencode($search_name) . '&search_desc=' . urlencode($search_desc) . '&limit=' . $limit . '" style="padding: 8px 12px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; font-size: 14px;">Siguiente →</a>';
        }
    }
    
    print '</div>';
    print '</div>';
}

print '</div>';
print '</div>';

print '</div>'; // Fin del contenido de pestañas de historiales
print '</div>'; // Fin del contenedor de pestañas de historiales

print '</div>'; // Cerrar columna izquierda

// COLUMNA DERECHA
print '<div style="flex: 1; display: flex; flex-direction: column; gap: 20px;">';

// 3. Gestión de Stock por Tienda (arriba derecha)
print '<div style="flex: 0 0 auto;">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td colspan="2">📦 Gestión de Stock por Tienda</td></tr>';

// Calcular stock específico para HERNAN CORTES y CARPETANA
$hernan_cortes_stock = 0;
$carpetana_stock = 0;

// Consultar stock de HERNAN CORTES
$sql_hernan = "SELECT SUM(sm.value) as stock_quantity
               FROM " . MAIN_DB_PREFIX . "stock_mouvement sm
               INNER JOIN " . MAIN_DB_PREFIX . "entrepot e ON e.rowid = sm.fk_entrepot
               WHERE sm.fk_product = " . (int)$product_id . " AND e.ref = 'HERNAN CORTES'";
$res_hernan = $db->query($sql_hernan);
if ($res_hernan) {
    $data_hernan = $db->fetch_object($res_hernan);
    $hernan_cortes_stock = $data_hernan && $data_hernan->stock_quantity ? $data_hernan->stock_quantity : 0;
}

// Consultar stock de CARPETANA
$sql_carpetana = "SELECT SUM(sm.value) as stock_quantity
                  FROM " . MAIN_DB_PREFIX . "stock_mouvement sm
                  INNER JOIN " . MAIN_DB_PREFIX . "entrepot e ON e.rowid = sm.fk_entrepot
                  WHERE sm.fk_product = " . (int)$product_id . " AND e.ref = 'CARPETANA'";
$res_carpetana = $db->query($sql_carpetana);
if ($res_carpetana) {
    $data_carpetana = $db->fetch_object($res_carpetana);
    $carpetana_stock = $data_carpetana && $data_carpetana->stock_quantity ? $data_carpetana->stock_quantity : 0;
}

$our_total_stock = $hernan_cortes_stock + $carpetana_stock;


// Mostrar información de stock real
print '<tr><td><strong>Unidades en Nuestras Tiendas:</strong></td><td><strong style="color: #007bff; font-size: 16px;">' . $our_total_stock . ' unidades</strong></td></tr>';

if (isset($product->last_stock_date) && $product->last_stock_date) {
    $formatted_date = date('d/m/Y H:i', strtotime($product->last_stock_date));
    
    print '<tr><td>Última actualización:</td><td>' . $formatted_date . '</td></tr>';
}

print '<tr><td colspan="2"><hr style="margin: 10px 0;"></td></tr>';

// Mostrar HERNAN CORTES
print '<tr><td>🏪 HERNAN CORTES:</td><td>';
print '<strong style="color: #495057; font-size: 16px;">' . $hernan_cortes_stock . '</strong>';
print ' <span style="color: #666; font-size: 12px;">unidades</span>';
print '</td></tr>';

// Mostrar CARPETANA
print '<tr><td>🏪 CARPETANA:</td><td>';
print '<strong style="color: #495057; font-size: 16px;">' . $carpetana_stock . '</strong>';
print ' <span style="color: #666; font-size: 12px;">unidades</span>';
print '</td></tr>';

print '</table>';
print '</div>';

// 4. Gestión de Stock con Pestañas (abajo derecha)
print '<div style="flex: 1; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; overflow: hidden;">';

// Navegación de pestañas
print '<div style="display: flex; background: #e9ecef; border-bottom: 1px solid #dee2e6;">';
print '<button type="button" id="tab-add-remove" onclick="switchTab(\'add-remove\')" style="flex: 1; padding: 12px 16px; border: none; background: #007bff; color: white; cursor: pointer; font-weight: bold; display: flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.3s; font-size: 14px;">';
print '<span style="font-size: 16px;">➕</span>';
print '<span>Agregar/Quitar</span>';
print '</button>';
print '<button type="button" id="tab-transfer" onclick="switchTab(\'transfer\')" style="flex: 1; padding: 12px 16px; border: none; background: #6c757d; color: white; cursor: pointer; font-weight: bold; display: flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.3s; font-size: 14px;">';
print '<span style="font-size: 16px;">🔄</span>';
print '<span>Transferir</span>';
print '</button>';
print '</div>';

// Contenido de pestañas
print '<div style="padding: 20px;">';

// Pestaña Agregar/Quitar Stock
print '<div id="content-add-remove" style="display: block;">';

print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '" onsubmit="return validateAddStockForm()">';
print '<input type="hidden" name="action" value="manage_stock">';
print '<input type="hidden" name="id" value="' . $product_id . '">';

print '<table style="width: 100%;">';
print '<tr>';
print '<td style="padding: 8px 0; width: 30%; font-weight: bold;">Acción:</td>';
print '<td style="padding: 8px 0;">';
print '<select name="action_type" required style="width: 100%; padding: 10px; border: 2px solid #ced4da; border-radius: 6px; font-size: 14px; transition: border-color 0.3s;" onfocus="this.style.borderColor=\'#007bff\'" onblur="this.style.borderColor=\'#ced4da\'">';
print '<option value="">Seleccionar acción...</option>';
print '<option value="add">➕ Agregar Stock</option>';
print '<option value="remove">➖ Quitar Stock</option>';
print '</select>';
print '</td>';
print '</tr>';

print '<tr>';
print '<td style="padding: 8px 0; font-weight: bold;">Tienda:</td>';
print '<td style="padding: 8px 0;">';
print '<select name="single_store" required style="width: 100%; padding: 10px; border: 2px solid #ced4da; border-radius: 6px; font-size: 14px; transition: border-color 0.3s;" onfocus="this.style.borderColor=\'#007bff\'" onblur="this.style.borderColor=\'#ced4da\'">';
print '<option value="">Seleccionar tienda...</option>';

// Generar opciones desde la base de datos
echo "<!-- DEBUG GENERANDO OPCIONES SINGLE_STORE: " . count($available_stores) . " tiendas disponibles -->";
echo "<!-- DEBUG AVAILABLE_STORES EN DROPDOWN: " . print_r($available_stores, true) . " -->";

if (empty($available_stores)) {
    echo "<!-- DEBUG: available_stores está VACÍO en el dropdown -->";
    // Forzar las tiendas si están vacías
    $available_stores = [
        ['id' => 4, 'ref' => 'HERNAN CORTES'],
        ['id' => 5, 'ref' => 'CARPETANA']
    ];
    echo "<!-- DEBUG: Usando tiendas forzadas -->";
}

foreach ($available_stores as $store) {
    echo "<!-- DEBUG OPCION SINGLE_STORE: " . $store['ref'] . " -->";
    print '<option value="' . htmlspecialchars($store['ref']) . '">' . htmlspecialchars($store['ref']) . '</option>';
}

print '</select>';
print '</td>';
print '</tr>';

print '<tr>';
print '<td style="padding: 8px 0; font-weight: bold;">Cantidad:</td>';
print '<td style="padding: 8px 0;">';
print '<div style="display: flex; align-items: center; gap: 8px;">';
print '<button type="button" onclick="decreaseQuantity()" style="background: #dc3545; color: white; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer; font-weight: bold; transition: all 0.3s;" onmouseover="this.style.transform=\'scale(1.05)\'" onmouseout="this.style.transform=\'scale(1)\'">-</button>';
print '<input type="number" id="quantity" name="quantity" value="1" min="1" required style="width: 100px; padding: 10px; border: 2px solid #ced4da; border-radius: 6px; text-align: center; font-size: 14px; font-weight: bold; transition: border-color 0.3s;" onfocus="this.style.borderColor=\'#007bff\'" onblur="this.style.borderColor=\'#ced4da\'">';
print '<button type="button" onclick="increaseQuantity()" style="background: #28a745; color: white; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer; font-weight: bold; transition: all 0.3s;" onmouseover="this.style.transform=\'scale(1.05)\'" onmouseout="this.style.transform=\'scale(1)\'">+</button>';
print '</div>';
print '</td>';
print '</tr>';

print '<tr>';
print '<td colspan="2" style="padding: 15px 0; text-align: center;">';
print '<button type="submit" style="background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 14px; box-shadow: 0 2px 4px rgba(0,123,255,0.3); transition: all 0.3s;" onmouseover="this.style.transform=\'translateY(-1px)\'; this.style.boxShadow=\'0 4px 8px rgba(0,123,255,0.4)\'" onmouseout="this.style.transform=\'translateY(0)\'; this.style.boxShadow=\'0 2px 4px rgba(0,123,255,0.3)\'">Ejecutar</button>';
print '</td>';
print '</tr>';
print '</table>';

print '</form>';
print '</div>';

// Pestaña Transferir Stock
print '<div id="content-transfer" style="display: none;">';

print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '">';
print '<input type="hidden" name="action" value="manage_stock">';
print '<input type="hidden" name="action_type" value="transfer">';
print '<input type="hidden" name="id" value="' . $product_id . '">';

print '<table style="width: 100%;">';
print '<tr>';
print '<td style="padding: 8px 0; width: 30%; font-weight: bold;">Desde:</td>';
print '<td style="padding: 8px 0;">';
print '<select name="from_store" required style="width: 100%; padding: 10px; border: 2px solid #ced4da; border-radius: 6px; font-size: 14px; transition: border-color 0.3s;" onfocus="this.style.borderColor=\'#007bff\'" onblur="this.style.borderColor=\'#ced4da\'">';
print '<option value="">Seleccionar tienda origen...</option>';

// Generar opciones desde la base de datos
echo "<!-- DEBUG GENERANDO OPCIONES FROM_STORE: " . count($available_stores) . " tiendas disponibles -->";
echo "<!-- DEBUG AVAILABLE_STORES EN FROM_STORE: " . print_r($available_stores, true) . " -->";

if (empty($available_stores)) {
    echo "<!-- DEBUG: available_stores está VACÍO en from_store -->";
    // Forzar las tiendas si están vacías
    $available_stores = [
        ['id' => 4, 'ref' => 'HERNAN CORTES'],
        ['id' => 5, 'ref' => 'CARPETANA']
    ];
    echo "<!-- DEBUG: Usando tiendas forzadas en from_store -->";
}

foreach ($available_stores as $store) {
    echo "<!-- DEBUG OPCION FROM_STORE: " . $store['ref'] . " -->";
    print '<option value="' . htmlspecialchars($store['ref']) . '">' . htmlspecialchars($store['ref']) . '</option>';
}

print '</select>';
print '</td>';
print '</tr>';

print '<tr>';
print '<td style="padding: 8px 0; font-weight: bold;">Hacia:</td>';
print '<td style="padding: 8px 0;">';
print '<select name="to_store" required style="width: 100%; padding: 10px; border: 2px solid #ced4da; border-radius: 6px; font-size: 14px; transition: border-color 0.3s;" onfocus="this.style.borderColor=\'#007bff\'" onblur="this.style.borderColor=\'#ced4da\'">';
print '<option value="">Seleccionar tienda destino...</option>';

// Generar opciones desde la base de datos
echo "<!-- DEBUG GENERANDO OPCIONES TO_STORE: " . count($available_stores) . " tiendas disponibles -->";
echo "<!-- DEBUG AVAILABLE_STORES EN TO_STORE: " . print_r($available_stores, true) . " -->";

if (empty($available_stores)) {
    echo "<!-- DEBUG: available_stores está VACÍO en to_store -->";
    // Forzar las tiendas si están vacías
    $available_stores = [
        ['id' => 4, 'ref' => 'HERNAN CORTES'],
        ['id' => 5, 'ref' => 'CARPETANA']
    ];
    echo "<!-- DEBUG: Usando tiendas forzadas en to_store -->";
}

foreach ($available_stores as $store) {
    echo "<!-- DEBUG OPCION TO_STORE: " . $store['ref'] . " -->";
    print '<option value="' . htmlspecialchars($store['ref']) . '">' . htmlspecialchars($store['ref']) . '</option>';
}

print '</select>';
print '</td>';
print '</tr>';

print '<tr>';
print '<td style="padding: 8px 0; font-weight: bold;">Cantidad:</td>';
print '<td style="padding: 8px 0;">';
print '<div style="display: flex; align-items: center; gap: 8px;">';
print '<button type="button" onclick="decreaseTransferQuantity()" style="background: #dc3545; color: white; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer; font-weight: bold; transition: all 0.3s;" onmouseover="this.style.transform=\'scale(1.05)\'" onmouseout="this.style.transform=\'scale(1)\'">-</button>';
print '<input type="number" id="transfer_quantity" name="quantity" value="1" min="1" required style="width: 100px; padding: 10px; border: 2px solid #ced4da; border-radius: 6px; text-align: center; font-size: 14px; font-weight: bold; transition: border-color 0.3s;" onfocus="this.style.borderColor=\'#007bff\'" onblur="this.style.borderColor=\'#ced4da\'">';
print '<button type="button" onclick="increaseTransferQuantity()" style="background: #28a745; color: white; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer; font-weight: bold; transition: all 0.3s;" onmouseover="this.style.transform=\'scale(1.05)\'" onmouseout="this.style.transform=\'scale(1)\'">+</button>';
print '</div>';
print '</td>';
print '</tr>';

print '<tr>';
print '<td colspan="2" style="padding: 15px 0; text-align: center;">';
print '<button type="submit" style="background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%); color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 14px; box-shadow: 0 2px 4px rgba(40,167,69,0.3); transition: all 0.3s;" onmouseover="this.style.transform=\'translateY(-1px)\'; this.style.boxShadow=\'0 4px 8px rgba(40,167,69,0.4)\'" onmouseout="this.style.transform=\'translateY(0)\'; this.style.boxShadow=\'0 2px 4px rgba(40,167,69,0.3)\'">Transferir</button>';
print '</td>';
print '</tr>';
print '</table>';

print '</form>';
print '</div>';
print '</div>';

print '</div>'; // Cerrar columna derecha
print '</div>'; // Cerrar layout principal

// Espacio adicional en la parte inferior para separar del menú
print '<div style="height: 60px; margin-top: 30px;"></div>';





// JavaScript para pestañas y validación
print '<script>
// Función para cambiar pestañas de gestión de stock
function switchTab(tabName) {
    // Ocultar todos los contenidos
    document.getElementById("content-add-remove").style.display = "none";
    document.getElementById("content-transfer").style.display = "none";
    
    // Desactivar todos los botones
    document.getElementById("tab-add-remove").style.background = "#6c757d";
    document.getElementById("tab-transfer").style.background = "#6c757d";
    
    // Mostrar el contenido seleccionado
    if (tabName === "add-remove") {
        document.getElementById("content-add-remove").style.display = "block";
        document.getElementById("tab-add-remove").style.background = "#007bff";
    } else if (tabName === "transfer") {
        document.getElementById("content-transfer").style.display = "block";
        document.getElementById("tab-transfer").style.background = "#007bff";
    }
}

// Función para cambiar pestañas de historiales
function switchHistoryTab(tabName) {
    // Ocultar todos los contenidos
    document.getElementById("content-stock-history").style.display = "none";
    document.getElementById("content-price-history").style.display = "none";
    
    // Desactivar todos los botones
    document.getElementById("tab-stock-history").style.background = "#6c757d";
    document.getElementById("tab-price-history").style.background = "#6c757d";
    
    // Mostrar el contenido seleccionado
    if (tabName === "stock-history") {
        document.getElementById("content-stock-history").style.display = "flex";
        document.getElementById("tab-stock-history").style.background = "#007bff";
    } else if (tabName === "price-history") {
        document.getElementById("content-price-history").style.display = "flex";
        document.getElementById("tab-price-history").style.background = "#007bff";
    }
}

// Funciones para botones de cantidad
function increaseQuantity() {
    var quantityInput = document.getElementById("quantity");
    var currentValue = parseInt(quantityInput.value) || 0;
    quantityInput.value = currentValue + 1;
}

function decreaseQuantity() {
    var quantityInput = document.getElementById("quantity");
    var currentValue = parseInt(quantityInput.value) || 1;
    if (currentValue > 1) {
        quantityInput.value = currentValue - 1;
    }
}

function increaseTransferQuantity() {
    var quantityInput = document.getElementById("transfer_quantity");
    var currentValue = parseInt(quantityInput.value) || 0;
    quantityInput.value = currentValue + 1;
}

function decreaseTransferQuantity() {
    var quantityInput = document.getElementById("transfer_quantity");
    var currentValue = parseInt(quantityInput.value) || 1;
    if (currentValue > 1) {
        quantityInput.value = currentValue - 1;
    }
}

// JavaScript para debug y validación
function validateAddStockForm() {
    var actionType = document.querySelector("select[name=\'action_type\']").value;
    var singleStore = document.querySelector("select[name=\'single_store\']").value;
    var quantity = document.querySelector("input[name=\'quantity\']").value;
    
    console.log("DEBUG FORM:");
    console.log("action_type:", actionType);
    console.log("single_store:", singleStore);
    console.log("quantity:", quantity);
    
    if (!actionType) {
        alert("Por favor selecciona una acción");
        return false;
    }
    
    if (!singleStore) {
        alert("Por favor selecciona una tienda. DEBUG: single_store=\'" + singleStore + "\'");
        return false;
    }
    
    if (!quantity || quantity <= 0) {
        alert("Por favor ingresa una cantidad válida");
        return false;
    }
    
    return true;
}
</script>';

// Page footer
if (function_exists('llxFooter')) {
    llxFooter();
} else {
echo "</body></html>";
}
?>
