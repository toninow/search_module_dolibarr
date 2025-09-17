<?php
/* Copyright (C) 2024 - Search Duplicates Module for Dolibarr
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
 * \file       lib/search_duplicates.lib.php
 * \ingroup    search_duplicates
 * \brief      Library files with common functions for Search Duplicates
 */

/**
 * Prepare array of tabs for SearchDuplicates
 *
 * @param SearchDuplicates $object Object related to tabs
 * @return array Array of tabs
 */
function search_duplicates_prepare_head(SearchDuplicates $object)
{
    global $langs, $conf;

    $langs->load("search_duplicates@search_duplicates");

    $h = 0;
    $head = array();

    $head[$h][0] = dol_buildpath("/search_duplicates/card.php", 1).'?id='.$object->id;
    $head[$h][1] = $langs->trans("Card");
    $head[$h][2] = 'card';
    $h++;

    if (isset($object->fields['note_public']) || isset($object->fields['note_private'])) {
        $nbNote = 0;
        if (!empty($object->note_private)) $nbNote++;
        if (!empty($object->note_public)) $nbNote++;
        $head[$h][0] = dol_buildpath("/search_duplicates/note.php", 1).'?id='.$object->id;
        $head[$h][1] = $langs->trans('Notes');
        if ($nbNote > 0) $head[$h][1].= '<span class="badge marginleftonlyshort">'.$nbNote.'</span>';
        $head[$h][2] = 'note';
        $h++;
    }

    $head[$h][0] = dol_buildpath("/search_duplicates/document.php", 1).'?id='.$object->id;
    $head[$h][1] = $langs->trans("Documents");
    $head[$h][2] = 'document';
    $h++;

    $head[$h][0] = dol_buildpath("/search_duplicates/info.php", 1).'?id='.$object->id;
    $head[$h][1] = $langs->trans("Info");
    $head[$h][2] = 'info';
    $h++;

    return $head;
}

/**
 * Show header for SearchDuplicates
 *
 * @param SearchDuplicates $object Object
 * @param string $head Array of tabs
 * @param int $showaddress 0=no, 1=yes
 * @return void
 */
function search_duplicates_print_head(SearchDuplicates $object, $head, $showaddress = 0)
{
    print dol_get_fiche_head($head, 'card', $object->ref, -1, $object->picto);
}

/**
 * Show footer for SearchDuplicates
 *
 * @param SearchDuplicates $object Object
 * @return void
 */
function search_duplicates_print_foot(SearchDuplicates $object)
{
    print dol_get_fiche_end();
}

/**
 * Get list of duplicate groups
 *
 * @param DoliDB $db Database handler
 * @param int $limit Limit
 * @return array Array of duplicate groups
 */
function search_duplicates_get_groups($db, $limit = 50)
{
    $sql = "SELECT g.*, p.label as product_name, p.ref as product_ref
            FROM " . MAIN_DB_PREFIX . "search_duplicates_groups g
            LEFT JOIN " . MAIN_DB_PREFIX . "product p ON g.original_product_id = p.rowid
            WHERE g.entity IN (" . getEntity('search_duplicates') . ")
            ORDER BY g.date_creation DESC
            LIMIT " . (int)$limit;

    $resql = $db->query($sql);
    if (!$resql) {
        dol_syslog("Error getting duplicate groups: " . $db->lasterror(), LOG_ERR);
        return array();
    }

    $groups = array();
    $num = $db->num_rows($resql);
    for ($i = 0; $i < $num; $i++) {
        $obj = $db->fetch_object($resql);
        $groups[] = array(
            'id' => $obj->rowid,
            'group_name' => $obj->group_name,
            'original_product_id' => $obj->original_product_id,
            'product_name' => $obj->product_name,
            'product_ref' => $obj->product_ref,
            'status' => $obj->status,
            'notes' => $obj->notes,
            'created_by' => $obj->created_by,
            'date_creation' => $obj->date_creation
        );
    }

    return $groups;
}

/**
 * Get products in a duplicate group
 *
 * @param DoliDB $db Database handler
 * @param int $groupId Group ID
 * @return array Array of products in group
 */
function search_duplicates_get_group_products($db, $groupId)
{
    $sql = "SELECT gp.*, p.label as product_name, p.ref as product_ref, p.price
            FROM " . MAIN_DB_PREFIX . "search_duplicates_group_products gp
            LEFT JOIN " . MAIN_DB_PREFIX . "product p ON gp.product_id = p.rowid
            WHERE gp.group_id = " . (int)$groupId . "
            AND gp.entity IN (" . getEntity('search_duplicates') . ")
            ORDER BY gp.is_original DESC, gp.similarity_percentage DESC";

    $resql = $db->query($sql);
    if (!$resql) {
        dol_syslog("Error getting group products: " . $db->lasterror(), LOG_ERR);
        return array();
    }

    $products = array();
    $num = $db->num_rows($resql);
    for ($i = 0; $i < $num; $i++) {
        $obj = $db->fetch_object($resql);
        $products[] = array(
            'id' => $obj->rowid,
            'product_id' => $obj->product_id,
            'product_name' => $obj->product_name,
            'product_ref' => $obj->product_ref,
            'price' => $obj->price,
            'is_original' => $obj->is_original,
            'similarity_percentage' => $obj->similarity_percentage
        );
    }

    return $products;
}

/**
 * Create a new duplicate group
 *
 * @param DoliDB $db Database handler
 * @param array $groupData Group data
 * @return int Group ID or 0 on error
 */
function search_duplicates_create_group($db, $groupData)
{
    global $user, $conf;

    $db->begin();

    try {
        // Create main group
        $sql = "INSERT INTO " . MAIN_DB_PREFIX . "search_duplicates_groups 
                (group_name, original_product_id, created_by, status, notes, entity) 
                VALUES (?, ?, ?, 'active', ?, ?)";
        
        $groupName = $groupData['group_name'] ?? 'Grupo de duplicados';
        $originalId = (int)$groupData['original_product_id'];
        $notes = $groupData['notes'] ?? "Grupo creado el " . date('Y-m-d H:i:s');
        
        $resql = $db->query($sql, [
            $groupName, 
            $originalId, 
            $user->id, 
            $notes,
            $conf->entity
        ]);
        
        if (!$resql) {
            throw new Exception("Error creating group: " . $db->lasterror());
        }
        
        $groupId = $db->last_insert_id(MAIN_DB_PREFIX . "search_duplicates_groups");

        // Add original product to group
        $sql = "INSERT INTO " . MAIN_DB_PREFIX . "search_duplicates_group_products 
                (group_id, product_id, is_original, similarity_percentage, entity) 
                VALUES (?, ?, 1, 100.00, ?)";
        
        $resql = $db->query($sql, [$groupId, $originalId, $conf->entity]);
        if (!$resql) {
            throw new Exception("Error adding original product: " . $db->lasterror());
        }

        // Add duplicates to group
        if (!empty($groupData['duplicates'])) {
            $sql = "INSERT INTO " . MAIN_DB_PREFIX . "search_duplicates_group_products 
                    (group_id, product_id, is_original, similarity_percentage, entity) 
                    VALUES (?, ?, 0, ?, ?)";
            
            foreach ($groupData['duplicates'] as $duplicate) {
                $duplicateId = (int)$duplicate['product_id'];
                $similarity = $duplicate['similarity_percentage'] ?? 85.0;
                $resql = $db->query($sql, [$groupId, $duplicateId, $similarity, $conf->entity]);
                if (!$resql) {
                    throw new Exception("Error adding duplicate: " . $db->lasterror());
                }
            }
        }

        // Log action
        $sql = "INSERT INTO " . MAIN_DB_PREFIX . "search_duplicates_actions 
                (group_id, action_type, product_id, performed_by, notes, entity) 
                VALUES (?, 'created', ?, ?, ?, ?)";
        
        $actionNotes = "Grupo creado con " . count($groupData['duplicates'] ?? []) . " duplicados";
        $resql = $db->query($sql, [
            $groupId, 
            $originalId, 
            $user->id, 
            $actionNotes,
            $conf->entity
        ]);
        
        if (!$resql) {
            throw new Exception("Error logging action: " . $db->lasterror());
        }

        $db->commit();
        return $groupId;

    } catch (Exception $e) {
        $db->rollback();
        dol_syslog("Error creating duplicate group: " . $e->getMessage(), LOG_ERR);
        return 0;
    }
}
