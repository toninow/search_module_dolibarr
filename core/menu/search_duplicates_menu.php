<?php
/* Copyright (C) 2024 - Search Duplicates Module for Dolibarr */

// Load Dolibarr environment
if (!defined('DOL_DOCUMENT_ROOT')) {
    $res = 0;
    if (!$res && file_exists("../../../main.inc.php")) {
        $res = @include "../../../main.inc.php";
    }
    if (!$res && file_exists("../../main.inc.php")) {
        $res = @include "../../main.inc.php";
    }
    if (!$res && file_exists("../main.inc.php")) {
        $res = @include "../main.inc.php";
    }
    if (!$res && file_exists("main.inc.php")) {
        $res = @include "main.inc.php";
    }
}

// Check if module is enabled
if ($conf->global->MAIN_MODULE_SEARCHDUPLICATES) {
    // Add menu entry to main menu
    $menu->add(DOL_URL_ROOT.'/custom/searchduplicates/index.php', 'SearchDuplicates', 0, 1, '', 'search_duplicates@search_duplicates');
}
?>
