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

echo "<h2>🔍 BÚSQUEDA ESPECÍFICA: SAVAREZ 520R</h2>";

// Buscar productos que contengan 520R
$sql = "SELECT rowid, ref, label, description, barcode, tosell, tobuy, hidden, finished, datec, stock
        FROM " . MAIN_DB_PREFIX . "product 
        WHERE entity = " . $conf->entity . " 
        AND (label LIKE '%520R%' OR ref LIKE '%520R%' OR description LIKE '%520R%')
        ORDER BY rowid";

$resql = $db->query($sql);
if ($resql) {
    $products = array();
    while ($obj = $db->fetch_object($resql)) {
        $products[] = $obj;
    }
    
    echo "<h3>📊 PRODUCTOS CON 520R ENCONTRADOS: " . count($products) . "</h3>";
    
    if (count($products) > 0) {
        // Agrupar por similitud de nombre (no exacto)
        $groups = array();
        $processed = array();
        
        foreach ($products as $product) {
            if (in_array($product->rowid, $processed)) continue;
            
            $group_key = $product->rowid;
            $groups[$group_key] = array($product);
            $processed[] = $product->rowid;
            
            // Buscar productos similares
            foreach ($products as $other_product) {
                if ($other_product->rowid == $product->rowid || in_array($other_product->rowid, $processed)) continue;
                
                // Calcular similitud entre nombres usando el mejor algoritmo
                $similarity = calculateNameSimilarity($product->label, $other_product->label);
                
                if ($similarity >= 75) { // 75% de similitud (óptimo según el análisis)
                    $groups[$group_key][] = $other_product;
                    $processed[] = $other_product->rowid;
                }
            }
        }
        
        // Mostrar grupos de duplicados
        foreach ($groups as $group_key => $group_products) {
            if (count($group_products) > 1) {
                $group_name = $group_products[0]->label; // Usar el nombre del primer producto
                echo "<div style='border: 3px solid #dc3545; margin: 20px 0; padding: 20px; background: #f8d7da; border-radius: 8px;'>";
                echo "<h4 style='color: #721c24; margin-top: 0;'>🚨 DUPLICADOS DETECTADOS: " . htmlspecialchars($group_name) . "</h4>";
                echo "<p><strong>Cantidad de duplicados:</strong> " . count($group_products) . "</p>";
                
                $total_stock = 0;
                $active_count = 0;
                $inactive_count = 0;
                
                echo "<table style='width: 100%; border-collapse: collapse; margin: 15px 0;'>";
                echo "<tr style='background: #e9ecef; font-weight: bold;'>";
                echo "<td style='padding: 12px; border: 1px solid #dee2e6; text-align: center;'>ID</td>";
                echo "<td style='padding: 12px; border: 1px solid #dee2e6; text-align: center;'>REFERENCIA</td>";
                echo "<td style='padding: 12px; border: 1px solid #dee2e6; text-align: center;'>NOMBRE</td>";
                echo "<td style='padding: 12px; border: 1px solid #dee2e6; text-align: center;'>DESCRIPCIÓN</td>";
                echo "<td style='padding: 12px; border: 1px solid #dee2e6; text-align: center;'>ESTADO</td>";
                echo "<td style='padding: 12px; border: 1px solid #dee2e6; text-align: center;'>FECHA</td>";
                echo "<td style='padding: 12px; border: 1px solid #dee2e6; text-align: center;'>STOCK</td>";
                echo "<td style='padding: 12px; border: 1px solid #dee2e6; text-align: center;'>COINCIDENCIA</td>";
                echo "<td style='padding: 12px; border: 1px solid #dee2e6; text-align: center;'>ACCIONES</td>";
                echo "</tr>";
                
                foreach ($group_products as $index => $product) {
                    // Determinar estado del producto según lógica de Dolibarr
                    $is_active = (($product->tosell == 1 || $product->tobuy == 1) && $product->hidden == 0);
                    $status_text = $is_active ? 'ACTIVO' : 'INACTIVO';
                    $status_icon = $is_active ? '✅ ACTIVO' : '❌ INACTIVO';
                    
                    $coincidence = count($group_products) > 1 ? '🚨 DUPLICADO' : '⚡ ÚNICO';
                    $row_color = count($group_products) > 1 ? '#ffebee' : '#e8f5e8';
                    
                    echo "<tr style='background: " . $row_color . ";'>";
                    echo "<td style='padding: 12px; border: 1px solid #dee2e6; text-align: center; font-weight: bold;'>" . $product->rowid . "</td>";
                    echo "<td style='padding: 12px; border: 1px solid #dee2e6; text-align: center; font-weight: bold; color: #007bff;'>" . htmlspecialchars($product->ref) . "</td>";
                    echo "<td style='padding: 12px; border: 1px solid #dee2e6;'>" . htmlspecialchars($product->label) . "</td>";
                    echo "<td style='padding: 12px; border: 1px solid #dee2e6; font-size: 12px; max-width: 200px;'>" . htmlspecialchars(substr($product->description, 0, 100)) . "...</td>";
                    echo "<td style='padding: 12px; border: 1px solid #dee2e6; text-align: center; font-size: 11px;'>" . $status_icon . "</td>";
                    echo "<td style='padding: 12px; border: 1px solid #dee2e6; text-align: center;'>" . date('d/m/Y H:i', strtotime($product->datec)) . "</td>";
                    echo "<td style='padding: 12px; border: 1px solid #dee2e6; text-align: center; font-weight: bold; color: #28a745;'>" . $product->stock . "</td>";
                    echo "<td style='padding: 12px; border: 1px solid #dee2e6; text-align: center; font-weight: bold; color: " . (count($group_products) > 1 ? '#dc3545' : '#28a745') . ";'>" . $coincidence . "</td>";
                    echo "<td style='padding: 12px; border: 1px solid #dee2e6; text-align: center;'>";
                    echo "<a href='" . DOL_URL_ROOT . "/product/card.php?id=" . $product->rowid . "' target='_blank' style='background: #007bff; color: white; padding: 6px 10px; text-decoration: none; border-radius: 4px; margin-right: 3px; font-size: 12px;'>Ver</a>";
                    echo "<a href='" . DOL_URL_ROOT . "/product/card.php?id=" . $product->rowid . "&action=edit' target='_blank' style='background: #28a745; color: white; padding: 6px 10px; text-decoration: none; border-radius: 4px; margin-right: 3px; font-size: 12px;'>Editar</a>";
                    if (count($group_products) > 1 && $index > 0) {
                        echo "<a href='#' onclick='confirmDelete(" . $product->rowid . ")' style='background: #dc3545; color: white; padding: 6px 10px; text-decoration: none; border-radius: 4px; font-size: 12px;'>Eliminar</a>";
                    }
                    echo "</td>";
                    echo "</tr>";
                    
                    $total_stock += $product->stock;
                    if ($is_active) $active_count++; else $inactive_count++;
                }
                
                echo "</table>";
                
                echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 6px; margin: 15px 0;'>";
                echo "<h5 style='margin: 0 0 10px 0;'>📊 RESUMEN DEL GRUPO:</h5>";
                echo "<p><strong>Total de productos:</strong> " . count($group_products) . " | ";
                echo "<strong>Activos:</strong> " . $active_count . " | ";
                echo "<strong>Inactivos:</strong> " . $inactive_count . " | ";
                echo "<strong>Stock total:</strong> " . $total_stock . "</p>";
                echo "<p><strong>Recomendación:</strong> Mantener el producto más reciente y activo, sumar el stock de los duplicados antes de eliminarlos.</p>";
                echo "</div>";
                
                echo "</div>";
            } else {
                // Productos únicos
                $group_name = $group_products[0]->label; // Usar el nombre del primer producto
                echo "<div style='border: 2px solid #28a745; margin: 20px 0; padding: 20px; background: #d4edda; border-radius: 8px;'>";
                echo "<h4 style='color: #155724; margin-top: 0;'>✅ PRODUCTO ÚNICO: " . htmlspecialchars($group_name) . "</h4>";
                
                $product = $group_products[0];
                
                // Determinar estado del producto según lógica de Dolibarr
                $is_active = (($product->tosell == 1 || $product->tobuy == 1) && $product->hidden == 0);
                $status_text = $is_active ? 'ACTIVO' : 'INACTIVO';
                $status_icon = $is_active ? '✅ ACTIVO' : '❌ INACTIVO';
                
                echo "<table style='width: 100%; border-collapse: collapse; margin: 15px 0;'>";
                echo "<tr style='background: #f8f9fa;'>";
                echo "<td style='padding: 12px; border: 1px solid #dee2e6; font-weight: bold; width: 150px;'>ID:</td>";
                echo "<td style='padding: 12px; border: 1px solid #dee2e6; font-weight: bold; color: #007bff;'>" . $product->rowid . "</td>";
                echo "<td style='padding: 12px; border: 1px solid #dee2e6; font-weight: bold; width: 150px;'>Referencia:</td>";
                echo "<td style='padding: 12px; border: 1px solid #dee2e6; font-weight: bold;'>" . htmlspecialchars($product->ref) . "</td>";
                echo "</tr>";
                echo "<tr>";
                echo "<td style='padding: 12px; border: 1px solid #dee2e6; font-weight: bold;'>Estado:</td>";
                echo "<td style='padding: 12px; border: 1px solid #dee2e6;'>" . $status_icon . "</td>";
                echo "<td style='padding: 12px; border: 1px solid #dee2e6; font-weight: bold;'>Stock:</td>";
                echo "<td style='padding: 12px; border: 1px solid #dee2e6; font-weight: bold; color: #28a745;'>" . $product->stock . "</td>";
                echo "</tr>";
                echo "</table>";
                echo "</div>";
            }
        }
        
    } else {
        echo "<p>❌ No se encontraron productos con 520R</p>";
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

echo "<div style='background: #d1ecf1; border: 1px solid #bee5eb; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<h4 style='color: #0c5460; margin-top: 0;'>💡 INSTRUCCIONES:</h4>";
echo "<ol>";
echo "<li><strong>Revisa los productos duplicados</strong> marcados en rojo</li>";
echo "<li><strong>Decide cuál mantener</strong> (recomendado: el más reciente y activo)</li>";
echo "<li><strong>Suma el stock</strong> antes de eliminar el duplicado</li>";
echo "<li><strong>Elimina el duplicado</strong> usando el botón rojo</li>";
echo "<li><strong>Actualiza la referencia</strong> si es necesario</li>";
echo "</ol>";
echo "</div>";

echo "<p><a href='index.php' style='background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;'>← Volver al módulo de búsqueda</a></p>";

// Función para calcular similitud entre nombres usando el mejor algoritmo
function calculateNameSimilarity($name1, $name2) {
    $name1 = strtolower(trim($name1));
    $name2 = strtolower(trim($name2));
    
    // Si son exactamente iguales
    if ($name1 === $name2) {
        return 100;
    }
    
    // 1. Similar Text (mejor para diferencias pequeñas como "JUEGIO" vs "JUEGO")
    similar_text($name1, $name2, $similar_text_percent);
    
    // 2. Levenshtein Distance (excelente para errores tipográficos)
    $levenshtein = levenshtein($name1, $name2);
    $max_length = max(strlen($name1), strlen($name2));
    $levenshtein_percent = $max_length > 0 ? (1 - ($levenshtein / $max_length)) * 100 : 0;
    
    // 3. N-grams (bueno para coincidencias parciales)
    $ngrams_similarity = calculateNgramsSimilarity($name1, $name2);
    
    // 4. Jaccard (bueno para comparación por palabras)
    $jaccard_similarity = calculateJaccardSimilarity($name1, $name2);
    
    // Usar el mejor resultado (máximo de todos los algoritmos)
    $best_similarity = max($similar_text_percent, $levenshtein_percent, $ngrams_similarity, $jaccard_similarity);
    
    return $best_similarity;
}

// Función para calcular similitud N-grams
function calculateNgramsSimilarity($text1, $text2, $n = 3) {
    $ngrams1 = generateNgrams($text1, $n);
    $ngrams2 = generateNgrams($text2, $n);
    
    $intersection = array_intersect($ngrams1, $ngrams2);
    $union = array_unique(array_merge($ngrams1, $ngrams2));
    
    return count($union) > 0 ? (count($intersection) / count($union)) * 100 : 0;
}

// Función para generar N-grams
function generateNgrams($text, $n) {
    $ngrams = array();
    $text = preg_replace('/[^a-z0-9\s]/', '', strtolower($text));
    $words = explode(' ', $text);
    
    foreach ($words as $word) {
        if (strlen($word) >= $n) {
            for ($i = 0; $i <= strlen($word) - $n; $i++) {
                $ngrams[] = substr($word, $i, $n);
            }
        }
    }
    
    return $ngrams;
}

// Función para calcular similitud Jaccard
function calculateJaccardSimilarity($text1, $text2) {
    $tokens1 = tokenize($text1);
    $tokens2 = tokenize($text2);
    
    $intersection = array_intersect($tokens1, $tokens2);
    $union = array_unique(array_merge($tokens1, $tokens2));
    
    return count($union) > 0 ? (count($intersection) / count($union)) * 100 : 0;
}

// Función para tokenizar texto
function tokenize($text) {
    $text = preg_replace('/[^a-z0-9\s]/', '', strtolower($text));
    $words = explode(' ', $text);
    return array_filter($words, function($word) {
        return strlen($word) > 2; // Filtrar palabras cortas
    });
}
?>
