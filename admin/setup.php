<?php
/* Copyright (C) 2026 Kim Wittkowski <kim@wittkowski-it.de>
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
 * \file    htdocs/custom/news/admin/setup.php
 * \ingroup news
 * \brief   Setup page of module News
 */

$res = 0;
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

global $db, $langs, $user, $conf;

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('/news/lib/news.lib.php');

$langs->loadLangs(array("admin", "news@news"));

if (!$user->admin) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');

if ($action == 'update') {
	$popup = GETPOSTINT('NEWS_POPUP_ENABLED');
	if (dolibarr_set_const($db, 'NEWS_POPUP_ENABLED', $popup ? '1' : '0', 'chaine', 0, '', $conf->entity) > 0) {
		setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
	} else {
		setEventMessages($langs->trans("Error"), null, 'errors');
	}
}

$form = new Form($db);

llxHeader('', $langs->trans("NewsSetup"), '', '', 0, 0, '', '', '', 'mod-news page-admin-setup');

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans("NewsSetup"), $linkback, 'title_setup');

$head = newsAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', '', -1, '');

// Requirements first: without them the popup is silently never shown
print '<span class="opacitymedium">'.$langs->trans("NewsRequirements").'</span><br>';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans("NewsRequirement").'</td><td class="center" width="120">'.$langs->trans("Status").'</td><td>'.$langs->trans("NewsReqHint").'</td></tr>';
foreach (newsCheckRequirements() as $req) {
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans($req['key']).'</td>';
	print '<td class="center">';
	if ($req['ok']) {
		print img_picto('', 'tick', 'class="paddingright"').$langs->trans("NewsReqOk");
	} else {
		print '<span class="badge badge-status8 badge-status">'.$langs->trans("NewsReqMissing").'</span>';
	}
	print '</td>';
	print '<td class="opacitymedium">'.$langs->trans($req['key'].'Hint').'</td>';
	print '</tr>';
}
print '</table>';
print '</div>';
print '<br>';

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update">';

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans("Parameter").'</td><td width="220">'.$langs->trans("Value").'</td></tr>';
print '<tr class="oddeven">';
print '<td>'.$form->textwithpicto($langs->trans("NEWS_POPUP_ENABLED"), $langs->trans("NEWS_POPUP_ENABLEDTooltip")).'</td>';
print '<td>'.$form->selectyesno('NEWS_POPUP_ENABLED', getDolGlobalInt('NEWS_POPUP_ENABLED', 1), 1).'</td>';
print '</tr>';
print '</table>';
print '</div>';

print '<br><div class="center"><input type="submit" class="button button-save" value="'.$langs->trans("Save").'"></div>';
print '</form>';

print dol_get_fiche_end();

llxFooter();
$db->close();
