<?php
/*  Duplicados de productos (rápido y universal)
 *  - Búsqueda por referencia / nombre / descripción / EAN
 *  - Detección de duplicados con heurísticas + BLOQUES (candidate sets)
 *  - Cache en $_SESSION por filtros (no recomputa al cambiar de pestaña)
 *  - Paginación en Únicos y Duplicados
 */

// ====== Entorno Dolibarr ======
if (!defined('DOL_DOCUMENT_ROOT')) {
$res = 0;
    if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php";
    if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
    if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
    if (!$res && file_exists("../../../../main.inc.php")) $res = @include "../../../../main.inc.php";
    if (!$res) die("No se encontró main.inc.php");
}
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// ====== Parámetros ======
$action              = GETPOST('action', 'aZ09');
$clear               = GETPOST('clear', 'int');

$product_id_raw      = trim((string) GETPOST('product_id', 'restricthtml')); // "14390,14326"
$product_ref         = trim((string) GETPOST('product_ref', 'restricthtml'));
$product_name        = trim((string) GETPOST('product_name', 'restricthtml'));
$product_description = trim((string) GETPOST('product_description', 'restricthtml'));
$product_ean         = trim((string) GETPOST('product_ean', 'restricthtml'));

$limit               = (int) GETPOST('limit', 'int');
if ($limit < 200 || $limit > 50000) $limit = 2000;

$tab                 = GETPOST('tab', 'alphanohtml', 'duplicates');
if ($tab !== 'unique' && $tab !== 'duplicates') $tab = 'duplicates';

$page                = max(1, (int) GETPOST('page', 'int'));
$per_page            = (int) GETPOST('per_page', 'int');
if ($per_page < 50 || $per_page > 2000) $per_page = 200; // tamaño de página sano

if ((int)$clear === 1) {
    $action = '';
    $product_id_raw = $product_ref = $product_name = $product_description = $product_ean = '';
    $page = 1;
}

// ====== Helpers universales ======
function mp_normalize($text) {
    $text = (string)$text;
    $text = trim($text);
    $text = mb_strtolower($text, 'UTF-8');
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text);
    $text = preg_replace('/\s+/', ' ', $text);
    return trim($text);
}
function mp_normalize_code($text) {
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string)$text);
    return preg_replace('/[^A-Za-z0-9]/', '', $text ?? '');
}
function mp_lang_stopwords() {
    static $sw = null;
    if ($sw !== null) return $sw;
    $es = [
        'a','ante','bajo','cabe','con','contra','de','del','desde','durante','en','entre','hacia','hasta','mediante','para','por','segun','sin','so','sobre','tras',
        'el','la','los','las','un','una','unos','unas','al','lo',
        'y','o','u','e','ni','que','como','cuando','donde','cual','cuales','cuyo','cuyos','cuyas',
        'su','sus','mi','mis','tu','tus','nuestro','nuestra','nuestros','nuestras','vuestro','vuestra','vuestros','vuestras',
        'es','son','ser','esta','estan','estar','fue','fueron','era','eran','sea','sean','ha','han','haber','hay',
        'este','esta','estos','estas','ese','esa','esos','esas','aqui','alli','alla','asi',
    ];
    $en = [
        'a','an','the','and','or','but','if','then','else','for','from','to','of','in','on','at','by','with','without','over','under','between','into','through',
        'is','are','be','been','being','was','were','has','have','had','do','does','did','this','that','these','those','here','there',
        'my','your','his','her','its','our','their','you','we','they','he','she','it',
    ];
    $sw = array_unique(array_merge($es, $en));
    return $sw;
}
function mp_tokens_basic($text) {
    $norm = mp_normalize($text);
    if ($norm === '') return [];
    $parts = explode(' ', $norm);
    $langSet = array_fill_keys(mp_lang_stopwords(), true);
    $out = [];
    foreach ($parts as $p) {
        if ($p === '') continue;
        if (isset($langSet[$p])) continue;
        if (strlen($p) <= 2) continue;
        if (ctype_digit($p)) continue;
        $out[] = $p;
    }
    return array_values(array_unique($out));
}
function mp_dynamic_stopwords(array $products, float $thresholdFrac = 0.5) {
    $df = []; $N = max(1, count($products));
    foreach ($products as $p) {
        $text = ((string)$p->ref).' '.((string)$p->label).' '.((string)$p->description);
        $tokens = mp_tokens_basic($text);
        foreach (array_unique($tokens) as $t) $df[$t] = ($df[$t] ?? 0) + 1;
    }
    $dyn = [];
    foreach ($df as $t => $cnt) if ($cnt / $N >= $thresholdFrac) $dyn[$t] = true;
    return $dyn;
}
function mp_tokens($text, array $dynStop = []) {
    $tokens = mp_tokens_basic($text);
    if (!$dynStop) return $tokens;
    $out = [];
    foreach ($tokens as $t) if (!isset($dynStop[$t])) $out[] = $t;
    return array_values(array_unique($out));
}
function mp_jaccard(array $a, array $b) {
    if (!$a && !$b) return 1.0;
    $setA = array_unique($a); $setB = array_unique($b);
    $inter = array_intersect($setA, $setB);
    $union = array_unique(array_merge($setA, $setB));
    if (count($union) === 0) return 0.0;
    return count($inter) / count($union);
}
function mp_lev_ratio($a, $b) {
    $a = mp_normalize($a); $b = mp_normalize($b);
    $len = max(strlen($a), strlen($b));
    if ($len === 0) return 1.0;
    $dist = levenshtein($a, $b);
    $r = 1.0 - ($dist / $len);
    return max(0.0, min(1.0, $r));
}
function mp_has_filters($idraw, $ref, $name, $desc, $ean) {
    return (trim((string)$idraw) !== '' || $ref !== '' || $name !== '' || $desc !== '' || $ean !== '');
}
function mp_parse_ids($raw) {
    $ids = [];
    foreach (explode(',', (string)$raw) as $p) {
        $p = trim($p);
        if ($p !== '' && ctype_digit($p)) $ids[] = (int)$p;
    }
    return array_values(array_unique($ids));
}
function mp_extract_model_codes($text) {
    $t = strtoupper((string)$text);
    $t = preg_replace('/[^A-Z0-9\- ]/', ' ', $t);
    preg_match_all('/\b[A-Z]*\d+[A-Z]+|\b[A-Z]+\d+[A-Z]*|\b\d+[A-Z]+\b/', $t, $m);
    $codes = [];
    foreach ($m[0] as $c) {
        $c = preg_replace('/[^A-Z0-9]/', '', $c);
        if (strlen($c) >= 3) $codes[] = $c;
    }
    return array_values(array_unique($codes));
}

// ====== Acceso a datos ======
function mp_load_products($limit = 2000, $use_limit = true) {
    global $db, $conf;
    $sql = "SELECT p.rowid, p.ref, p.label, p.description, p.barcode, p.tosell, p.tobuy, p.hidden, p.finished, p.datec
            FROM ".MAIN_DB_PREFIX."product p
            WHERE p.entity = ".((int) $conf->entity)."
            ORDER BY p.datec DESC, p.rowid DESC";
    if ($use_limit) $sql .= " LIMIT ".((int)$limit);
    $res = $db->query($sql);
    if (!$res) return [];
    $out = [];
    while ($o = $db->fetch_object($res)) $out[] = $o;
    return $out;
}
function mp_load_products_by_filters($ids, $ref, $name, $desc, $ean) {
    global $db, $conf;
    $w = ["p.entity = ".((int)$conf->entity)];
    if (!empty($ids)) {
        $in = implode(',', array_map('intval', $ids));
        $w[] = "p.rowid IN ($in)";
    }
    if ($ref !== '') { $r = $db->escape($ref);
        $w[] = "(p.ref LIKE '%$r%' OR p.label LIKE '%$r%' OR p.description LIKE '%$r%')";
    }
    if ($name !== '') { $n = $db->escape($name);
        $w[] = "(p.label LIKE '%$n%' OR p.ref LIKE '%$n%' OR p.description LIKE '%$n%')";
    }
    if ($desc !== '') { $d = $db->escape($desc);
        $w[] = "(p.description LIKE '%$d%' OR p.label LIKE '%$d%' OR p.ref LIKE '%$d%')";
    }
    if ($ean !== '') { $e = $db->escape($ean);
        $w[] = "(p.barcode LIKE '%$e%' OR p.label LIKE '%$e%' OR p.description LIKE '%$e%')";
    }
    $sql = "SELECT p.rowid, p.ref, p.label, p.description, p.barcode, p.tosell, p.tobuy, p.hidden, p.finished, p.datec
            FROM ".MAIN_DB_PREFIX."product p
            WHERE ".implode(' AND ', $w)."
            ORDER BY p.rowid DESC";
    $res = $db->query($sql);
    if (!$res) return [];
    $out = [];
    while ($o = $db->fetch_object($res)) $out[] = $o;

    // Asegurar IDs pedidas aunque no coincidan con otros filtros
    if (!empty($ids)) {
        $present = array_fill_keys(array_map(fn($p)=>(int)$p->rowid, $out), true);
        $missing = [];
        foreach ($ids as $id) if (!isset($present[$id])) $missing[] = (int)$id;
        if (!empty($missing)) {
            $in = implode(',', $missing);
            $sql2 = "SELECT p.rowid, p.ref, p.label, p.description, p.barcode, p.tosell, p.tobuy, p.hidden, p.finished, p.datec
                     FROM ".MAIN_DB_PREFIX."product p
                     WHERE p.entity = ".((int)$conf->entity)." AND p.rowid IN ($in)";
            $res2 = $db->query($sql2);
            if ($res2) while ($o2 = $db->fetch_object($res2)) $out[] = $o2;
        }
    }
    return $out;
}

// ====== Detector con BLOQUES + Cache ======
class MPDuplicateDetector {
    private $products;   // array de objetos
    private $dynStop;    // stopwords dinámicas
    // Umbrales
    private $JACCARD_MIN = 0.75;
    private $LEV_MIN     = 0.80;
    private $SIMTXT_MIN  = 85;

    // Índices de bloqueo
    private $bucket = []; // key => list of idx

    public function __construct(array $products, array $dynStop = []) {
        $this->products = array_values($products);
        $this->dynStop  = $dynStop;
        $this->buildBuckets();
    }

    private function keypush($key, $idx) {
        if ($key === '' || $key === null) return;
        if (!isset($this->bucket[$key])) $this->bucket[$key] = [];
        $this->bucket[$key][] = $idx;
    }

    private function buildBuckets() {
        // Crea claves de bloqueo para reducir comparaciones
        foreach ($this->products as $i => $p) {
            $ref  = mp_normalize_code($p->ref);
            $ean  = mp_normalize_code($p->barcode);
            $name = (string)$p->label;
            $desc = (string)$p->description;

            if ($ean !== '') $this->keypush('EAN:'.$ean, $i);

            if ($ref !== '') {
                $this->keypush('REF:'.$ref, $i);
                // prefijo/sufijo ayudan cuando hay sufijos de color/talla
                $this->keypush('REFP:'.substr($ref, 0, 6), $i);
                $this->keypush('REFS:'.substr($ref, -6), $i);
            }

            // Códigos de modelo
            foreach (mp_extract_model_codes($p->ref.' '.$name) as $code) {
                $this->keypush('MOD:'.$code, $i);
            }

            // Firma de tokens (bag limitada): top 5 tokens ordenados
            $tokens = mp_tokens($p->ref.' '.$name.' '.$desc, $this->dynStop);
            if ($tokens) {
                sort($tokens, SORT_STRING);
                $sig = implode('|', array_slice($tokens, 0, 5));
                $this->keypush('SIG:'.$sig, $i);
            }
        }
    }

    private function candidatesFor($i) {
        // Unión de los buckets donde cae el producto i
        $cands = [];
        $seen  = [];
        $p = $this->products[$i];

        $probeKeys = [];

        $ref  = mp_normalize_code($p->ref);
        $ean  = mp_normalize_code($p->barcode);
        $name = (string)$p->label;
        $desc = (string)$p->description;

        if ($ean !== '') $probeKeys[] = 'EAN:'.$ean;
        if ($ref !== '') {
            $probeKeys[] = 'REF:'.$ref;
            $probeKeys[] = 'REFP:'.substr($ref, 0, 6);
            $probeKeys[] = 'REFS:'.substr($ref, -6);
        }
        foreach (mp_extract_model_codes($p->ref.' '.$name) as $code) {
            $probeKeys[] = 'MOD:'.$code;
        }
        $tokens = mp_tokens($p->ref.' '.$name.' '.$desc, $this->dynStop);
        if ($tokens) {
            sort($tokens, SORT_STRING);
            $sig = implode('|', array_slice($tokens, 0, 5));
            $probeKeys[] = 'SIG:'.$sig;
        }

        foreach ($probeKeys as $k) {
            if (!isset($this->bucket[$k])) continue;
            foreach ($this->bucket[$k] as $j) {
                if ($j === $i) continue;
                if (isset($seen[$j])) continue;
                $seen[$j] = true;
                $cands[] = $j;
            }
        }
        return $cands;
    }

    public function separate() {
        $dups = [];
        $uniq = [];
        $used = [];

        $n = count($this->products);
        for ($i = 0; $i < $n; $i++) {
            if (isset($used[$i])) continue;

            $group = [$this->products[$i]];
            $used[$i] = true;

            $cands = $this->candidatesFor($i);
            if ($cands) {
                foreach ($cands as $j) {
                    if (isset($used[$j])) continue;
                if ($this->areDuplicates($this->products[$i], $this->products[$j])) {
                    $group[] = $this->products[$j];
                        $used[$j] = true;
                    }
                }
            }

            if (count($group) > 1) $dups[] = $group;
            else $uniq[] = $group[0];
        }

        return ['duplicates' => $dups, 'unique' => $uniq];
    }

    private function areDuplicates($a, $b) {
        // Early check diferentes IDs
        if ((int)$a->rowid === (int)$b->rowid) return false;

        $refA = mp_normalize_code($a->ref);
        $refB = mp_normalize_code($b->ref);
        $eanA = mp_normalize_code($a->barcode);
        $eanB = mp_normalize_code($b->barcode);

        $nameA = (string)$a->label;
        $nameB = (string)$b->label;
        $descA = (string)$a->description;
        $descB = (string)$b->description;

        $canonA = trim(mp_normalize($a->ref.' '.$nameA.' '.$descA));
        $canonB = trim(mp_normalize($b->ref.' '.$nameB.' '.$descB));

        // 1) EAN exacto
        if ($eanA !== '' && $eanA === $eanB) return true;

        // 2) Ref exacta / contenida (≥4 chars)
        if ($refA !== '' && $refB !== '') {
            if ($refA === $refB) return true;
            if (strlen($refA) >= 4 && strlen($refB) >= 4) {
                if (strpos($refA, $refB) !== false || strpos($refB, $refA) !== false) return true;
            }
        }

        // 3) Códigos de modelo
        $codesA = mp_extract_model_codes($a->ref.' '.$nameA);
        $codesB = mp_extract_model_codes($b->ref.' '.$nameB);
        if ($codesA && $codesB && !empty(array_intersect($codesA, $codesB))) return true;
        if ($codesA xor $codesB) {
            $codes = $codesA ?: $codesB;
            foreach ($codes as $c) if ($c !== '' && strpos(strtoupper($canonA.' '.$canonB), $c) !== false) return true;
        }

        // 4) Subcadenas canon (≥8)
        if (strlen($canonA) >= 8 && strlen($canonB) >= 8) {
            if (strpos($canonA, $canonB) !== false || strpos($canonB, $canonA) !== false) return true;
        }

        // 5) Tokens + Jaccard
        $tA = mp_tokens($a->ref.' '.$nameA.' '.$descA, $this->dynStop);
        $tB = mp_tokens($b->ref.' '.$nameB.' '.$descB, $this->dynStop);
        if ($tA && $tB) {
            $jac = mp_jaccard($tA, $tB);
            if ($jac >= $this->JACCARD_MIN) return true;
        }

        // 6) Levenshtein
        $lev = mp_lev_ratio($canonA, $canonB);
        if ($lev >= $this->LEV_MIN) return true;

        // 7) similar_text
        similar_text($canonA, $canonB, $pct);
        if ($pct >= $this->SIMTXT_MIN) return true;

        return false;
    }
}

// ====== Carga y filtrado ======
$ids = mp_parse_ids($product_id_raw);
if (mp_has_filters($product_id_raw, $product_ref, $product_name, $product_description, $product_ean)) {
    $filtered = mp_load_products_by_filters($ids, $product_ref, $product_name, $product_description, $product_ean);
} else {
    $filtered = mp_load_products($limit, true);
}

// ====== Cache por filtros ======
$cache_key = md5(json_encode([
    'ids'=>$ids,'ref'=>$product_ref,'name'=>$product_name,'desc'=>$product_description,'ean'=>$product_ean,
    'limit'=>$limit
]));
if (!isset($_SESSION['dup_cache'])) $_SESSION['dup_cache'] = [];

if (!isset($_SESSION['dup_cache'][$cache_key])) {
    // Construye stopwords dinámicas y detecta duplicados una sola vez
    $dynStop = mp_dynamic_stopwords($filtered, 0.50);
    $det = new MPDuplicateDetector($filtered, $dynStop);
    $result = $det->separate();

    // Guardar sólo lo necesario en la cache
    $_SESSION['dup_cache'][$cache_key] = [
        'generated_at' => time(),
        'dups'  => $result['duplicates'],
        'uniq'  => $result['unique'],
        'count_filtered' => count($filtered)
    ];
}

$dups  = $_SESSION['dup_cache'][$cache_key]['dups'];
$uniq  = $_SESSION['dup_cache'][$cache_key]['uniq'];
$count_filtered = $_SESSION['dup_cache'][$cache_key]['count_filtered'];

// ====== Paginación ======
function paginate_array(array $arr, int $page, int $per_page) {
    $total = count($arr);
    $max_page = max(1, (int)ceil($total / $per_page));
    if ($page > $max_page) $page = $max_page;
    $offset = ($page - 1) * $per_page;
    $slice = array_slice($arr, $offset, $per_page);
    return [$slice, $total, $page, $max_page];
}

if ($tab === 'unique') {
    [$uniq_page, $uniq_total, $page, $max_page] = paginate_array($uniq, $page, $per_page);
} else {
    [$dups_page, $dups_total, $page, $max_page] = paginate_array($dups, $page, $per_page);
}

// ====== UI ======
llxHeader('', 'Detección de duplicados');

// Estilos para pantalla completa con scroll
print '<style>
.fullscreen-container {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: #f8f9fa;
    z-index: 1000;
    overflow: hidden;
}

.fullscreen-header {
    background: #fff;
    border-bottom: 1px solid #dee2e6;
    padding: 15px 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    position: sticky;
    top: 0;
    z-index: 1001;
}

.fullscreen-content {
    height: calc(100vh - 80px);
    overflow-y: auto;
    padding: 20px;
    background: #f8f9fa;
}

.fullscreen-title {
    margin: 0;
    font-size: 24px;
    color: #333;
    display: flex;
    align-items: center;
    gap: 10px;
}

.close-btn {
    position: absolute;
    top: 15px;
    right: 20px;
    background: #dc3545;
    color: white;
    border: none;
    padding: 8px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
}

.close-btn:hover {
    background: #c82333;
}

.search-form-container {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.tabs-container {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.content-tabs {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    min-height: 400px;
}

.stats-bar {
    background: #e9ecef;
    padding: 10px 20px;
    border-radius: 4px;
    margin: 10px 0;
    font-size: 14px;
    color: #495057;
}

.pagination {
    background: white;
    padding: 15px 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin: 20px 0;
    text-align: center;
}

.pagination a {
    display: inline-block;
    padding: 8px 12px;
    margin: 0 2px;
    background: #007bff;
    color: white;
    text-decoration: none;
    border-radius: 4px;
    font-size: 12px;
}

.pagination a:hover {
    background: #0056b3;
}

.pagination span {
    display: inline-block;
    padding: 8px 12px;
    margin: 0 5px;
    font-weight: bold;
    color: #495057;
}

table {
    width: 100%;
    border-collapse: collapse;
    background: white;
}

.liste_titre {
    background: #f8f9fa;
    font-weight: bold;
    border-bottom: 2px solid #dee2e6;
}

.liste_titre td {
    padding: 12px 8px;
    border-bottom: 1px solid #dee2e6;
}

.oddeven td {
    padding: 10px 8px;
    border-bottom: 1px solid #f1f3f4;
}

.oddeven:nth-child(even) {
    background: #f8f9fa;
}

.oddeven:hover {
    background: #e3f2fd;
}

.button {
    display: inline-block;
    padding: 6px 12px;
    background: #007bff;
    color: white;
    text-decoration: none;
    border-radius: 4px;
    font-size: 12px;
    border: none;
    cursor: pointer;
}

.button:hover {
    background: #0056b3;
}

.button-cancel {
    background: #6c757d;
}

.button-cancel:hover {
    background: #545b62;
}

.butAction {
    display: inline-block;
    padding: 6px 12px;
    background: #007bff;
    color: white;
    text-decoration: none;
    border-radius: 4px;
    font-size: 12px;
    margin: 2px;
}

.butAction:hover {
    background: #0056b3;
}

.butActionRefused {
    background: #6c757d;
    cursor: not-allowed;
}

input[type="text"], input[type="number"] {
    width: 100%;
    padding: 8px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 14px;
    box-sizing: border-box;
}

input[type="text"]:focus, input[type="number"]:focus {
    border-color: #007bff;
    outline: none;
    box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
}

.tabsAction {
    display: flex;
    gap: 10px;
    padding: 15px 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.tabsAction a {
    padding: 10px 20px;
    background: #e9ecef;
    color: #495057;
    text-decoration: none;
    border-radius: 4px 4px 0 0;
    font-weight: 500;
    transition: all 0.3s;
}

.tabsAction a:hover {
    background: #dee2e6;
}

.tabsAction .butActionRefused {
    background: #007bff;
    color: white;
}

.tabsAction .butActionRefused:hover {
    background: #0056b3;
}

.content-tabs table {
    margin: 0;
    border-radius: 0;
    box-shadow: none;
}

.content-tabs .pagination {
    margin: 10px 0;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
}

.group-duplicate {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    margin: 10px 0;
    border-radius: 6px;
    padding: 15px;
}

.group-duplicate h4 {
    margin: 0 0 10px 0;
    color: #856404;
    font-size: 16px;
}

.group-duplicate table {
    background: white;
    border-radius: 4px;
    overflow: hidden;
}

.no-data {
    text-align: center;
    padding: 40px 20px;
    color: #6c757d;
    font-size: 16px;
    background: white;
    border-radius: 8px;
    margin: 20px 0;
}

.loading {
    text-align: center;
    padding: 20px;
    color: #007bff;
    font-size: 14px;
}

@media (max-width: 768px) {
    .fullscreen-content {
        padding: 10px;
    }
    
    .search-form-container {
        padding: 15px;
    }
    
    .tabsAction {
        flex-direction: column;
        gap: 5px;
    }
    
    .tabsAction a {
        text-align: center;
    }
    
    table {
        font-size: 12px;
    }
    
    .liste_titre td, .oddeven td {
        padding: 8px 4px;
    }
}
</style>';

// Contenedor de pantalla completa
print '<div class="fullscreen-container">';
print '<div class="fullscreen-header">';
print '<h1 class="fullscreen-title">🔍 Detección de Duplicados - Pantalla Completa</h1>';
print '<button class="close-btn" onclick="window.close()">✕ Cerrar</button>';
print '</div>';

print '<div class="fullscreen-content">';
print '<div class="search-form-container">';

// Formulario
print '<form method="POST" action="'.dol_escape_htmltag($_SERVER["PHP_SELF"]).'">';
print '<table class="noborder centpercent" style="margin-bottom:12px">';
print '<tr class="liste_titre">';
print '<td class="center" style="width:10%">IDs</td>';
print '<td class="center" style="width:12%">EAN</td>';
print '<td class="center" style="width:18%">Referencia</td>';
print '<td class="center" style="width:32%">Nombre / Descripción</td>';
print '<td class="center" style="width:8%">Límite</td>';
print '<td class="center" style="width:8%">Por pág.</td>';
print '<td class="center" style="width:12%">Acciones</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td class="center"><input type="text" name="product_id" value="'.dol_escape_htmltag($product_id_raw).'" class="flat" style="width:95%" placeholder="e.g. 14390,14326"></td>';
print '<td class="center"><input type="text" name="product_ean" value="'.dol_escape_htmltag($product_ean).'" class="flat" style="width:95%" placeholder="EAN"></td>';
print '<td class="center"><input type="text" name="product_ref" value="'.dol_escape_htmltag($product_ref).'" class="flat" style="width:95%" placeholder="REF"></td>';
print '<td class="center"><input type="text" name="product_name" value="'.dol_escape_htmltag($product_name).'" class="flat" style="width:95%" placeholder="Texto en nombre o descripción"></td>';
print '<td class="center"><input type="number" name="limit" value="'.(int)$limit.'" class="flat" style="width:90%"></td>';
print '<td class="center"><input type="number" name="per_page" value="'.(int)$per_page.'" class="flat" style="width:90%"></td>';
print '<td class="center">';
print '<input type="hidden" name="action" value="search">';
print '<button type="submit" class="button">Buscar</button> ';
print '<a class="button button-cancel" href="'.dol_escape_htmltag($_SERVER["PHP_SELF"]).'?clear=1">Limpiar</a>';
print '</td>';
print '</tr>';
print '</table>';
print '</form>';

print '</div>'; // Cerrar search-form-container

// Contenedor de pestañas
print '<div class="tabs-container">';
print '<div class="stats-bar">';
print '📊 Cargados: <strong>'.$count_filtered.'</strong> · Duplicados: <strong>'.count($dups).'</strong> grupos · Únicos: <strong>'.count($uniq).'</strong>';
print '</div>';

print '<div class="tabsAction">';
$base = dol_escape_htmltag($_SERVER["PHP_SELF"]).'?per_page='.$per_page.'&limit='.$limit.'&product_id='.urlencode($product_id_raw).
        '&product_ean='.urlencode($product_ean).'&product_ref='.urlencode($product_ref).'&product_name='.urlencode($product_name);
print '<a class="butAction'.($tab==='unique'?'Refused':'').'" href="'.$base.'&tab=unique&page=1">📦 Únicos ('.count($uniq).')</a> ';
print '<a class="butAction'.($tab==='duplicates'?'Refused':'').'" href="'.$base.'&tab=duplicates&page=1">🔄 Duplicados ('.count($dups).')</a>';
print '</div>';

// Render con paginación
function render_pager($base, $tab, $page, $max_page) {
    if ($max_page <= 1) return;
    print '<div class="pagination">';
    $prev = max(1, $page-1); $next = min($max_page, $page+1);
    print '<a class="button" href="'.$base.'&tab='.$tab.'&page=1">« Primero</a> ';
    print '<a class="button" href="'.$base.'&tab='.$tab.'&page='.$prev.'">‹ Anterior</a> ';
    print '<span>Página <strong>'.$page.'</strong> de <strong>'.$max_page.'</strong></span>';
    print '<a class="button" href="'.$base.'&tab='.$tab.'&page='.$next.'">Siguiente ›</a> ';
    print '<a class="button" href="'.$base.'&tab='.$tab.'&page='.$max_page.'">Último »</a>';
    print '</div>';
}

// Contenido de las pestañas
print '<div class="content-tabs">';
    
if ($tab === 'unique') {
    if (empty($uniq_page)) {
        print '<div class="no-data">📦 No se encontraron productos únicos</div>';
    } else {
        render_pager($base, 'unique', $page, $max_page);
    print '<table class="noborder centpercent">';
        print '<tr class="liste_titre"><td style="width:6%">ID</td><td style="width:16%">Ref</td><td style="width:12%">EAN</td><td>Nombre</td><td style="width:10%">Acciones</td></tr>';
        foreach ($uniq_page as $p) {
        print '<tr class="oddeven">';
            print '<td>'.(int)$p->rowid.'</td>';
            print '<td>'.dol_escape_htmltag($p->ref).'</td>';
            print '<td>'.dol_escape_htmltag($p->barcode).'</td>';
            print '<td>'.dol_escape_htmltag($p->label).'</td>';
            print '<td><a class="button" href="'.DOL_URL_ROOT.'/product/card.php?id='.(int)$p->rowid.'" target="_blank">Abrir</a></td>';
        print '</tr>';
    }
    print '</table>';
        render_pager($base, 'unique', $page, $max_page);
    }
} else {
    if (empty($dups_page)) {
        print '<div class="no-data">🔄 No se encontraron duplicados</div>';
} else {
        render_pager($base, 'duplicates', $page, $max_page);
        $start = ($page - 1) * $per_page;
        foreach ($dups_page as $k => $group) {
            $idx = $start + $k + 1;
            print '<div class="group-duplicate">';
            print '<h4>🔄 Grupo de Duplicados #'.$idx.' ('.count($group).' productos)</h4>';
        print '<table class="noborder centpercent">';
            print '<tr class="liste_titre"><td style="width:6%">ID</td><td style="width:16%">Ref</td><td style="width:12%">EAN</td><td>Nombre</td><td style="width:10%">Acciones</td></tr>';
            foreach ($group as $p) {
            print '<tr class="oddeven">';
                print '<td>'.(int)$p->rowid.'</td>';
                print '<td>'.dol_escape_htmltag($p->ref).'</td>';
                print '<td>'.dol_escape_htmltag($p->barcode).'</td>';
                print '<td>'.dol_escape_htmltag($p->label).'</td>';
                print '<td><a class="button" href="'.DOL_URL_ROOT.'/product/card.php?id='.(int)$p->rowid.'" target="_blank">Abrir</a></td>';
            print '</tr>';
        }
        print '</table>';
        print '</div>';
    }
        render_pager($base, 'duplicates', $page, $max_page);
    }
}

print '</div>'; // Cerrar content-tabs
print '</div>'; // Cerrar tabs-container
print '</div>'; // Cerrar fullscreen-content
print '</div>'; // Cerrar fullscreen-container

llxFooter();
?>
