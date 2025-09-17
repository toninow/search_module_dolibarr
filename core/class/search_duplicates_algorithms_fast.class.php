<?php
/* Copyright (C) 2024 - Fast Search Duplicates Module for Dolibarr */

/**
 * Fast class for duplicate search algorithms - Optimized for real database
 */
class SearchDuplicatesAlgorithmsFast
{
    private $db;
    private $conf;
    private $langs;

    public function __construct($db, $conf, $langs)
    {
        $this->db = $db;
        $this->conf = $conf;
        $this->langs = $langs;
    }

    /**
     * Find products by specific criteria (like the original search)
     */
    public function findByCriteria($criteria)
    {
        $duplicates = array();
        
        // Build SQL query based on criteria
        $where_conditions = array();
        $params = array();
        
        if (isset($criteria['id']) && !empty($criteria['id'])) {
            $where_conditions[] = "rowid = ?";
            $params[] = intval($criteria['id']);
        }
        
        if (isset($criteria['ref']) && !empty($criteria['ref'])) {
            $where_conditions[] = "ref LIKE ?";
            $params[] = '%' . $this->db->escape($criteria['ref']) . '%';
        }
        
        if (isset($criteria['name']) && !empty($criteria['name'])) {
            $where_conditions[] = "label LIKE ?";
            $params[] = '%' . $this->db->escape($criteria['name']) . '%';
        }
        
        if (isset($criteria['description']) && !empty($criteria['description'])) {
            $where_conditions[] = "description LIKE ?";
            $params[] = '%' . $this->db->escape($criteria['description']) . '%';
        }
        
        if (isset($criteria['barcode']) && !empty($criteria['barcode'])) {
            $where_conditions[] = "barcode LIKE ?";
            $params[] = '%' . $this->db->escape($criteria['barcode']) . '%';
        }
        
        if (empty($where_conditions)) {
            return $duplicates;
        }
        
        $sql = "SELECT rowid, ref, label, description, barcode, tosell, tobuy, hidden, finished, datec, stock
                FROM " . MAIN_DB_PREFIX . "product 
                WHERE entity = " . $this->conf->entity . " 
                AND (" . implode(" OR ", $where_conditions) . ")
                ORDER BY rowid";
        
        $resql = $this->db->query($sql, $params);
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $duplicates[] = $obj;
            }
        }
        
        return $duplicates;
    }

    /**
     * Find similar products using the best algorithm automatically
     */
    public function findSimilarProductsAdvanced($product, $threshold = 75, $algorithm = 'auto')
    {
        $similar_products = array();
        
        // Get all products for comparison (limit to 1000 for performance)
        $sql = "SELECT rowid, ref, label, description, barcode, tosell, tobuy, hidden, finished, datec, stock
                FROM " . MAIN_DB_PREFIX . "product 
                WHERE entity = " . $this->conf->entity . " 
                AND rowid != " . intval($product->rowid) . "
                ORDER BY rowid
                LIMIT 1000";
        
        $resql = $this->db->query($sql);
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $similarity = $this->calculateAdvancedSimilarity($product, $obj, $algorithm);
                
                if ($similarity >= $threshold) {
                    $obj->similarity = $similarity;
                    $similar_products[] = $obj;
                }
            }
        }
        
        // Sort by similarity (highest first)
        usort($similar_products, function($a, $b) {
            return $b->similarity - $a->similarity;
        });
        
        return $similar_products;
    }

    /**
     * Calculate advanced similarity between two products using the best algorithm
     */
    private function calculateAdvancedSimilarity($product1, $product2, $algorithm = 'auto')
    {
        if ($algorithm === 'auto') {
            // Usar automáticamente el mejor algoritmo para cada caso
            return $this->calculateBestSimilarity($product1, $product2);
        }
        
        switch ($algorithm) {
            case 'exact_match':
                return $this->exactMatchSimilarity($product1, $product2);
                
            case 'levenshtein':
                return $this->levenshteinSimilarity($product1, $product2);
                
            case 'ngrams':
                return $this->ngramsSimilarity($product1, $product2);
                
            case 'token_based':
                return $this->tokenBasedSimilarity($product1, $product2);
                
            case 'vectorization':
                return $this->vectorizationSimilarity($product1, $product2);
                
            case 'hybrid':
            default:
                return $this->hybridSimilarity($product1, $product2);
        }
    }

    /**
     * Calculate the best similarity using multiple algorithms and return the highest
     */
    private function calculateBestSimilarity($product1, $product2)
    {
        // Calcular con todos los algoritmos y usar el mejor resultado
        $similarities = array(
            'exact' => $this->exactMatchSimilarity($product1, $product2),
            'levenshtein' => $this->levenshteinSimilarity($product1, $product2),
            'ngrams' => $this->ngramsSimilarity($product1, $product2),
            'token' => $this->tokenBasedSimilarity($product1, $product2),
            'vector' => $this->vectorizationSimilarity($product1, $product2)
        );
        
        // Retornar el mayor porcentaje de similitud
        return max($similarities);
    }

    /**
     * Exact Match Algorithm - Perfect for EAN/UPC, SKU, Reference
     */
    private function exactMatchSimilarity($product1, $product2)
    {
        $similarity = 0;
        $fields = array('ref', 'barcode');
        $totalWeight = 0;
        
        foreach ($fields as $field) {
            $weight = ($field == 'ref') ? 0.7 : 0.3;
            $totalWeight += $weight;
            
            if (!empty($product1->$field) && !empty($product2->$field)) {
                if (strcasecmp(trim($product1->$field), trim($product2->$field)) == 0) {
                    $similarity += $weight * 100;
                }
            }
        }
        
        return $totalWeight > 0 ? $similarity / $totalWeight : 0;
    }

    /**
     * Levenshtein Distance Algorithm - Good for typos and small differences
     */
    private function levenshteinSimilarity($product1, $product2)
    {
        $fields = array('label', 'description', 'ref');
        $weights = array('label' => 0.5, 'description' => 0.3, 'ref' => 0.2);
        $totalSimilarity = 0;
        $totalWeight = 0;
        
        foreach ($fields as $field) {
            if (!empty($product1->$field) && !empty($product2->$field)) {
                $text1 = strtolower(trim($product1->$field));
                $text2 = strtolower(trim($product2->$field));
                
                $maxLength = max(strlen($text1), strlen($text2));
                if ($maxLength > 0) {
                    $distance = levenshtein($text1, $text2);
                    $similarity = (1 - ($distance / $maxLength)) * 100;
                    $totalSimilarity += $similarity * $weights[$field];
                    $totalWeight += $weights[$field];
                }
            }
        }
        
        return $totalWeight > 0 ? $totalSimilarity / $totalWeight : 0;
    }

    /**
     * N-grams Algorithm - Good for partial matches and word order differences
     */
    private function ngramsSimilarity($product1, $product2)
    {
        $fields = array('label', 'description');
        $weights = array('label' => 0.7, 'description' => 0.3);
        $totalSimilarity = 0;
        $totalWeight = 0;
        
        foreach ($fields as $field) {
            if (!empty($product1->$field) && !empty($product2->$field)) {
                $text1 = strtolower(trim($product1->$field));
                $text2 = strtolower(trim($product2->$field));
                
                $similarity = $this->calculateNgramsSimilarity($text1, $text2);
                $totalSimilarity += $similarity * $weights[$field];
                $totalWeight += $weights[$field];
            }
        }
        
        return $totalWeight > 0 ? $totalSimilarity / $totalWeight : 0;
    }

    /**
     * Calculate N-grams similarity between two texts
     */
    private function calculateNgramsSimilarity($text1, $text2, $n = 3)
    {
        $ngrams1 = $this->generateNgrams($text1, $n);
        $ngrams2 = $this->generateNgrams($text2, $n);
        
        $intersection = array_intersect($ngrams1, $ngrams2);
        $union = array_unique(array_merge($ngrams1, $ngrams2));
        
        return count($union) > 0 ? (count($intersection) / count($union)) * 100 : 0;
    }

    /**
     * Generate N-grams from text
     */
    private function generateNgrams($text, $n)
    {
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

    /**
     * Token-based Algorithm (Jaccard, Dice) - Good for word-based comparison
     */
    private function tokenBasedSimilarity($product1, $product2)
    {
        $fields = array('label', 'description');
        $weights = array('label' => 0.7, 'description' => 0.3);
        $totalSimilarity = 0;
        $totalWeight = 0;
        
        foreach ($fields as $field) {
            if (!empty($product1->$field) && !empty($product2->$field)) {
                $text1 = strtolower(trim($product1->$field));
                $text2 = strtolower(trim($product2->$field));
                
                $jaccard = $this->calculateJaccardSimilarity($text1, $text2);
                $dice = $this->calculateDiceSimilarity($text1, $text2);
                $similarity = ($jaccard + $dice) / 2;
                
                $totalSimilarity += $similarity * $weights[$field];
                $totalWeight += $weights[$field];
            }
        }
        
        return $totalWeight > 0 ? $totalSimilarity / $totalWeight : 0;
    }

    /**
     * Calculate Jaccard similarity
     */
    private function calculateJaccardSimilarity($text1, $text2)
    {
        $tokens1 = $this->tokenize($text1);
        $tokens2 = $this->tokenize($text2);
        
        $intersection = array_intersect($tokens1, $tokens2);
        $union = array_unique(array_merge($tokens1, $tokens2));
        
        return count($union) > 0 ? (count($intersection) / count($union)) * 100 : 0;
    }

    /**
     * Calculate Dice similarity
     */
    private function calculateDiceSimilarity($text1, $text2)
    {
        $tokens1 = $this->tokenize($text1);
        $tokens2 = $this->tokenize($text2);
        
        $intersection = array_intersect($tokens1, $tokens2);
        $total = count($tokens1) + count($tokens2);
        
        return $total > 0 ? (2 * count($intersection) / $total) * 100 : 0;
    }

    /**
     * Tokenize text into words
     */
    private function tokenize($text)
    {
        $text = preg_replace('/[^a-z0-9\s]/', '', strtolower($text));
        $words = explode(' ', $text);
        return array_filter($words, function($word) {
            return strlen($word) > 2; // Filter out short words
        });
    }

    /**
     * Vectorization Algorithm (TF-IDF + Cosine) - Advanced text similarity
     */
    private function vectorizationSimilarity($product1, $product2)
    {
        $fields = array('label', 'description');
        $weights = array('label' => 0.7, 'description' => 0.3);
        $totalSimilarity = 0;
        $totalWeight = 0;
        
        foreach ($fields as $field) {
            if (!empty($product1->$field) && !empty($product2->$field)) {
                $text1 = strtolower(trim($product1->$field));
                $text2 = strtolower(trim($product2->$field));
                
                $similarity = $this->calculateCosineSimilarity($text1, $text2);
                $totalSimilarity += $similarity * $weights[$field];
                $totalWeight += $weights[$field];
            }
        }
        
        return $totalWeight > 0 ? $totalSimilarity / $totalWeight : 0;
    }

    /**
     * Calculate cosine similarity between two texts
     */
    private function calculateCosineSimilarity($text1, $text2)
    {
        $tokens1 = $this->tokenize($text1);
        $tokens2 = $this->tokenize($text2);
        
        $allTokens = array_unique(array_merge($tokens1, $tokens2));
        
        $vector1 = array();
        $vector2 = array();
        
        foreach ($allTokens as $token) {
            $vector1[] = substr_count($text1, $token);
            $vector2[] = substr_count($text2, $token);
        }
        
        $dotProduct = 0;
        $norm1 = 0;
        $norm2 = 0;
        
        for ($i = 0; $i < count($vector1); $i++) {
            $dotProduct += $vector1[$i] * $vector2[$i];
            $norm1 += $vector1[$i] * $vector1[$i];
            $norm2 += $vector2[$i] * $vector2[$i];
        }
        
        $norm1 = sqrt($norm1);
        $norm2 = sqrt($norm2);
        
        if ($norm1 > 0 && $norm2 > 0) {
            return ($dotProduct / ($norm1 * $norm2)) * 100;
        }
        
        return 0;
    }

    /**
     * Hybrid Algorithm - Combination of all algorithms with smart weighting
     */
    private function hybridSimilarity($product1, $product2)
    {
        // Get individual similarities
        $exact = $this->exactMatchSimilarity($product1, $product2);
        $levenshtein = $this->levenshteinSimilarity($product1, $product2);
        $ngrams = $this->ngramsSimilarity($product1, $product2);
        $token = $this->tokenBasedSimilarity($product1, $product2);
        $vector = $this->vectorizationSimilarity($product1, $product2);
        
        // Smart weighting based on product characteristics
        $weights = array(
            'exact' => 0.3,      // High weight for exact matches
            'levenshtein' => 0.25, // Good for typos
            'ngrams' => 0.2,     // Good for partial matches
            'token' => 0.15,     // Good for word-based comparison
            'vector' => 0.1      // Advanced but can be slow
        );
        
        // Calculate weighted average
        $totalSimilarity = 0;
        $totalWeight = 0;
        
        foreach ($weights as $algorithm => $weight) {
            $similarity = ${$algorithm};
            $totalSimilarity += $similarity * $weight;
            $totalWeight += $weight;
        }
        
        return $totalWeight > 0 ? $totalSimilarity / $totalWeight : 0;
    }

    /**
     * Find specific product and its duplicates (like the 520R example)
     */
    public function findSpecificProduct($search_term, $fields = array('name', 'description', 'ref'), $threshold = 80)
    {
        $duplicates = array();
        
        // First, find products that match the search term
        $search_conditions = array();
        $search_params = array();
        
        foreach ($fields as $field) {
            switch ($field) {
                case 'name':
                    $search_conditions[] = "label LIKE ?";
                    $search_params[] = '%' . $this->db->escape($search_term) . '%';
                    break;
                case 'description':
                    $search_conditions[] = "description LIKE ?";
                    $search_params[] = '%' . $this->db->escape($search_term) . '%';
                    break;
                case 'ref':
                    $search_conditions[] = "ref LIKE ?";
                    $search_params[] = '%' . $this->db->escape($search_term) . '%';
                    break;
            }
        }
        
        if (empty($search_conditions)) {
            return $duplicates;
        }
        
        $sql = "SELECT rowid, ref, label, description, barcode, tosell, tobuy, hidden, finished, datec, stock
                FROM " . MAIN_DB_PREFIX . "product 
                WHERE entity = " . $this->conf->entity . " 
                AND (" . implode(" OR ", $search_conditions) . ")
                ORDER BY rowid";
        
        $resql = $this->db->query($sql, $search_params);
        if ($resql) {
            $found_products = array();
            while ($obj = $this->db->fetch_object($resql)) {
                $found_products[] = $obj;
            }
            
            // Group similar products
            $groups = array();
            $processed = array();
            
            foreach ($found_products as $product) {
                if (in_array($product->rowid, $processed)) continue;
                
                $group_key = $product->rowid;
                $groups[$group_key] = array($product);
                $processed[] = $product->rowid;
                
                // Find similar products
                foreach ($found_products as $other_product) {
                    if ($other_product->rowid == $product->rowid || in_array($other_product->rowid, $processed)) continue;
                    
                    $similarity = $this->calculateAdvancedSimilarity($product, $other_product, 'hybrid');
                    
                    if ($similarity >= $threshold) {
                        $other_product->similarity = $similarity;
                        $groups[$group_key][] = $other_product;
                        $processed[] = $other_product->rowid;
                    }
                }
            }
            
            // Convert groups to duplicates array
            foreach ($groups as $group_products) {
                if (count($group_products) > 1) {
                    $duplicates[] = $group_products;
                }
            }
        }
        
        return $duplicates;
    }
}
?>