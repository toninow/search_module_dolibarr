<?php
// Script para sincronizar stock entre llx_stock_mouvement y llx_product_stock
require_once '../../main.inc.php';

if (!$user->admin) {
    die("Solo administradores pueden ejecutar este script");
}

echo "<h2>Sincronizando Stock entre Movimientos y Product_Stock</h2>";

// Obtener todos los productos que tienen movimientos de stock
$sql_products = "SELECT DISTINCT fk_product FROM " . MAIN_DB_PREFIX . "stock_mouvement ORDER BY fk_product";
$res_products = $db->query($sql_products);

$total_updated = 0;
$total_errors = 0;

while ($product = $db->fetch_object($res_products)) {
    $product_id = $product->fk_product;
    
    // Obtener todas las tiendas que tienen movimientos para este producto
    $sql_entrepots = "SELECT DISTINCT fk_entrepot FROM " . MAIN_DB_PREFIX . "stock_mouvement WHERE fk_product = " . (int)$product_id;
    $res_entrepots = $db->query($sql_entrepots);
    
    while ($entrepot = $db->fetch_object($res_entrepots)) {
        $entrepot_id = $entrepot->fk_entrepot;
        
        // Calcular stock real basado en movimientos
        $sql_calc_stock = "SELECT SUM(value) as total_stock FROM " . MAIN_DB_PREFIX . "stock_mouvement 
                          WHERE fk_product = " . (int)$product_id . " AND fk_entrepot = " . (int)$entrepot_id;
        $res_calc_stock = $db->query($sql_calc_stock);
        $calc_stock_obj = $db->fetch_object($res_calc_stock);
        $real_stock = $calc_stock_obj ? $calc_stock_obj->total_stock : 0;
        
        // Actualizar o insertar en product_stock
        $sql_update_stock = "INSERT INTO " . MAIN_DB_PREFIX . "product_stock (fk_product, fk_entrepot, reel) 
                            VALUES (" . (int)$product_id . ", " . (int)$entrepot_id . ", " . (int)$real_stock . ")
                            ON DUPLICATE KEY UPDATE reel = " . (int)$real_stock;
        
        if ($db->query($sql_update_stock)) {
            $total_updated++;
            echo "✅ Producto $product_id, Tienda $entrepot_id: Stock actualizado a $real_stock<br>";
        } else {
            $total_errors++;
            echo "❌ Error actualizando producto $product_id, tienda $entrepot_id: " . $db->lasterror() . "<br>";
        }
    }
}

echo "<br><h3>Resumen:</h3>";
echo "✅ Registros actualizados: $total_updated<br>";
echo "❌ Errores: $total_errors<br>";

if ($total_errors == 0) {
    echo "<br><strong style='color: green;'>¡Sincronización completada exitosamente!</strong><br>";
    echo "<a href='edit.php?id=7301'>Volver al producto de prueba</a>";
} else {
    echo "<br><strong style='color: red;'>Hubo errores durante la sincronización.</strong><br>";
}
?>
