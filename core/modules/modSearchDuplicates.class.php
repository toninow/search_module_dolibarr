<?php
/* Copyright (C) 2024 - Search Duplicates Module for Dolibarr */

// Load Dolibarr environment
if (!defined('DOL_DOCUMENT_ROOT')) {
    $res = 0;
    if (!$res && file_exists("../../../../main.inc.php")) {
        $res = @include "../../../../main.inc.php";
    }
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

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

class modSearchDuplicates extends DolibarrModules
{
    public function __construct($db)
    {
        parent::__construct($db);
        
        $this->numero = 500000;
        $this->rights_class = 'search_duplicates';
        $this->family = "products";
        $this->module_position = '90';
        $this->name = 'searchduplicates';
        $this->description = "Sistema avanzado de detección de productos duplicados";
        $this->descriptionlong = "Sistema avanzado de detección de productos duplicados desarrollado por Antonio Benalcázar Musical Princesa";
        $this->editor_name = 'Antonio Benalcázar Musical Princesa';
        $this->version = '1.22.0';
        $this->const_name = 'MAIN_MODULE_SEARCHDUPLICATES';
        $this->picto='search';
        
        $this->depends = array();
        $this->requiredby = array();
        $this->conflictwith = array();
        $this->langfiles = array("search_duplicates@search_duplicates");
        $this->phpmin = array(7, 0);
        $this->need_dolibarr_version = array(15, 0);
        $this->menu = array();
        $this->config_page_url = array("setup.php@searchduplicates");
        
        // Definir partes del módulo incluyendo CSS personalizado
        $this->module_parts = array(
            'css' => array('/custom/searchduplicates/css/searchduplicates.css')
        );
        
        $this->const = array();
        $this->boxes = array();
        $this->export_code = array();
        $r = 0;
        
        $this->menu[$r] = array(
            'fk_menu' => 0,
            'type' => 'top',
            'titre' => 'Productos Duplicados',
            'mainmenu' => 'search_duplicates',
            'leftmenu' => '0',
            'url' => '/custom/searchduplicates/index.php',
            'langs' => 'search_duplicates@search_duplicates',
            'position' => 105,
            'enabled' => '1',
            'perms' => '1',
            'target' => '',
            'user' => 0
        );
        $r++;
        
        $this->menu[$r] = array(
            'fk_menu' => 'fk_mainmenu=search_duplicates',
            'type' => 'left',
            'titre' => 'Productos Duplicados',
            'mainmenu' => 'search_duplicates',
            'leftmenu' => 'search_duplicates',
            'url' => '/custom/searchduplicates/index.php',
            'langs' => 'search_duplicates@search_duplicates',
            'position' => 1000,
            'enabled' => '1',
            'perms' => '1',
            'target' => '',
            'user' => 0,
            'usertype' => 0,
            'entity' => 0
        );
        $r++;
        
        // Rights
        $this->rights = array();
        $r = 0;
        
        $this->rights[$r][0] = $this->numero + $r;
        $this->rights[$r][1] = 'Leer objetos de búsqueda avanzada';
        $this->rights[$r][3] = 0; // 0 = todos los usuarios
        $this->rights[$r][4] = 'read';
        $r++;
        
        $this->rights[$r][0] = $this->numero + $r;
        $this->rights[$r][1] = 'Crear/Actualizar objetos de búsqueda avanzada';
        $this->rights[$r][3] = 0; // 0 = todos los usuarios
        $this->rights[$r][4] = 'write';
        $r++;
        
        $this->rights[$r][0] = $this->numero + $r;
        $this->rights[$r][1] = 'Eliminar objetos de búsqueda avanzada';
        $this->rights[$r][3] = 0; // 0 = todos los usuarios
        $this->rights[$r][4] = 'delete';
        $r++;
    }
    
    
    public function remove($options = '')
    {
        // Desactivar constante
        $sql = "UPDATE llx_const SET value = 0 WHERE name = 'MAIN_MODULE_SEARCHDUPLICATES'";
        $this->db->query($sql);
        
        // Eliminar menús
        $sql = "DELETE FROM llx_menu WHERE mainmenu = 'search_duplicates'";
        $this->db->query($sql);
        
        // Eliminar permisos
        $sql = "DELETE FROM llx_rights_def WHERE module = 'search_duplicates'";
        $this->db->query($sql);
        
        // Eliminar constantes del módulo
        $sql = "DELETE FROM llx_const WHERE name LIKE 'MAIN_MODULE_SEARCHDUPLICATES%'";
        $this->db->query($sql);
        
        // Eliminar archivos del módulo si se solicita
        if (isset($options['delete_files']) && $options['delete_files'] == 1) {
            $this->deleteModuleFiles();
        }
        
        return 1;
    }
    
    /**
     * Delete module files from filesystem
     *
     * @return int 1 if success, 0 if failure
     */
    public function deleteModuleFiles()
    {
        $module_path = DOL_DOCUMENT_ROOT . '/custom/searchduplicates/';
        
        if (is_dir($module_path)) {
            // Función recursiva para eliminar directorio
            function deleteDirectory($dir) {
                if (!is_dir($dir)) {
                    return false;
                }
                
                $files = array_diff(scandir($dir), array('.', '..'));
                foreach ($files as $file) {
                    $path = $dir . '/' . $file;
                    if (is_dir($path)) {
                        deleteDirectory($path);
                    } else {
                        unlink($path);
                    }
                }
                return rmdir($dir);
            }
            
            return deleteDirectory($module_path);
        }
        
        return 1;
    }
    
    /**
     * Get URL to access the module
     *
     * @return string URL to access the module
     */
    public function getRightUrl()
    {
        return '/custom/searchduplicates/index.php';
    }
    
    /**
     * Get URL to access the module (alternative method)
     *
     * @return string URL to access the module
     */
    public function getUrl()
    {
        return '/custom/searchduplicates/index.php';
    }
    
    /**
     * Get URL to access the admin configuration
     *
     * @return string URL to access the admin configuration
     */
    public function getAdminUrl()
    {
        return '/custom/searchduplicates/index.php';
    }
    
    /**
     * Include menu
     *
     * @return void
     */
    public function includeMenu()
    {
        include_once DOL_DOCUMENT_ROOT . '/custom/menu_search_duplicates.php';
    }
}