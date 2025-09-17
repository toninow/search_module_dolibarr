<?php
/* Copyright (C) 2024 - Search Duplicates Module for Dolibarr */

/**
 * Class for duplicate search algorithms
 */
class SearchDuplicatesAlgorithms
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
     * Find specific product and its similar products
     */
    public function findSpecificProduct($search_term, $fields = array('name', 'description', 'ref'), $threshold = 80)
    {
        $duplicates = array();
        
        // Get all products that match the search term
        $sql = "SELECT rowid, ref, label, description FROM " . MAIN_DB_PREFIX . "product WHERE entity = " . $this->conf->entity;
        $resql = $this->db->query($sql);
        
        if ($resql) {
            $products = array();
            while ($obj = $this->db->fetch_object($resql)) {
                $products[] = $obj;
            }
            
            // Find products that match the search term
            $matching_products = array();
            foreach ($products as $product) {
                $match = false;
                foreach ($fields as $field) {
                    $value = $this->getFieldValue($product, $field);
                    if (stripos($value, $search_term) !== false) {
                        $match = true;
                        break;
                    }
                }
                if ($match) {
                    $matching_products[] = $product;
                }
            }
            
            // For each matching product, find similar products
            foreach ($matching_products as $product1) {
                foreach ($products as $product2) {
                    if ($product1->rowid != $product2->rowid) {
                        $similarity = $this->calculateSimilarity($product1, $product2, $fields);
                        
                        if ($similarity >= $threshold) {
                            $duplicates[] = array(
                                'product1_id' => $product1->rowid,
                                'product2_id' => $product2->rowid,
                                'product1_ref' => $product1->ref,
                                'product2_ref' => $product2->ref,
                                'product1_name' => $product1->label,
                                'product2_name' => $product2->label,
                                'algorithm' => 'specific_search',
                                'similarity' => $similarity
                            );
                        }
                    }
                }
            }
        }
        
        return $duplicates;
    }

    /**
     * Main function to find duplicates based on selected algorithm
     */
    public function findDuplicates($fields = array('name', 'description', 'ref'), $threshold = 80)
    {
        $algorithm = $this->conf->global->SEARCH_DUPLICATES_ALGORITHM ?? 'hybrid';
        
        switch ($algorithm) {
            case 'exact_match':
                return $this->exactMatchAlgorithm($fields);
            case 'levenshtein':
                return $this->levenshteinAlgorithm($fields, $threshold);
            case 'ngrams':
                return $this->ngramsAlgorithm($fields, $threshold);
            case 'token_based':
                return $this->tokenBasedAlgorithm($fields, $threshold);
            case 'vectorization':
                return $this->vectorizationAlgorithm($fields, $threshold);
            case 'hybrid':
            default:
                return $this->hybridAlgorithm($fields, $threshold);
        }
    }

    /**
     * 1. Exact Match Algorithm
     * Most reliable and fast - detects exact duplicates by unique codes
     */
    private function exactMatchAlgorithm($fields)
    {
        $duplicates = array();
        
        // Check for exact matches in EAN/UPC, SKU, reference
        $sql = "SELECT p1.rowid as id1, p2.rowid as id2, p1.ref as ref1, p2.ref as ref2, 
                       p1.label as name1, p2.label as name2,
                       'exact_match' as algorithm, 100 as similarity
                FROM " . MAIN_DB_PREFIX . "product as p1
                INNER JOIN " . MAIN_DB_PREFIX . "product as p2 ON p1.rowid < p2.rowid
                WHERE (p1.barcode = p2.barcode AND p1.barcode != '' AND p1.barcode IS NOT NULL)
                   OR (p1.ref = p2.ref AND p1.ref != '' AND p1.ref IS NOT NULL)
                   OR (p1.supplier_ref = p2.supplier_ref AND p1.supplier_ref != '' AND p1.supplier_ref IS NOT NULL)";
        
        $resql = $this->db->query($sql);
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $duplicates[] = array(
                    'product1_id' => $obj->id1,
                    'product2_id' => $obj->id2,
                    'product1_ref' => $obj->ref1,
                    'product2_ref' => $obj->ref2,
                    'product1_name' => $obj->name1,
                    'product2_name' => $obj->name2,
                    'algorithm' => $obj->algorithm,
                    'similarity' => $obj->similarity
                );
            }
        }
        
        return $duplicates;
    }

    /**
     * 2. Levenshtein Distance Algorithm
     * Detects typos and variations in names
     */
    private function levenshteinAlgorithm($fields, $threshold)
    {
        $duplicates = array();
        
        // Get all products
        $sql = "SELECT rowid, ref, label, description FROM " . MAIN_DB_PREFIX . "product WHERE entity = " . $this->conf->entity;
        $resql = $this->db->query($sql);
        
        if ($resql) {
            $products = array();
            while ($obj = $this->db->fetch_object($resql)) {
                $products[] = $obj;
            }
            
            // Compare each product with others
            for ($i = 0; $i < count($products); $i++) {
                for ($j = $i + 1; $j < count($products); $j++) {
                    $similarity = $this->calculateLevenshteinSimilarity($products[$i], $products[$j], $fields);
                    
                    if ($similarity >= $threshold) {
                        $duplicates[] = array(
                            'product1_id' => $products[$i]->rowid,
                            'product2_id' => $products[$j]->rowid,
                            'product1_ref' => $products[$i]->ref,
                            'product2_ref' => $products[$j]->ref,
                            'product1_name' => $products[$i]->label,
                            'product2_name' => $products[$j]->label,
                            'algorithm' => 'levenshtein',
                            'similarity' => $similarity
                        );
                    }
                }
            }
        }
        
        return $duplicates;
    }

    /**
     * 3. N-grams/Trigrams Algorithm
     * Very efficient with PostgreSQL
     */
    private function ngramsAlgorithm($fields, $threshold)
    {
        $duplicates = array();
        
        // For MySQL, we'll use a simplified n-gram approach
        $sql = "SELECT p1.rowid as id1, p2.rowid as id2, p1.ref as ref1, p2.ref as ref2,
                       p1.label as name1, p2.label as name2
                FROM " . MAIN_DB_PREFIX . "product as p1
                INNER JOIN " . MAIN_DB_PREFIX . "product as p2 ON p1.rowid < p2.rowid
                WHERE p1.entity = " . $this->conf->entity . " AND p2.entity = " . $this->conf->entity;
        
        $resql = $this->db->query($sql);
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $similarity = $this->calculateNgramsSimilarity($obj->name1, $obj->name2);
                
                if ($similarity >= $threshold) {
                    $duplicates[] = array(
                        'product1_id' => $obj->id1,
                        'product2_id' => $obj->id2,
                        'product1_ref' => $obj->ref1,
                        'product2_ref' => $obj->ref2,
                        'product1_name' => $obj->name1,
                        'product2_name' => $obj->name2,
                        'algorithm' => 'ngrams',
                        'similarity' => $similarity
                    );
                }
            }
        }
        
        return $duplicates;
    }

    /**
     * 4. Token-based Algorithm (Jaccard, Dice)
     * Compares word sets regardless of order
     */
    private function tokenBasedAlgorithm($fields, $threshold)
    {
        $duplicates = array();
        
        $sql = "SELECT p1.rowid as id1, p2.rowid as id2, p1.ref as ref1, p2.ref as ref2,
                       p1.label as name1, p2.label as name2
                FROM " . MAIN_DB_PREFIX . "product as p1
                INNER JOIN " . MAIN_DB_PREFIX . "product as p2 ON p1.rowid < p2.rowid
                WHERE p1.entity = " . $this->conf->entity . " AND p2.entity = " . $this->conf->entity;
        
        $resql = $this->db->query($sql);
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $similarity = $this->calculateJaccardSimilarity($obj->name1, $obj->name2);
                
                if ($similarity >= $threshold) {
                    $duplicates[] = array(
                        'product1_id' => $obj->id1,
                        'product2_id' => $obj->id2,
                        'product1_ref' => $obj->ref1,
                        'product2_ref' => $obj->ref2,
                        'product1_name' => $obj->name1,
                        'product2_name' => $obj->name2,
                        'algorithm' => 'token_based',
                        'similarity' => $similarity
                    );
                }
            }
        }
        
        return $duplicates;
    }

    /**
     * 5. Vectorization Algorithm (TF-IDF + Cosine)
     * Converts text to vectors for semantic similarity
     */
    private function vectorizationAlgorithm($fields, $threshold)
    {
        $duplicates = array();
        
        // Simplified TF-IDF implementation
        $sql = "SELECT p1.rowid as id1, p2.rowid as id2, p1.ref as ref1, p2.ref as ref2,
                       p1.label as name1, p2.label as name2
                FROM " . MAIN_DB_PREFIX . "product as p1
                INNER JOIN " . MAIN_DB_PREFIX . "product as p2 ON p1.rowid < p2.rowid
                WHERE p1.entity = " . $this->conf->entity . " AND p2.entity = " . $this->conf->entity;
        
        $resql = $this->db->query($sql);
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $similarity = $this->calculateCosineSimilarity($obj->name1, $obj->name2);
                
                if ($similarity >= $threshold) {
                    $duplicates[] = array(
                        'product1_id' => $obj->id1,
                        'product2_id' => $obj->id2,
                        'product1_ref' => $obj->ref1,
                        'product2_ref' => $obj->ref2,
                        'product1_name' => $obj->name1,
                        'product2_name' => $obj->name2,
                        'algorithm' => 'vectorization',
                        'similarity' => $similarity
                    );
                }
            }
        }
        
        return $duplicates;
    }

    /**
     * 6. Hybrid Algorithm
     * Uses multiple algorithms for maximum precision
     */
    private function hybridAlgorithm($fields, $threshold)
    {
        $allDuplicates = array();
        
        // Run all algorithms
        $exactDuplicates = $this->exactMatchAlgorithm($fields);
        $levenshteinDuplicates = $this->levenshteinAlgorithm($fields, $threshold);
        $ngramsDuplicates = $this->ngramsAlgorithm($fields, $threshold);
        $tokenDuplicates = $this->tokenBasedAlgorithm($fields, $threshold);
        $vectorDuplicates = $this->vectorizationAlgorithm($fields, $threshold);
        
        // Combine results
        $allDuplicates = array_merge($exactDuplicates, $levenshteinDuplicates, $ngramsDuplicates, $tokenDuplicates, $vectorDuplicates);
        
        // Remove duplicates and calculate combined similarity
        $uniqueDuplicates = array();
        $seen = array();
        
        foreach ($allDuplicates as $dup) {
            $key = min($dup['product1_id'], $dup['product2_id']) . '_' . max($dup['product1_id'], $dup['product2_id']);
            
            if (!isset($seen[$key])) {
                $seen[$key] = $dup;
                $seen[$key]['algorithm'] = 'hybrid';
            } else {
                // Combine similarities (average)
                $seen[$key]['similarity'] = ($seen[$key]['similarity'] + $dup['similarity']) / 2;
            }
        }
        
        return array_values($seen);
    }

    /**
     * Calculate Levenshtein similarity between two products
     */
    private function calculateLevenshteinSimilarity($product1, $product2, $fields)
    {
        $maxSimilarity = 0;
        
        foreach ($fields as $field) {
            $text1 = $this->getFieldValue($product1, $field);
            $text2 = $this->getFieldValue($product2, $field);
            
            if (!empty($text1) && !empty($text2)) {
                $distance = levenshtein(strtolower($text1), strtolower($text2));
                $maxLength = max(strlen($text1), strlen($text2));
                $similarity = $maxLength > 0 ? (1 - $distance / $maxLength) * 100 : 0;
                $maxSimilarity = max($maxSimilarity, $similarity);
            }
        }
        
        return $maxSimilarity;
    }

    /**
     * Calculate N-grams similarity
     */
    private function calculateNgramsSimilarity($text1, $text2)
    {
        $text1 = strtolower($text1);
        $text2 = strtolower($text2);
        
        $ngrams1 = $this->getNgrams($text1, 3);
        $ngrams2 = $this->getNgrams($text2, 3);
        
        $intersection = array_intersect($ngrams1, $ngrams2);
        $union = array_unique(array_merge($ngrams1, $ngrams2));
        
        return count($union) > 0 ? (count($intersection) / count($union)) * 100 : 0;
    }

    /**
     * Calculate Jaccard similarity
     */
    private function calculateJaccardSimilarity($text1, $text2)
    {
        $words1 = array_unique(explode(' ', strtolower($text1)));
        $words2 = array_unique(explode(' ', strtolower($text2)));
        
        $intersection = array_intersect($words1, $words2);
        $union = array_unique(array_merge($words1, $words2));
        
        return count($union) > 0 ? (count($intersection) / count($union)) * 100 : 0;
    }

    /**
     * Calculate Cosine similarity (simplified)
     */
    private function calculateCosineSimilarity($text1, $text2)
    {
        $words1 = explode(' ', strtolower($text1));
        $words2 = explode(' ', strtolower($text2));
        
        $allWords = array_unique(array_merge($words1, $words2));
        $vector1 = array();
        $vector2 = array();
        
        foreach ($allWords as $word) {
            $vector1[] = substr_count($text1, $word);
            $vector2[] = substr_count($text2, $word);
        }
        
        $dotProduct = 0;
        $norm1 = 0;
        $norm2 = 0;
        
        for ($i = 0; $i < count($vector1); $i++) {
            $dotProduct += $vector1[$i] * $vector2[$i];
            $norm1 += $vector1[$i] * $vector1[$i];
            $norm2 += $vector2[$i] * $vector2[$i];
        }
        
        if ($norm1 == 0 || $norm2 == 0) return 0;
        
        return ($dotProduct / (sqrt($norm1) * sqrt($norm2))) * 100;
    }

    /**
     * Calculate similarity between two products using multiple algorithms
     */
    private function calculateSimilarity($product1, $product2, $fields)
    {
        $maxSimilarity = 0;
        
        foreach ($fields as $field) {
            $text1 = $this->getFieldValue($product1, $field);
            $text2 = $this->getFieldValue($product2, $field);
            
            if (!empty($text1) && !empty($text2)) {
                // Levenshtein similarity
                $levenshtein = $this->calculateLevenshteinSimilarity($product1, $product2, array($field));
                
                // N-grams similarity
                $ngrams = $this->calculateNgramsSimilarity($text1, $text2);
                
                // Token-based similarity
                $token = $this->calculateJaccardSimilarity($text1, $text2);
                
                // Take the maximum similarity
                $similarity = max($levenshtein, $ngrams, $token);
                $maxSimilarity = max($maxSimilarity, $similarity);
            }
        }
        
        return $maxSimilarity;
    }

    /**
     * Get field value from product object
     */
    private function getFieldValue($product, $field)
    {
        switch ($field) {
            case 'name':
            case 'label':
                return $product->label ?? '';
            case 'description':
                return $product->description ?? '';
            case 'ref':
                return $product->ref ?? '';
            default:
                return '';
        }
    }

    /**
     * Generate n-grams from text
     */
    private function getNgrams($text, $n)
    {
        $ngrams = array();
        $text = preg_replace('/[^a-z0-9\s]/', '', strtolower($text));
        
        for ($i = 0; $i <= strlen($text) - $n; $i++) {
            $ngrams[] = substr($text, $i, $n);
        }
        
        return $ngrams;
    }
}
