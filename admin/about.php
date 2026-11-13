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
 * \file    htdocs/custom/news/admin/about.php
 * \ingroup news
 * \brief   About page of module News
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

global $db, $langs, $user;

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
dol_include_once('/news/lib/news.lib.php');
dol_include_once('/news/core/modules/modNews.class.php');

$langs->loadLangs(array("admin", "news@news"));

if (!$user->admin) {
	accessforbidden();
}

llxHeader('', $langs->trans("NewsAbout"), '', '', 0, 0, '', '', '', 'mod-news page-admin-about');

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans("NewsSetup"), $linkback, 'title_setup');

$head = newsAdminPrepareHead();
print dol_get_fiche_head($head, 'about', '', -1, '');

$mod = new modNews($db);
print $mod->getDescLong();

print dol_get_fiche_end();

llxFooter();
$db->close();
