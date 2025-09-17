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

echo "<h2>🧠 DETECCIÓN INTELIGENTE DE DUPLICADOS</h2>";
echo "<p>Analizando nombre, descripción, referencia y otros campos para detectar duplicados reales...</p>";

// Función para calcular similitud entre textos
function calculateSimilarity($text1, $text2) {
    $text1 = strtolower(trim($text1));
    $text2 = strtolower(trim($text2));
    
    if (empty($text1) || empty($text2)) return 0;
    
    // Similitud exacta
    if ($text1 === $text2) return 100;
    
    // Similitud por palabras comunes
    $words1 = explode(' ', preg_replace('/[^a-z0-9\s]/', ' ', $text1));
    $words2 = explode(' ', preg_replace('/[^a-z0-9\s]/', ' ', $text2));
    
    $words1 = array_filter($words1, function($w) { return strlen($w) > 2; });
    $words2 = array_filter($words2, function($w) { return strlen($w) > 2; });
    
    $common_words = array_intersect($words1, $words2);
    $total_words = array_unique(array_merge($words1, $words2));
    
    if (count($total_words) == 0) return 0;
    
    return (count($common_words) / count($total_words)) * 100;
}

// Obtener todos los productos
$sql = "SELECT rowid, ref, label, description, note_public, note_private, barcode, supplier_ref, 
               status, date_creation, stock, price, price_ttc
        FROM " . MAIN_DB_PREFIX . "product 
        WHERE entity = " . $conf->entity . " 
        AND (label != '' OR description != '')
        ORDER BY label, ref";

$resql = $db->query($sql);
if ($resql) {
    $products = array();
    while ($obj = $db->fetch_object($resql)) {
        $products[] = $obj;
    }
    
    echo "<h3>📊 ANÁLISIS COMPLETO DE " . count($products) . " PRODUCTOS:</h3>";
    
    $duplicate_groups = array();
    $processed = array();
    
    foreach ($products as $index1 => $product1) {
        if (in_array($index1, $processed)) continue;
        
        $similar_products = array($product1);
        
        foreach ($products as $index2 => $product2) {
            if ($index1 >= $index2) continue;
            if (in_array($index2, $processed)) continue;
            
            // Calcular similitud en múltiples campos
            $name_similarity = calculateSimilarity($product1->label, $product2->label);
            $desc_similarity = calculateSimilarity($product1->description, $product2->description);
            $ref_similarity = calculateSimilarity($product1->ref, $product2->ref);
            $barcode_similarity = calculateSimilarity($product1->barcode, $product2->barcode);
            $supplier_similarity = calculateSimilarity($product1->supplier_ref, $product2->supplier_ref);
            
            // Ponderar la similitud (nombre es más importante)
            $total_similarity = ($name_similarity * 0.4) + 
                               ($desc_similarity * 0.3) + 
                               ($ref_similarity * 0.15) + 
                               ($barcode_similarity * 0.1) + 
                               ($supplier_similarity * 0.05);
            
            // Si la similitud es alta, considerarlo duplicado
            if ($total_similarity >= 70) {
                $similar_products[] = $product2;
                $processed[] = $index2;
            }
        }
        
        if (count($similar_products) > 1) {
            $processed[] = $index1;
            $duplicate_groups[] = $similar_products;
        }
    }
    
    // Mostrar resultados
    if (count($duplicate_groups) > 0) {
        echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
        echo "<h4 style='color: #155724; margin-top: 0;'>✅ " . count($duplicate_groups) . " GRUPOS DE DUPLICADOS ENCONTRADOS</h4>";
        echo "</div>";
        
        foreach ($duplicate_groups as $group_index => $group) {
            echo "<div style='border: 2px solid #ff6b6b; margin: 20px 0; padding: 20px; background: #ffe0e0; border-radius: 8px;'>";
            echo "<h4 style='color: #d63031; margin: 0 0 15px 0;'>🚨 GRUPO " . ($group_index + 1) . " - " . count($group) . " PRODUCTOS DUPLICADOS</h4>";
            
            // Calcular similitudes detalladas
            $base_product = $group[0];
            echo "<p><strong>Producto base:</strong> " . htmlspecialchars($base_product->label) . "</p>";
            
            $total_stock = 0;
            $active_count = 0;
            $inactive_count = 0;
            
            echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 15px 0;'>";
            echo "<tr style='background: #f8f9fa; font-weight: bold;'>";
            echo "<th style='padding: 12px; text-align: left;'>ID</th>";
            echo "<th style='padding: 12px; text-align: left;'>REFERENCIA</th>";
            echo "<th style='padding: 12px; text-align: left;'>NOMBRE</th>";
            echo "<th style='padding: 12px; text-align: left;'>DESCRIPCIÓN</th>";
            echo "<th style='padding: 12px; text-align: left;'>CÓDIGO BARRAS</th>";
            echo "<th style='padding: 12px; text-align: left;'>REF. PROVEEDOR</th>";
            echo "<th style='padding: 12px; text-align: left;'>ESTADO</th>";
            echo "<th style='padding: 12px; text-align: left;'>STOCK</th>";
            echo "<th style='padding: 12px; text-align: left;'>SIMILITUD</th>";
            echo "<th style='padding: 12px; text-align: left;'>ACCIONES</th>";
            echo "</tr>";
            
            foreach ($group as $product) {
                $name_sim = calculateSimilarity($base_product->label, $product->label);
                $desc_sim = calculateSimilarity($base_product->description, $product->description);
                $ref_sim = calculateSimilarity($base_product->ref, $product->ref);
                $barcode_sim = calculateSimilarity($base_product->barcode, $product->barcode);
                $supplier_sim = calculateSimilarity($base_product->supplier_ref, $product->supplier_ref);
                
                $overall_sim = ($name_sim * 0.4) + ($desc_sim * 0.3) + ($ref_sim * 0.15) + ($barcode_sim * 0.1) + ($supplier_sim * 0.05);
                
                $status_icon = $product->status == 1 ? '✅ Activo' : '❌ Inactivo';
                $sim_color = $overall_sim >= 90 ? '#28a745' : ($overall_sim >= 70 ? '#ffc107' : '#dc3545');
                
                echo "<tr>";
                echo "<td style='padding: 12px; font-weight: bold;'>" . $product->rowid . "</td>";
                echo "<td style='padding: 12px; font-weight: bold; color: #007bff;'>" . htmlspecialchars($product->ref) . "</td>";
                echo "<td style='padding: 12px;'>" . htmlspecialchars($product->label) . "</td>";
                echo "<td style='padding: 12px; font-size: 12px; max-width: 200px;'>" . htmlspecialchars(substr($product->description, 0, 100)) . "...</td>";
                echo "<td style='padding: 12px;'>" . htmlspecialchars($product->barcode) . "</td>";
                echo "<td style='padding: 12px;'>" . htmlspecialchars($product->supplier_ref) . "</td>";
                echo "<td style='padding: 12px;'>" . $status_icon . "</td>";
                echo "<td style='padding: 12px; text-align: center; font-weight: bold;'>" . $product->stock . "</td>";
                echo "<td style='padding: 12px; text-align: center; font-weight: bold; color: " . $sim_color . ";'>" . number_format($overall_sim, 1) . "%</td>";
                echo "<td style='padding: 12px;'>";
                echo "<a href='" . DOL_URL_ROOT . "/product/card.php?id=" . $product->rowid . "' target='_blank' style='background: #007bff; color: white; padding: 6px 10px; text-decoration: none; border-radius: 4px; margin-right: 3px; font-size: 12px;'>Ver</a>";
                echo "<a href='" . DOL_URL_ROOT . "/product/card.php?id=" . $product->rowid . "&action=edit' target='_blank' style='background: #28a745; color: white; padding: 6px 10px; text-decoration: none; border-radius: 4px; margin-right: 3px; font-size: 12px;'>Editar</a>";
                if ($product->rowid != $base_product->rowid) {
                    echo "<a href='#' onclick='confirmDelete(" . $product->rowid . ")' style='background: #dc3545; color: white; padding: 6px 10px; text-decoration: none; border-radius: 4px; font-size: 12px;'>Eliminar</a>";
                }
                echo "</td>";
                echo "</tr>";
                
                $total_stock += $product->stock;
                if ($product->status == 1) $active_count++; else $inactive_count++;
            }
            
            echo "</table>";
            
            echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 6px; margin: 15px 0;'>";
            echo "<h5 style='margin: 0 0 10px 0;'>📊 RESUMEN DEL GRUPO:</h5>";
            echo "<p><strong>Total de productos:</strong> " . count($group) . " | ";
            echo "<strong>Activos:</strong> " . $active_count . " | ";
            echo "<strong>Inactivos:</strong> " . $inactive_count . " | ";
            echo "<strong>Stock total:</strong> " . $total_stock . "</p>";
            echo "<p><strong>Recomendación:</strong> Mantener el producto más reciente y activo, sumar el stock de los duplicados antes de eliminarlos.</p>";
            echo "</div>";
            
            echo "</div>";
        }
        
    } else {
        echo "<div style='background: #d1ecf1; border: 1px solid #bee5eb; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
        echo "<h4 style='color: #0c5460; margin-top: 0;'>✅ NO SE ENCONTRARON DUPLICADOS</h4>";
        echo "<p>El análisis inteligente no detectó productos duplicados en tu base de datos.</p>";
        echo "</div>";
    }
    
} else {
    echo "<p>❌ Error en la consulta: " . $db->lasterror() . "</p>";
}

// JavaScript para confirmar eliminación
echo "<script>";
echo "function confirmDelete(productId) {";
echo "    if (confirm('¿Estás seguro de que quieres eliminar este producto duplicado?\\n\\nIMPORTANTE: Asegúrate de haber sumado el stock al producto que mantienes.')) {";
echo "        window.open('" . DOL_URL_ROOT . "/product/card.php?id=' + productId + '&action=delete', '_blank');";
echo "    }";
echo "}";
echo "</script>";

echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<h4 style='color: #856404; margin-top: 0;'>🧠 ANÁLISIS INTELIGENTE:</h4>";
echo "<ul>";
echo "<li><strong>Nombre:</strong> 40% de peso en la similitud</li>";
echo "<li><strong>Descripción:</strong> 30% de peso en la similitud</li>";
echo "<li><strong>Referencia:</strong> 15% de peso en la similitud</li>";
echo "<li><strong>Código de barras:</strong> 10% de peso en la similitud</li>";
echo "<li><strong>Referencia proveedor:</strong> 5% de peso en la similitud</li>";
echo "</ul>";
echo "<p><strong>Umbral de similitud:</strong> 70% o más para considerar duplicados</p>";
echo "</div>";

echo "<p><a href='index.php' style='background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;'>← Volver al módulo de búsqueda</a></p>";
?>


