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

echo "<h2>🔍 DETECCIÓN DE DUPLICADOS REALES</h2>";

// Buscar productos con nombres exactos duplicados
$sql = "SELECT label, COUNT(*) as count, GROUP_CONCAT(CONCAT('ID:', rowid, ' Ref:', ref) SEPARATOR ' | ') as products
        FROM " . MAIN_DB_PREFIX . "product 
        WHERE entity = " . $conf->entity . " 
        AND label != '' 
        GROUP BY label 
        HAVING COUNT(*) > 1 
        ORDER BY count DESC";

$resql = $db->query($sql);
if ($resql) {
    echo "<h3>📊 PRODUCTOS CON NOMBRES EXACTOS DUPLICADOS:</h3>";
    
    $total_duplicates = 0;
    while ($obj = $db->fetch_object($resql)) {
        echo "<div style='border: 2px solid #ff6b6b; margin: 10px 0; padding: 15px; background: #ffe0e0; border-radius: 8px;'>";
        echo "<h4 style='color: #d63031; margin: 0 0 10px 0;'>🚨 " . htmlspecialchars($obj->label) . "</h4>";
        echo "<p><strong>Cantidad de duplicados:</strong> " . $obj->count . "</p>";
        echo "<p><strong>Productos:</strong> " . htmlspecialchars($obj->products) . "</p>";
        echo "</div>";
        $total_duplicates += $obj->count - 1;
    }
    
    echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 20px 0;'>";
    echo "<h4>📈 RESUMEN:</h4>";
    echo "<p><strong>Total de productos duplicados encontrados:</strong> " . $total_duplicates . "</p>";
    echo "</div>";
} else {
    echo "<p>❌ Error en la consulta: " . $db->lasterror() . "</p>";
}

// Buscar productos con referencias similares
echo "<h3>🔍 BÚSQUEDA DE REFERENCIAS SIMILARES:</h3>";

$sql = "SELECT ref, label, rowid FROM " . MAIN_DB_PREFIX . "product 
        WHERE entity = " . $conf->entity . " 
        AND ref LIKE '%520R%' 
        ORDER BY ref";

$resql = $db->query($sql);
if ($resql) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #f8f9fa;'>";
    echo "<th style='padding: 10px;'>ID</th>";
    echo "<th style='padding: 10px;'>Referencia</th>";
    echo "<th style='padding: 10px;'>Nombre</th>";
    echo "<th style='padding: 10px;'>Acciones</th>";
    echo "</tr>";
    
    while ($obj = $db->fetch_object($resql)) {
        echo "<tr>";
        echo "<td style='padding: 10px;'>" . $obj->rowid . "</td>";
        echo "<td style='padding: 10px;'><strong>" . htmlspecialchars($obj->ref) . "</strong></td>";
        echo "<td style='padding: 10px;'>" . htmlspecialchars($obj->label) . "</td>";
        echo "<td style='padding: 10px;'>";
        echo "<a href='" . DOL_URL_ROOT . "/product/card.php?id=" . $obj->rowid . "' target='_blank' style='background: #007bff; color: white; padding: 5px 10px; text-decoration: none; border-radius: 4px;'>Ver</a> ";
        echo "<a href='" . DOL_URL_ROOT . "/product/card.php?id=" . $obj->rowid . "&action=edit' target='_blank' style='background: #28a745; color: white; padding: 5px 10px; text-decoration: none; border-radius: 4px;'>Editar</a>";
        echo "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p>❌ Error en la consulta: " . $db->lasterror() . "</p>";
}

echo "<h3>💡 RECOMENDACIONES:</h3>";
echo "<ul>";
echo "<li>🔧 <strong>Revisar las referencias:</strong> SAVAREZ-SET-520R vs EK-CSRJ</li>";
echo "<li>🔧 <strong>Unificar productos:</strong> Decidir cuál mantener y cuál eliminar</li>";
echo "<li>🔧 <strong>Actualizar stock:</strong> Sumar las cantidades antes de eliminar</li>";
echo "<li>🔧 <strong>Revisar historial:</strong> Ver qué transacciones tiene cada producto</li>";
echo "</ul>";

echo "<p><a href='index.php'>← Volver al módulo de búsqueda</a></p>";
?>


