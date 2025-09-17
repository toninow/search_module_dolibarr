<?php
/* Copyright (C) 2024 - Search Duplicates Module for Dolibarr */

/**
 * \file       module_search_duplicates.php
 * \ingroup    search_duplicates
 * \brief      Description and activation file for module Search Duplicates
 */

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php";
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) die("Include of main fails");

// Load Dolibarr modules
dol_include_once('/core/modules/modSearchDuplicates.class.php');

// Load translation files
$langs->load("search_duplicates@search_duplicates");

// Access control
if (!$user->admin) accessforbidden();

// Parameters
$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');

// Initialize objects
$object = new modSearchDuplicates($db);

// Actions
if ($action == 'setmod') {
    // Activate module
    if ($object->rights->search_duplicates->write) {
        $result = $object->setValue('SEARCH_DUPLICATES_ENABLED', 1);
        if ($result > 0) {
            setEventMessages($langs->trans("ModuleActivated"), null, 'mesgs');
        } else {
            setEventMessages($langs->trans("ErrorActivatingModule"), $object->errors, 'errors');
        }
    }
} elseif ($action == 'unsetmod') {
    // Deactivate module
    if ($object->rights->search_duplicates->write) {
        $result = $object->setValue('SEARCH_DUPLICATES_ENABLED', 0);
        if ($result > 0) {
            setEventMessages($langs->trans("ModuleDeactivated"), null, 'mesgs');
        } else {
            setEventMessages($langs->trans("ErrorDeactivatingModule"), $object->errors, 'errors');
        }
    }
}

// Page header
llxHeader('', $langs->trans("SearchDuplicates"));

// Subheader
$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($langs->trans("SearchDuplicates"), $linkback, 'title_setup');

// Configuration header
print '<div class="fichecenter">';
print '<div class="fichehalfleft">';

// Module status
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th>'.$langs->trans("Parameter").'</th>';
print '<th>'.$langs->trans("Value").'</th>';
print '<th>'.$langs->trans("Action").'</th>';
print '</tr>';

// Module enabled/disabled
print '<tr class="oddeven">';
print '<td>'.$langs->trans("ModuleStatus").'</td>';
print '<td>';
if ($conf->global->SEARCH_DUPLICATES_ENABLED) {
    print '<span class="status4">'.$langs->trans("Enabled").'</span>';
} else {
    print '<span class="status8">'.$langs->trans("Disabled").'</span>';
}
print '</td>';
print '<td>';
if ($conf->global->SEARCH_DUPLICATES_ENABLED) {
    print '<a href="'.$_SERVER['PHP_SELF'].'?action=unsetmod">'.$langs->trans("Disable").'</a>';
} else {
    print '<a href="'.$_SERVER['PHP_SELF'].'?action=setmod">'.$langs->trans("Enable").'</a>';
}
print '</td>';
print '</tr>';

print '</table>';

print '</div>';
print '<div class="fichehalfright">';

// Module information
print '<div class="info">';
print '<h3>'.$langs->trans("ModuleInformation").'</h3>';
print '<p><strong>'.$langs->trans("ModuleName").':</strong> Search Duplicates v1.18</p>';
print '<p><strong>'.$langs->trans("Author").':</strong> SearchDuplicates Solutions</p>';
print '<p><strong>'.$langs->trans("License").':</strong> Commercial</p>';
print '<p><strong>'.$langs->trans("Website").':</strong> <a href="https://searchduplicates.com" target="_blank">searchduplicates.com</a></p>';
print '<p><strong>'.$langs->trans("Support").':</strong> <a href="mailto:support@searchduplicates.com">support@searchduplicates.com</a></p>';
print '</div>';

print '</div>';
print '</div>';

// Access to module
if ($conf->global->SEARCH_DUPLICATES_ENABLED) {
    print '<div class="fichecenter">';
    print '<h3>'.$langs->trans("AccessToModule").'</h3>';
    print '<p><a href="index.php" class="button">'.$langs->trans("AccessToSearchDuplicates").'</a></p>';
    print '</div>';
}

// Commercial information
print '<div class="fichecenter">';
print '<div class="info">';
print '<h3>'.$langs->trans("CommercialInformation").'</h3>';
print '<p><strong>'.$langs->trans("Version").':</strong> 1.18 - Professional Edition</p>';
print '<p><strong>'.$langs->trans("Features").':</strong> AI-powered duplicate detection, Advanced analytics, Stock management, API integration</p>';
print '<p><strong>'.$langs->trans("Support").':</strong> 12 months included, Priority support available</p>';
print '<p><strong>'.$langs->trans("Updates").':</strong> Automatic updates included</p>';
print '<p><a href="https://searchduplicates.com/pricing" target="_blank" class="button">'.$langs->trans("ViewPricing").'</a></p>';
print '</div>';
print '</div>';

// Page footer
llxFooter();
?>
