<?php
/* Copyright (C) 2003-2023 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2004-2012 Regis Houssin        <regis.houssin@inodbox.com>
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
 *  \file       main.inc.php
 *  \brief      Main file for Dolibarr environment
 */

// Define Dolibarr environment
if (!defined('DOL_DOCUMENT_ROOT')) {
    define('DOL_DOCUMENT_ROOT', __DIR__);
}

// Define constants
if (!defined('DOL_VERSION')) {
    define('DOL_VERSION', '15.0.2');
}

// Include core files (simplified for custom modules)
if (file_exists(DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php')) {
    require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection (simplified for custom modules)
$db = null;
if (file_exists(DOL_DOCUMENT_ROOT.'/conf/conf.php')) {
    include_once DOL_DOCUMENT_ROOT.'/conf/conf.php';
    if (isset($dolibarr_main_db_host)) {
        try {
            $db = new PDO("mysql:host=".$dolibarr_main_db_host.";dbname=".$dolibarr_main_db_name, $dolibarr_main_db_user, $dolibarr_main_db_pass);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Error de conexión a la base de datos: " . $e->getMessage());
        }
    }
}

// Configuration object
$conf = new stdClass();
$conf->entity = 1;

// Language object
$langs = new stdClass();
$langs->defaultlang = 'es_ES';

// User object
$user = new stdClass();
$user->id = 1;
$user->login = 'admin';

// Function to get POST parameters
function GETPOST($param, $type = 'aZ09') {
    return isset($_GET[$param]) ? $_GET[$param] : (isset($_POST[$param]) ? $_POST[$param] : '');
}

// Function to escape HTML
function dol_escape_htmltag($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Function to escape JavaScript
function dol_escape_js($string) {
    return addslashes($string);
}

// Function to print date
function dol_print_date($date, $format = 'd/m/Y') {
    if (empty($date)) return '';
    return date($format, strtotime($date));
}

// Function to get Dolibarr URL
function dol_buildpath($path, $type = 'http') {
    return $path;
}

// Define DOL_URL_ROOT
if (!defined('DOL_URL_ROOT')) {
    define('DOL_URL_ROOT', '/dolibarr');
}

// Define MAIN_DB_PREFIX
if (!defined('MAIN_DB_PREFIX')) {
    define('MAIN_DB_PREFIX', 'llx_');
}
