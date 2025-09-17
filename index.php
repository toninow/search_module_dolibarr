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
        $res = @include "../../../../main.inc.php";
    }
    if (!$res && file_exists("../../../../main.inc.php")) {
        $res = @include "../../../../main.inc.php";
    }
}

// Load module libraries
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

// Process actions
$action = GETPOST('action', 'aZ09');
$clear = GETPOST('clear', 'int');

// Si se hace clic en LIMPIAR, limpiar todas las variables de búsqueda
if ($clear == 1) {
    $action = '';
    $product_id = '';
    $product_ref = '';
    $product_name = '';
    $product_description = '';
    $product_ean = '';
    $tab = 'duplicates'; // Volver a la pestaña de duplicados por defecto
} else {
    $product_id = GETPOST('product_id', 'int');
    $product_ref = GETPOST('product_ref', 'alphanohtml');
    $product_name = GETPOST('product_name', 'alphanohtml');
    $product_description = GETPOST('product_description', 'alphanohtml');
    $product_ean = GETPOST('product_ean', 'alphanohtml');
    $tab = GETPOST('tab', 'aZ09');
}
if (empty($tab)) $tab = 'unique';

// Obtener página actual
$current_page = GETPOST('page', 'int');
if (empty($current_page)) $current_page = 1;

$duplicates = array();
$unique_products = array();

// ===== FUNCIONES AUXILIARES =====

class SimpleDuplicateDetector {
    private $products;

    public function __construct($products) {
        $this->products = $products;
    }

    public function findDuplicates() {
        $duplicates = [];
        $unique = [];
        $used = [];

        for ($i = 0; $i < count($this->products); $i++) {
            if (in_array($i, $used)) continue;

            $group = [$this->products[$i]];
            $used[] = $i;

            for ($j = $i + 1; $j < count($this->products); $j++) {
                if (in_array($j, $used)) continue;

                if ($this->areDuplicates($this->products[$i], $this->products[$j])) {
                    $group[] = $this->products[$j];
                    $used[] = $j;
                }
            }

            if (count($group) > 1) {
                $duplicates[] = $group;
            } else {
                $unique[] = $group[0];
            }
        }

        return ['duplicates' => $duplicates, 'unique' => $unique];
    }

    private function areDuplicates($product1, $product2) {
        $name1 = strtolower($product1->label);
        $name2 = strtolower($product2->label);
        
        // Si son exactamente iguales
        if ($name1 === $name2) {
            return true;
        }
        
        // Extraer códigos de modelo de ambos nombres
        $codes1 = $this->extractModelCodes($name1);
        $codes2 = $this->extractModelCodes($name2);
        
        // Si ambos tienen códigos y son diferentes, NO son duplicados
        if (!empty($codes1) && !empty($codes2)) {
            $intersection = array_intersect($codes1, $codes2);
            if (empty($intersection)) {
                return false; // Códigos diferentes = productos diferentes
            }
        }
        
        // Si uno tiene código y el otro no, verificar si el código está en el nombre
        if (!empty($codes1) && empty($codes2)) {
            foreach ($codes1 as $code) {
                if (strpos($name2, $code) !== false) {
                    return true; // El código del primero está en el segundo
                }
            }
        }
        
        if (!empty($codes2) && empty($codes1)) {
            foreach ($codes2 as $code) {
                if (strpos($name1, $code) !== false) {
                    return true; // El código del segundo está en el primero
                }
            }
        }
        
        // Calcular similitud textual
        similar_text($name1, $name2, $percent);
        
        // Si similitud > 90%, son duplicados
        return $percent >= 90;
    }
    
    private function extractModelCodes($text) {
        // Buscar códigos como 520R, 520-R, 520 R, etc.
        preg_match_all('/\b[a-z]*\d+[a-z]*\b/', $text, $matches);
        $codes = [];
        
        foreach ($matches[0] as $code) {
            // Normalizar: quitar guiones y espacios
            $normalized = preg_replace('/[^a-z0-9]/', '', $code);
            if (strlen($normalized) >= 2) {
                $codes[] = $normalized;
            }
        }
        
        return array_unique($codes);
    }
}

// ===== ALGORITMO PRINCIPAL =====

function separateProducts($all_products) {
    $detector = new SimpleDuplicateDetector($all_products);
    return $detector->findDuplicates();
}

// Función para cargar todos los productos
function loadAllProducts() {
    global $db, $conf;
    $sql = "SELECT p.rowid, p.ref, p.label, p.description, p.barcode, p.tosell, p.tobuy, p.hidden, p.finished, p.datec, p.stock
            FROM " . MAIN_DB_PREFIX . "product p
            WHERE p.entity = " . $conf->entity . "
            ORDER BY p.datec DESC, p.rowid DESC
            LIMIT 1000";
    
    $resql = $db->query($sql);
    if ($resql) {
        $products = array();
        while ($obj = $db->fetch_object($resql)) {
            $products[] = $obj;
        }
        return $products;
    }
    return array();
}

// ===== LÓGICA PRINCIPAL =====

// Cargar duplicados por defecto
if (empty($action)) {
    $all_products = loadAllProducts();
    if (count($all_products) > 0) {
        $searched = separateProducts($all_products);
        $duplicates = $searched['duplicates'];
        $unique_products = $searched['unique'];
    }
}

// Procesar búsqueda - FILTRAR DUPLICADOS EXISTENTES (RÁPIDO)
elseif ($action == 'search') {
    // Cargar duplicados existentes (como cuando no buscas)
    $all_products = loadAllProducts();
    if (count($all_products) > 0) {
        $searched = separateProducts($all_products);
        $all_duplicates = $searched['duplicates'];
        $all_unique_products = $searched['unique'];
        
        // Filtrar duplicados que contengan productos que coincidan con la búsqueda
        $duplicates = array();
        $unique_products = array();
        
        // Filtrar grupos de duplicados
        foreach ($all_duplicates as $group) {
            $group_matches = false;
            foreach ($group as $product) {
                $matches = true;
                
                // Verificar criterios de búsqueda
                if (!empty($product_id) && $product->rowid != $product_id) {
                    $matches = false;
                }
                if (!empty($product_ref) && stripos($product->ref, $product_ref) === false) {
                    $matches = false;
                }
                if (!empty($product_name) && stripos($product->label, $product_name) === false) {
                    $matches = false;
                }
                if (!empty($product_description) && stripos($product->description, $product_description) === false) {
                    $matches = false;
                }
                if (!empty($product_ean) && stripos($product->barcode, $product_ean) === false) {
                    $matches = false;
                }
                
                if ($matches) {
                    $group_matches = true;
                    break;
                }
            }
            
            if ($group_matches) {
                $duplicates[] = $group;
            }
        }
        
        // Filtrar productos únicos
        foreach ($all_unique_products as $product) {
            $matches = true;
            
            // Verificar criterios de búsqueda
            if (!empty($product_id) && $product->rowid != $product_id) {
                $matches = false;
            }
            if (!empty($product_ref) && stripos($product->ref, $product_ref) === false) {
                $matches = false;
            }
            if (!empty($product_name) && stripos($product->label, $product_name) === false) {
                $matches = false;
            }
            if (!empty($product_description) && stripos($product->description, $product_description) === false) {
                $matches = false;
            }
            if (!empty($product_ean) && stripos($product->barcode, $product_ean) === false) {
                $matches = false;
            }
            
            if ($matches) {
                $unique_products[] = $product;
            }
        }
        
        setEventMessages("Filtro aplicado. " . count($duplicates) . " grupos de duplicados y " . count($unique_products) . " productos únicos que coinciden con la búsqueda.", null, 'mesgs');
    } else {
        $duplicates = array();
        $unique_products = array();
        setEventMessages("No hay productos en la base de datos.", null, 'warnings');
    }
}

// Page title
$title = "🔍 Búsqueda de Productos Duplicados";
$help_url = '';

// Header
llxHeader('', $title, $help_url);

// Subheader
$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php?restore_lastsearch_values=1">← Volver al listado de módulos</a>';
print load_fiche_titre($title, $linkback, 'title_setup');

// Formulario de búsqueda simplificado
print '<div style="padding: 20px 0; margin: 20px 0;">';

print '<form method="POST" action="' . $_SERVER["PHP_SELF"] . '">';
print '<input type="hidden" name="action" value="search">';
print '<input type="hidden" name="tab" value="' . $tab . '">';
print '<table class="noborder centpercent" style="margin-bottom: 20px;">';
print '<tr class="liste_titre">';
print '<td class="center" style="padding: 8px; width: 8%;">ID</td>';
print '<td class="center" style="padding: 8px; width: 12%;">EAN</td>';
print '<td class="center" style="padding: 8px; width: 20%;">Referencia</td>';
print '<td class="center" style="padding: 8px; width: 40%;">Nombre</td>';
print '<td class="center" style="padding: 8px; width: 20%;">Acciones</td>';
print '</tr>';
print '<tr class="oddeven">';
print '<td style="padding: 8px;"><input type="number" id="product_id" name="product_id" value="' . htmlspecialchars($product_id) . '" placeholder="1234" class="flat" style="width: 100%; box-sizing: border-box; padding: 8px; border: 1px solid #ddd; border-radius: 4px; transition: all 0.3s; background: #f8f9fa;" onfocus="this.style.borderColor=\'#007bff\'; this.style.backgroundColor=\'#ffffff\'; this.style.boxShadow=\'0 0 0 2px rgba(0,123,255,0.25)\';" onblur="this.style.borderColor=\'#ddd\'; this.style.backgroundColor=\'#f8f9fa\'; this.style.boxShadow=\'none\';"></td>';
print '<td style="padding: 8px;"><input type="text" id="product_ean" name="product_ean" value="' . htmlspecialchars($product_ean) . '" placeholder="1234567890123" class="flat" style="width: 100%; box-sizing: border-box; padding: 8px; border: 1px solid #ddd; border-radius: 4px; transition: all 0.3s; background: #f8f9fa;" onfocus="this.style.borderColor=\'#007bff\'; this.style.backgroundColor=\'#ffffff\'; this.style.boxShadow=\'0 0 0 2px rgba(0,123,255,0.25)\';" onblur="this.style.borderColor=\'#ddd\'; this.style.backgroundColor=\'#f8f9fa\'; this.style.boxShadow=\'none\';"></td>';
print '<td style="padding: 8px;"><input type="text" id="product_ref" name="product_ref" value="' . htmlspecialchars($product_ref) . '" placeholder="PROD-001" class="flat" style="width: 100%; box-sizing: border-box; padding: 8px; border: 1px solid #ddd; border-radius: 4px; transition: all 0.3s; background: #f8f9fa;" onfocus="this.style.borderColor=\'#007bff\'; this.style.backgroundColor=\'#ffffff\'; this.style.boxShadow=\'0 0 0 2px rgba(0,123,255,0.25)\';" onblur="this.style.borderColor=\'#ddd\'; this.style.backgroundColor=\'#f8f9fa\'; this.style.boxShadow=\'none\';"></td>';
print '<td style="padding: 8px;"><input type="text" id="product_name" name="product_name" value="' . htmlspecialchars($product_name) . '" placeholder="Nombre del producto" class="flat" style="width: 100%; box-sizing: border-box; padding: 8px; border: 1px solid #ddd; border-radius: 4px; transition: all 0.3s; background: #f8f9fa;" onfocus="this.style.borderColor=\'#007bff\'; this.style.backgroundColor=\'#ffffff\'; this.style.boxShadow=\'0 0 0 2px rgba(0,123,255,0.25)\';" onblur="this.style.borderColor=\'#ddd\'; this.style.backgroundColor=\'#f8f9fa\'; this.style.boxShadow=\'none\';"></td>';
print '<td style="padding: 8px; text-align: center; vertical-align: middle;">';
print '<button type="submit" style="background: #007bff; color: white; border: 1px solid #007bff; padding: 8px 12px; margin-right: 6px; border-radius: 4px; font-size: 12px; cursor: pointer; height: 100%; width: 80px; box-sizing: border-box;">🔍 BUSCAR</button>';
print '<a href="' . $_SERVER["PHP_SELF"] . '" style="background: #6c757d; color: white; border: 1px solid #6c757d; padding: 8px 12px; border-radius: 4px; font-size: 12px; text-decoration: none; display: inline-block; height: 100%; width: 80px; line-height: 16px; box-sizing: border-box; vertical-align: top;">🧹 LIMPIAR</a>';
print '</td>';
print '</tr>';
print '</table>';
print '</form>';
print '</div>';

// Pestañas
print '<div style="margin: 20px 0;">';
print '<button id="tab-unique-btn" onclick="showTab(\'unique\')" style="background: #f8f9fa; color: #495057; border: 1px solid #dee2e6; padding: 10px 20px; margin-right: 5px; cursor: pointer; border-radius: 4px 4px 0 0;">Pestaña 1: PRODUCTOS ÚNICOS (' . count($unique_products) . ')</button>';
print '<button id="tab-duplicates-btn" onclick="showTab(\'duplicates\')" style="background: #f8f9fa; color: #495057; border: 1px solid #dee2e6; padding: 10px 20px; cursor: pointer; border-radius: 4px 4px 0 0;">Pestaña 2: DUPLICADOS (' . count($duplicates) . ')</button>';
print '</div>';

// Contenido de pestañas
print '<div id="tab-unique" style="display: ' . ($tab == 'unique' ? 'block' : 'none') . ';">';
if (count($unique_products) > 0) {
    print '<div style="background: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; margin: 10px 0; border-radius: 4px;">';
    print '<h4 style="margin: 0 0 10px 0; color: #0c5460;">📦 PRODUCTOS ÚNICOS</h4>';
    print '<p style="margin: 0; color: #0c5460;">Estos productos no tienen duplicados similares en la base de datos.</p>';
    print '</div>';
    
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    print '<td class="center" style="padding: 8px;">ID</td>';
    print '<td class="center" style="padding: 8px;">Referencia</td>';
    print '<td class="center" style="padding: 8px;">Nombre</td>';
    print '<td class="center" style="padding: 8px;">Descripción</td>';
    print '<td class="center" style="padding: 8px;">Estado</td>';
    print '<td class="center" style="padding: 8px;">Fecha</td>';
    print '<td class="center" style="padding: 8px;">Stock</td>';
    print '<td class="center" style="padding: 8px;">Acciones</td>';
    print '</tr>';
    
    foreach ($unique_products as $product) {
        $status = ($product->tosell == 1 && $product->tobuy == 1 && $product->hidden == 0) ? 'ACTIVO' : 'INACTIVO';
        $status_color = ($status == 'ACTIVO') ? '#28a745' : '#dc3545';
        
        print '<tr class="oddeven">';
        print '<td class="center" style="padding: 8px;">' . $product->rowid . '</td>';
        print '<td class="center" style="padding: 8px;"><a href="' . DOL_URL_ROOT . '/product/card.php?id=' . $product->rowid . '">' . $product->ref . '</a></td>';
        print '<td class="center" style="padding: 8px;">' . $product->label . '</td>';
        print '<td class="center" style="padding: 8px;">' . substr(strip_tags($product->description), 0, 100) . '...</td>';
        print '<td class="center" style="padding: 8px; color: ' . $status_color . '; font-weight: bold;">' . $status . '</td>';
        print '<td class="center" style="padding: 8px;">' . dol_print_date($product->datec, 'dayhour') . '</td>';
        print '<td class="center" style="padding: 8px;">' . $product->stock . '</td>';
        print '<td class="center" style="padding: 8px;">';
        print '<a href="' . DOL_URL_ROOT . '/product/card.php?id=' . $product->rowid . '" class="butAction">VER</a> ';
        print '<a href="' . DOL_URL_ROOT . '/product/card.php?id=' . $product->rowid . '&action=edit" class="butAction">EDITAR</a>';
        print '</td>';
        print '</tr>';
    }
    print '</table>';
} else {
    print '<div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; margin: 10px 0; border-radius: 4px;">';
    print '<p style="margin: 0; color: #721c24;">No se encontraron productos únicos.</p>';
    print '</div>';
}
print '</div>';

print '<div id="tab-duplicates" style="display: ' . ($tab == 'duplicates' ? 'block' : 'none') . ';">';
if (count($duplicates) > 0) {
    print '<div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; margin: 10px 0; border-radius: 4px;">';
    print '<h4 style="margin: 0 0 10px 0; color: #721c24;">⚠️ DUPLICADOS ENCONTRADOS</h4>';
    print '<p style="margin: 0; color: #721c24;">Estos productos tienen duplicados similares en la base de datos.</p>';
    print '</div>';
    
    foreach ($duplicates as $index => $group) {
        print '<div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 10px 0; border-radius: 4px;">';
        print '<h4 style="margin: 0 0 10px 0; color: #856404;">🚩 GRUPO DE DUPLICADOS #' . ($index + 1) . '</h4>';
        print '<p style="margin: 0 0 15px 0; color: #856404;">Cantidad de duplicados: ' . count($group) . '</p>';
        
        print '<table class="noborder centpercent">';
        print '<tr class="liste_titre">';
        print '<td class="center" style="padding: 8px;">ID</td>';
        print '<td class="center" style="padding: 8px;">Referencia</td>';
        print '<td class="center" style="padding: 8px;">Nombre</td>';
        print '<td class="center" style="padding: 8px;">Descripción</td>';
        print '<td class="center" style="padding: 8px;">Estado</td>';
        print '<td class="center" style="padding: 8px;">Fecha</td>';
        print '<td class="center" style="padding: 8px;">Stock</td>';
        print '<td class="center" style="padding: 8px;">Similitud</td>';
        print '<td class="center" style="padding: 8px;">Acciones</td>';
        print '</tr>';
        
        foreach ($group as $product) {
            $status = ($product->tosell == 1 && $product->tobuy == 1 && $product->hidden == 0) ? 'ACTIVO' : 'INACTIVO';
            $status_color = ($status == 'ACTIVO') ? '#28a745' : '#dc3545';
            
            print '<tr class="oddeven">';
            print '<td class="center" style="padding: 8px;">' . $product->rowid . '</td>';
            print '<td class="center" style="padding: 8px;"><a href="' . DOL_URL_ROOT . '/product/card.php?id=' . $product->rowid . '">' . $product->ref . '</a></td>';
            print '<td class="center" style="padding: 8px;">' . $product->label . '</td>';
            print '<td class="center" style="padding: 8px;">' . substr(strip_tags($product->description), 0, 100) . '...</td>';
            print '<td class="center" style="padding: 8px; color: ' . $status_color . '; font-weight: bold;">' . $status . '</td>';
            print '<td class="center" style="padding: 8px;">' . dol_print_date($product->datec, 'dayhour') . '</td>';
            print '<td class="center" style="padding: 8px;">' . $product->stock . '</td>';
            print '<td class="center" style="padding: 8px; color: #28a745; font-weight: bold;">ORIGINAL</td>';
            print '<td class="center" style="padding: 8px;">';
            print '<a href="' . DOL_URL_ROOT . '/product/card.php?id=' . $product->rowid . '" class="butAction">VER</a> ';
            print '<a href="' . DOL_URL_ROOT . '/product/card.php?id=' . $product->rowid . '&action=edit" class="butAction">EDITAR</a>';
            print '</td>';
            print '</tr>';
        }
        print '</table>';
        print '</div>';
    }
} else {
    print '<div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; margin: 10px 0; border-radius: 4px;">';
    print '<p style="margin: 0; color: #155724;">No se encontraron duplicados.</p>';
    print '</div>';
}
print '</div>';

// JavaScript para pestañas
print '<script>
function showTab(tabName) {
    // Ocultar todas las pestañas
    document.getElementById("tab-unique").style.display = "none";
    document.getElementById("tab-duplicates").style.display = "none";
    
    // Desactivar todos los botones
    document.getElementById("tab-unique-btn").style.background = "#f8f9fa";
    document.getElementById("tab-unique-btn").style.color = "#495057";
    document.getElementById("tab-unique-btn").style.borderColor = "#dee2e6";
    document.getElementById("tab-duplicates-btn").style.background = "#f8f9fa";
    document.getElementById("tab-duplicates-btn").style.color = "#495057";
    document.getElementById("tab-duplicates-btn").style.borderColor = "#dee2e6";
    
    // Mostrar la pestaña seleccionada
    if (tabName === "unique") {
        document.getElementById("tab-unique").style.display = "block";
        document.getElementById("tab-unique-btn").style.background = "#007bff";
        document.getElementById("tab-unique-btn").style.color = "white";
        document.getElementById("tab-unique-btn").style.borderColor = "#007bff";
    } else {
        document.getElementById("tab-duplicates").style.display = "block";
        document.getElementById("tab-duplicates-btn").style.background = "#007bff";
        document.getElementById("tab-duplicates-btn").style.color = "white";
        document.getElementById("tab-duplicates-btn").style.borderColor = "#007bff";
    }
}
</script>';

// Footer
llxFooter();
?>
