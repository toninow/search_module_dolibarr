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

echo "<h2>🔍 ANÁLISIS ESPECÍFICO: SAVAREZ 520R</h2>";

// Buscar específicamente los productos 520R
$sql = "SELECT rowid, ref, label, description, status, date_creation, stock
        FROM " . MAIN_DB_PREFIX . "product 
        WHERE entity = " . $conf->entity . " 
        AND (label LIKE '%520R%' OR ref LIKE '%520R%' OR description LIKE '%520R%')
        ORDER BY rowid";

$resql = $db->query($sql);
if ($resql) {
    echo "<h3>📊 PRODUCTOS SAVAREZ 520R ENCONTRADOS:</h3>";
    
    $products = array();
    while ($obj = $db->fetch_object($resql)) {
        $products[] = $obj;
    }
    
    if (count($products) > 0) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 20px 0;'>";
        echo "<tr style='background: #f8f9fa; font-weight: bold;'>";
        echo "<th style='padding: 15px; text-align: left;'>ID</th>";
        echo "<th style='padding: 15px; text-align: left;'>REFERENCIA</th>";
        echo "<th style='padding: 15px; text-align: left;'>NOMBRE</th>";
        echo "<th style='padding: 15px; text-align: left;'>ESTADO</th>";
        echo "<th style='padding: 15px; text-align: left;'>FECHA CREACIÓN</th>";
        echo "<th style='padding: 15px; text-align: left;'>STOCK</th>";
        echo "<th style='padding: 15px; text-align: left;'>COINCIDENCIA</th>";
        echo "<th style='padding: 15px; text-align: left;'>ACCIONES</th>";
        echo "</tr>";
        
        foreach ($products as $index => $product) {
            $is_duplicate = false;
            $duplicate_count = 0;
            
            // Verificar si es duplicado comparando con otros productos
            foreach ($products as $other_index => $other_product) {
                if ($index != $other_index) {
                    if (strcasecmp(trim($product->label), trim($other_product->label)) == 0) {
                        $is_duplicate = true;
                        $duplicate_count++;
                    }
                }
            }
            
            $row_color = $is_duplicate ? '#ffebee' : '#e8f5e8';
            $status_icon = $product->status == 1 ? '✅ Activo' : '❌ Inactivo';
            $coincidence = $is_duplicate ? '🚨 DUPLICADO' : '⚡ ÚNICO';
            
            echo "<tr style='background: " . $row_color . ";'>";
            echo "<td style='padding: 15px; font-weight: bold;'>" . $product->rowid . "</td>";
            echo "<td style='padding: 15px; font-weight: bold; color: #007bff;'>" . htmlspecialchars($product->ref) . "</td>";
            echo "<td style='padding: 15px;'>" . htmlspecialchars($product->label) . "</td>";
            echo "<td style='padding: 15px;'>" . $status_icon . "</td>";
            echo "<td style='padding: 15px;'>" . date('d/m/Y H:i', $product->date_creation) . "</td>";
            echo "<td style='padding: 15px; text-align: center; font-weight: bold;'>" . $product->stock . "</td>";
            echo "<td style='padding: 15px; text-align: center; font-weight: bold; color: " . ($is_duplicate ? '#d63031' : '#00b894') . ";'>" . $coincidence . "</td>";
            echo "<td style='padding: 15px;'>";
            echo "<a href='" . DOL_URL_ROOT . "/product/card.php?id=" . $product->rowid . "' target='_blank' style='background: #007bff; color: white; padding: 8px 12px; text-decoration: none; border-radius: 4px; margin-right: 5px;'>Ver</a>";
            echo "<a href='" . DOL_URL_ROOT . "/product/card.php?id=" . $product->rowid . "&action=edit' target='_blank' style='background: #28a745; color: white; padding: 8px 12px; text-decoration: none; border-radius: 4px; margin-right: 5px;'>Editar</a>";
            if ($is_duplicate) {
                echo "<a href='#' onclick='confirmDelete(" . $product->rowid . ")' style='background: #dc3545; color: white; padding: 8px 12px; text-decoration: none; border-radius: 4px;'>Eliminar</a>";
            }
            echo "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
        
        // Análisis de duplicados
        echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
        echo "<h4 style='color: #856404; margin-top: 0;'>🔍 ANÁLISIS DE DUPLICADOS:</h4>";
        
        $duplicate_groups = array();
        foreach ($products as $product) {
            $key = strtolower(trim($product->label));
            if (!isset($duplicate_groups[$key])) {
                $duplicate_groups[$key] = array();
            }
            $duplicate_groups[$key][] = $product;
        }
        
        foreach ($duplicate_groups as $group_name => $group_products) {
            if (count($group_products) > 1) {
                echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 6px; margin: 10px 0;'>";
                echo "<h5 style='color: #721c24; margin: 0 0 10px 0;'>🚨 GRUPO DE DUPLICADOS: " . htmlspecialchars($group_name) . "</h5>";
                echo "<p><strong>Productos duplicados:</strong> " . count($group_products) . "</p>";
                
                $total_stock = 0;
                foreach ($group_products as $dup_product) {
                    $total_stock += $dup_product->stock;
                    echo "<p>• ID: " . $dup_product->rowid . " | Ref: " . $dup_product->ref . " | Stock: " . $dup_product->stock . "</p>";
                }
                
                echo "<p><strong>Stock total combinado:</strong> " . $total_stock . "</p>";
                echo "<p><strong>Recomendación:</strong> Mantener el producto más reciente y sumar el stock al eliminado.</p>";
                echo "</div>";
            }
        }
        
        echo "</div>";
        
    } else {
        echo "<p>❌ No se encontraron productos con 520R</p>";
    }
    
} else {
    echo "<p>❌ Error en la consulta: " . $db->lasterror() . "</p>";
}

// JavaScript para confirmar eliminación
echo "<script>";
echo "function confirmDelete(productId) {";
echo "    if (confirm('¿Estás seguro de que quieres eliminar este producto duplicado?')) {";
echo "        window.open('" . DOL_URL_ROOT . "/product/card.php?id=' + productId + '&action=delete', '_blank');";
echo "    }";
echo "}";
echo "</script>";

echo "<div style='background: #d1ecf1; border: 1px solid #bee5eb; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<h4 style='color: #0c5460; margin-top: 0;'>💡 INSTRUCCIONES:</h4>";
echo "<ol>";
echo "<li><strong>Revisa los productos duplicados</strong> marcados en rojo</li>";
echo "<li><strong>Decide cuál mantener</strong> (recomendado: el más reciente)</li>";
echo "<li><strong>Suma el stock</strong> antes de eliminar el duplicado</li>";
echo "<li><strong>Elimina el duplicado</strong> usando el botón rojo</li>";
echo "<li><strong>Actualiza la referencia</strong> si es necesario</li>";
echo "</ol>";
echo "</div>";

echo "<p><a href='index.php' style='background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;'>← Volver al módulo de búsqueda</a></p>";
?>


