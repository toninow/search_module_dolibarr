<?php
/* Copyright (C) 2024 - Búsqueda avanzada by Antonio
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
 * \file       class/search_simple.class.php
 * \ingroup    search_duplicates
 * \brief      Clase simple para búsqueda de productos usando solo tablas nativas de Dolibarr
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Class for SearchSimple (versión simplificada)
 */
class SearchSimple extends CommonObject
{
    /**
     * @var string ID to identify managed object
     */
    public $element = 'search_duplicates';

    /**
     * @var string Name of table without prefix where object is stored
     */
    public $table_element = 'product';

    /**
     * @var int  Does this object support multicompany module ?
     */
    public $ismultientitymanaged = 1;

    /**
     * @var int  Does object support extrafields ?
     */
    public $isextrafieldmanaged = 0;

    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Búsqueda simple de productos
     *
     * @param string $searchTerm Término de búsqueda
     * @param int $limit Límite de resultados
     * @return array Array de productos
     */
    public function searchProducts($searchTerm, $limit = 50, $searchType = 'all')
    {
        $searchTerm = trim($searchTerm);
        if (empty($searchTerm)) {
            return [];
        }

        // Consulta SQL mejorada - incluye búsqueda por ID y referencia
        $escapedTerm = $this->db->escape($searchTerm);
        
        // Verificar si el término de búsqueda es un número (ID)
        $isNumeric = is_numeric($searchTerm);
        
        if ($isNumeric) {
            // Búsqueda por ID exacto o por referencia/descripción
            $sql = "SELECT 
                        p.rowid as id_product,
                        p.label as product_name,
                        p.ref as reference,
                        p.price,
                        p.tosell as active,
                        p.description,
                        p.datec as date_add,
                        p.tms as date_upd
                    FROM " . MAIN_DB_PREFIX . "product p
                    WHERE (p.rowid = " . (int)$searchTerm . "
                           OR p.ref LIKE '%" . $escapedTerm . "%' 
                           OR p.label LIKE '%" . $escapedTerm . "%'
                           OR p.description LIKE '%" . $escapedTerm . "%')
                    ORDER BY p.rowid ASC
                    LIMIT " . (int)$limit;
        } else {
            // Búsqueda por texto en referencia, nombre y descripción
            $sql = "SELECT 
                        p.rowid as id_product,
                        p.label as product_name,
                        p.ref as reference,
                        p.price,
                        p.tosell as active,
                        p.description,
                        p.datec as date_add,
                        p.tms as date_upd
                    FROM " . MAIN_DB_PREFIX . "product p
                    WHERE (p.ref LIKE '%" . $escapedTerm . "%' 
                           OR p.label LIKE '%" . $escapedTerm . "%'
                           OR p.description LIKE '%" . $escapedTerm . "%')
                    ORDER BY p.ref ASC
                    LIMIT " . (int)$limit;
        }

        $resql = $this->db->query($sql);
        if (!$resql) {
            return [];
        }

        $products = [];
        $num = $this->db->num_rows($resql);
        for ($i = 0; $i < $num; $i++) {
            $obj = $this->db->fetch_object($resql);
            $products[] = [
                'id_product' => $obj->id_product,
                'product_name' => $obj->product_name,
                'reference' => $obj->reference,
                'price' => $obj->price,
                'active' => $obj->active,
                'description' => $obj->description,
                'date_add' => $obj->date_add,
                'date_upd' => $obj->date_upd,
                'stock' => 0, // Temporalmente sin stock
                'is_duplicate' => false,
                'duplicate_of' => null
            ];
        }

        // Detectar duplicados simples
        $products = $this->detectSimpleDuplicates($products);

        return $products;
    }

    /**
     * Detectar duplicados basado en palabras y ordenar por original
     *
     * @param array $products Array de productos
     * @return array Array de productos con información de duplicados ordenados
     */
    private function detectSimpleDuplicates($products)
    {
        // Ordenar productos por fecha de creación (más antiguo primero)
        usort($products, function($a, $b) {
            return strtotime($a['date_add']) - strtotime($b['date_add']);
        });
        
        $duplicateGroups = [];
        $processed = [];
        
        for ($i = 0; $i < count($products); $i++) {
            if (in_array($i, $processed)) continue;
            
            $currentProduct = $products[$i];
            $duplicateGroup = [$i]; // El producto actual es el original del grupo
            $currentProduct['is_duplicate'] = false;
            $currentProduct['duplicate_of'] = null;
            $currentProduct['is_original'] = true;
            $currentProduct['duplicate_group'] = count($duplicateGroups);
            
            // Buscar duplicados del producto actual
            for ($j = $i + 1; $j < count($products); $j++) {
                if (in_array($j, $processed)) continue;
                
                $wordSimilarity = $this->calculateWordSimilarity($currentProduct, $products[$j]);
                
                if ($wordSimilarity > 0.95) { // 95% de palabras coincidentes (muy estricto)
                    $duplicateGroup[] = $j;
                    $products[$j]['is_duplicate'] = true;
                    $products[$j]['duplicate_of'] = $currentProduct['id_product'];
                    $products[$j]['is_original'] = false;
                    $products[$j]['duplicate_group'] = count($duplicateGroups);
                    $products[$j]['word_similarity'] = $wordSimilarity;
                    $products[$j]['duplicate_probability'] = round($wordSimilarity * 100);
                    $products[$j]['duplicate_level'] = $this->getDuplicateLevel($wordSimilarity);
                    $products[$j]['duplicate_reason'] = $this->getWordDuplicateReason($currentProduct, $products[$j]);
                    $processed[] = $j;
                }
            }
            
            // Marcar el producto original
            $currentProduct['word_similarity'] = 1.0;
            $currentProduct['duplicate_probability'] = 100;
            $currentProduct['duplicate_level'] = 'original';
            $currentProduct['duplicate_reason'] = 'Producto original';
            $products[$i] = $currentProduct;
            
            $duplicateGroups[] = $duplicateGroup;
            $processed[] = $i;
        }
        
        // Ordenar productos: originales primero, luego duplicados agrupados
        $sortedProducts = [];
        
        // Primero agregar todos los originales
        foreach ($products as $product) {
            if (!$product['is_duplicate']) {
                $sortedProducts[] = $product;
            }
        }
        
        // Luego agregar los duplicados agrupados por su original
        foreach ($duplicateGroups as $group) {
            if (count($group) > 1) { // Solo grupos con duplicados
                for ($i = 1; $i < count($group); $i++) { // Saltar el original (índice 0)
                    $sortedProducts[] = $products[$group[$i]];
                }
            }
        }
        
        return $sortedProducts;
    }

    /**
     * Calcular similitud entre dos strings
     *
     * @param string $str1 Primer string
     * @param string $str2 Segundo string
     * @return float Puntuación de similitud (0-1)
     */
    private function calculateSimilarity($str1, $str2)
    {
        $str1 = strtolower(trim($str1));
        $str2 = strtolower(trim($str2));
        
        if ($str1 === $str2) {
            return 1.0;
        }
        
        similar_text($str1, $str2, $percent);
        return $percent / 100;
    }

    /**
     * Calcular similitud basada en palabras coincidentes
     *
     * @param array $product1 Primer producto
     * @param array $product2 Segundo producto
     * @return float Puntuación de similitud por palabras (0-1)
     */
    private function calculateWordSimilarity($product1, $product2)
    {
        // Combinar campos para análisis
        $text1 = $this->combineProductFields($product1);
        $text2 = $this->combineProductFields($product2);
        
        // Extraer palabras únicas
        $words1 = $this->extractWords($text1);
        $words2 = $this->extractWords($text2);
        
        if (empty($words1) || empty($words2)) {
            return 0;
        }
        
        // Calcular intersección de palabras
        $commonWords = array_intersect($words1, $words2);
        $totalWords = array_unique(array_merge($words1, $words2));
        
        // Calcular similitud basada en palabras comunes
        $wordSimilarity = count($commonWords) / count($totalWords);
        
        // Bonificación por estado activo del original
        if ($product1['active'] && !$product2['active']) {
            $wordSimilarity += 0.1; // Bonificación del 10%
        }
        
        return min($wordSimilarity, 1.0);
    }

    /**
     * Combinar campos del producto para análisis
     *
     * @param array $product Producto
     * @return string Texto combinado
     */
    private function combineProductFields($product)
    {
        $fields = [];
        
        if (!empty($product['product_name'])) {
            $fields[] = $product['product_name'];
        }
        if (!empty($product['reference'])) {
            $fields[] = $product['reference'];
        }
        if (!empty($product['description'])) {
            $fields[] = $product['description'];
        }
        
        return implode(' ', $fields);
    }

    /**
     * Extraer palabras únicas de un texto
     *
     * @param string $text Texto a analizar
     * @return array Array de palabras únicas
     */
    private function extractWords($text)
    {
        // Limpiar y normalizar texto
        $text = strtolower($text);
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text); // Solo letras, números y espacios
        $text = preg_replace('/\s+/', ' ', $text); // Normalizar espacios
        $text = trim($text);
        
        // Extraer palabras (mínimo 2 caracteres)
        $words = explode(' ', $text);
        $words = array_filter($words, function($word) {
            return strlen($word) >= 2;
        });
        
        return array_unique($words);
    }

    /**
     * Calcular similitud combinada de múltiples campos
     *
     * @param array $product1 Primer producto
     * @param array $product2 Segundo producto
     * @return float Puntuación de similitud combinada (0-1)
     */
    private function calculateCombinedSimilarity($product1, $product2)
    {
        $nameSimilarity = $this->calculateSimilarity($product1['product_name'], $product2['product_name']);
        $refSimilarity = $this->calculateSimilarity($product1['reference'], $product2['reference']);
        
        // Calcular similitud de descripción si existe
        $descSimilarity = 0;
        if (!empty($product1['description']) && !empty($product2['description'])) {
            $descSimilarity = $this->calculateSimilarity($product1['description'], $product2['description']);
        }
        
        // Pesos: nombre 60%, referencia 30%, descripción 10%
        $combinedSimilarity = ($nameSimilarity * 0.6) + ($refSimilarity * 0.3) + ($descSimilarity * 0.1);
        
        return $combinedSimilarity;
    }

    /**
     * Obtener la razón del duplicado basada en palabras
     *
     * @param array $product1 Primer producto (original)
     * @param array $product2 Segundo producto (duplicado)
     * @return string Razón del duplicado
     */
    private function getWordDuplicateReason($product1, $product2)
    {
        $reasons = [];
        
        // Analizar palabras comunes
        $text1 = $this->combineProductFields($product1);
        $text2 = $this->combineProductFields($product2);
        
        $words1 = $this->extractWords($text1);
        $words2 = $this->extractWords($text2);
        $commonWords = array_intersect($words1, $words2);
        
        if (count($commonWords) > 0) {
            $reasons[] = count($commonWords) . ' palabras comunes: ' . implode(', ', array_slice($commonWords, 0, 5));
        }
        
        // Verificar estado activo
        if ($product1['active'] && !$product2['active']) {
            $reasons[] = 'Original activo, duplicado inactivo';
        } elseif (!$product1['active'] && $product2['active']) {
            $reasons[] = 'Original inactivo, duplicado activo';
        }
        
        // Verificar fecha de creación
        $date1 = strtotime($product1['date_add']);
        $date2 = strtotime($product2['date_add']);
        $daysDiff = abs($date1 - $date2) / (60 * 60 * 24);
        
        if ($daysDiff < 1) {
            $reasons[] = 'Creados el mismo día';
        } elseif ($daysDiff < 7) {
            $reasons[] = 'Creados en la misma semana';
        }
        
        return implode(' | ', $reasons);
    }

    /**
     * Obtener la razón del duplicado
     *
     * @param array $product1 Primer producto
     * @param array $product2 Segundo producto
     * @return string Razón del duplicado
     */
    private function getDuplicateReason($product1, $product2)
    {
        $reasons = [];
        
        $nameSimilarity = $this->calculateSimilarity($product1['product_name'], $product2['product_name']);
        $refSimilarity = $this->calculateSimilarity($product1['reference'], $product2['reference']);
        
        if ($nameSimilarity > 0.8) {
            $reasons[] = 'Nombre similar (' . round($nameSimilarity * 100) . '%)';
        }
        if ($refSimilarity > 0.8) {
            $reasons[] = 'Referencia similar (' . round($refSimilarity * 100) . '%)';
        }
        
        if (!empty($product1['description']) && !empty($product2['description'])) {
            $descSimilarity = $this->calculateSimilarity($product1['description'], $product2['description']);
            if ($descSimilarity > 0.7) {
                $reasons[] = 'Descripción similar (' . round($descSimilarity * 100) . '%)';
            }
        }
        
        return implode(', ', $reasons);
    }

    /**
     * Obtener el nivel de duplicado basado en la similitud
     *
     * @param float $similarity Puntuación de similitud (0-1)
     * @return string Nivel de duplicado
     */
    private function getDuplicateLevel($similarity)
    {
        if ($similarity >= 0.95) {
            return 'exact'; // Duplicado exacto
        } elseif ($similarity >= 0.85) {
            return 'high'; // Alta probabilidad
        } elseif ($similarity >= 0.75) {
            return 'medium'; // Probabilidad media
        } elseif ($similarity >= 0.6) {
            return 'low'; // Baja probabilidad
        } elseif ($similarity >= 0.5) {
            return 'possible'; // Posible duplicado
        } else {
            return 'none'; // No es duplicado
        }
    }

    /**
     * Construir la cláusula WHERE según el tipo de búsqueda
     *
     * @param string $searchTerm Término de búsqueda
     * @param string $searchType Tipo de búsqueda
     * @return string Cláusula WHERE
     */
    private function buildWhereClause($searchTerm, $searchType)
    {
        $escapedTerm = $this->db->escape($searchTerm);
        $conditions = [];

        switch ($searchType) {
            case 'name':
                // Solo buscar en el nombre del producto (tanto en p.label como en pl.label)
                $conditions[] = "(p.label LIKE '%" . $escapedTerm . "%' OR pl.label LIKE '%" . $escapedTerm . "%')";
                break;
                
            case 'ref':
                // Solo buscar en la referencia
                $conditions[] = "p.ref LIKE '%" . $escapedTerm . "%'";
                break;
                
            case 'ean':
                // Solo buscar en códigos EAN/ISBN
                $conditions[] = "(p.barcode LIKE '%" . $escapedTerm . "%' OR p.ean13 LIKE '%" . $escapedTerm . "%' OR p.isbn LIKE '%" . $escapedTerm . "%')";
                break;
                
            case 'barcode':
                // Solo buscar en códigos de barras
                $conditions[] = "p.barcode LIKE '%" . $escapedTerm . "%'";
                break;
                
            case 'all':
            default:
                // Buscar en todos los campos
                $conditions[] = "(p.ref LIKE '%" . $escapedTerm . "%' 
                                 OR p.label LIKE '%" . $escapedTerm . "%'
                                 OR pl.label LIKE '%" . $escapedTerm . "%'
                                 OR p.description LIKE '%" . $escapedTerm . "%'
                                 OR p.barcode LIKE '%" . $escapedTerm . "%'
                                 OR p.ean13 LIKE '%" . $escapedTerm . "%'
                                 OR p.isbn LIKE '%" . $escapedTerm . "%'
                                 OR p.partnumber LIKE '%" . $escapedTerm . "%'
                                 OR p.customcode LIKE '%" . $escapedTerm . "%')";
                break;
        }

        return implode(' AND ', $conditions);
    }
}
