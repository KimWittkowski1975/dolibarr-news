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
 * \file    htdocs/custom/news/list.php
 * \ingroup news
 * \brief   Lists: received news, sent news, all news (permission readall)
 */

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

dol_include_once('/news/class/news.class.php');
dol_include_once('/news/lib/news.lib.php');

global $db, $langs, $user, $conf;

$langs->loadLangs(array('news@news', 'users'));

if (!isModEnabled('news') || $user->socid > 0 || !$user->hasRight('news', 'read')) {
	accessforbidden();
}

$mode = GETPOST('mode', 'aZ09');
if (!in_array($mode, array('received', 'sent', 'all'))) {
	$mode = 'received';
}
if ($mode == 'sent' && !$user->hasRight('news', 'write')) {
	$mode = 'received';
}
if ($mode == 'all' && !newsCanSeeAll($user)) {
	accessforbidden();
}

$search_title = GETPOST('search_title', 'alphanohtml');
$search_unread = GETPOSTINT('search_unread');
if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
	$search_title = '';
	$search_unread = 0;
}

$limit = GETPOSTINT('limit') ? GETPOSTINT('limit') : $conf->liste_limit;
$page = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT('page');
if ($page < 0) {
	$page = 0;
}
$offset = $limit * $page;

/*
 * Query
 */

$p = $db->prefix();

if ($mode == 'received') {
	$sql = "SELECT n.rowid, n.title, n.important, n.status, n.datec, n.fk_user_author, r.date_read,";
	$sql .= " u.firstname, u.lastname, u.login";
	$sql .= " FROM ".$p."news_recipient as r";
	$sql .= " INNER JOIN ".$p."news as n ON n.rowid = r.fk_news";
	$sql .= " LEFT JOIN ".$p."user as u ON u.rowid = n.fk_user_author";
	$sql .= " WHERE r.fk_user = ".((int) $user->id)." AND n.status = ".News::STATUS_SENT;
	if ($search_unread) {
		$sql .= " AND r.date_read IS NULL";
	}
} else {
	$sql = "SELECT n.rowid, n.title, n.important, n.status, n.datec, n.fk_user_author, n.to_all,";
	$sql .= " u.firstname, u.lastname, u.login,";
	$sql .= " (SELECT COUNT(r1.rowid) FROM ".$p."news_recipient as r1 WHERE r1.fk_news = n.rowid) as nbrecipients,";
	$sql .= " (SELECT COUNT(r2.rowid) FROM ".$p."news_recipient as r2 WHERE r2.fk_news = n.rowid AND r2.date_read IS NOT NULL) as nbread";
	$sql .= " FROM ".$p."news as n";
	$sql .= " LEFT JOIN ".$p."user as u ON u.rowid = n.fk_user_author";
	$sql .= " WHERE 1 = 1";
	if ($mode == 'sent') {
		$sql .= " AND n.fk_user_author = ".((int) $user->id);
	}
}
if ($search_title !== '') {
	$sql .= natural_search('n.title', $search_title);
}

// Count
$nbtotalofrecords = 0;
$resql = $db->query("SELECT COUNT(*) as nb FROM (".$sql.") as tmp");
if ($resql && ($obj = $db->fetch_object($resql))) {
	$nbtotalofrecords = (int) $obj->nb;
}

if ($mode == 'received') {
	// Unread first, important unread on top, then newest
	$sql .= " ORDER BY (r.date_read IS NULL) DESC, (r.date_read IS NULL AND n.important = 1) DESC, n.datec DESC";
} else {
	$sql .= " ORDER BY n.datec DESC";
}
$sql .= $db->plimit($limit + 1, $offset);

$resql = $db->query($sql);
if (!$resql) {
	dol_print_error($db);
	exit;
}
$num = $db->num_rows($resql);

/*
 * View
 */

$titles = array('received' => 'NewsMenuReceived', 'sent' => 'NewsMenuSent', 'all' => 'NewsMenuAll');
$title = $langs->trans($titles[$mode]);

llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-news page-list');

$param = '&mode='.urlencode($mode);
if ($search_title !== '') {
	$param .= '&search_title='.urlencode($search_title);
}
if ($search_unread) {
	$param .= '&search_unread=1';
}
if ($limit != $conf->liste_limit) {
	$param .= '&limit='.((int) $limit);
}

$newbutton = '';
if ($user->hasRight('news', 'write')) {
	$newbutton = dolGetButtonTitle($langs->trans('NewsCreate'), '', 'fa fa-plus-circle', dol_buildpath('/news/card.php', 1).'?action=create');
}

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="mode" value="'.dol_escape_htmltag($mode).'">';

print_barre_liste($title, $page, $_SERVER['PHP_SELF'], $param, '', '', '', $num, $nbtotalofrecords, 'fa-newspaper', 0, $newbutton, '', $limit, 0, 0, 1);

$ncols = ($mode == 'received') ? 4 : ($mode == 'all' ? 5 : 4);

print '<div class="div-table-responsive">';
print '<table class="tagtable nobottomiftotal liste">';

// Filters
print '<tr class="liste_titre_filter">';
print '<td class="liste_titre"></td>';
print '<td class="liste_titre"><input type="text" class="flat maxwidth200" name="search_title" value="'.dol_escape_htmltag($search_title).'"></td>';
if ($mode == 'received') {
	print '<td class="liste_titre"></td>';
	print '<td class="liste_titre center"><label><input type="checkbox" name="search_unread" value="1"'.($search_unread ? ' checked' : '').'> '.$langs->trans('NewsOnlyUnread').'</label></td>';
} else {
	if ($mode == 'all') {
		print '<td class="liste_titre"></td>';
	}
	print '<td class="liste_titre"></td>';
	print '<td class="liste_titre"></td>';
}
$form = new Form($db);
print '<td class="liste_titre right">'.$form->showFilterButtons().'</td>';
print '</tr>';

// Titles
print '<tr class="liste_titre">';
print '<td class="liste_titre">'.$langs->trans('Date').'</td>';
print '<td class="liste_titre">'.$langs->trans('NewsTitle').'</td>';
if ($mode == 'received') {
	print '<td class="liste_titre">'.$langs->trans('NewsFrom').'</td>';
	print '<td class="liste_titre center">'.$langs->trans('NewsReadOn').'</td>';
} else {
	if ($mode == 'all') {
		print '<td class="liste_titre">'.$langs->trans('NewsFrom').'</td>';
	}
	print '<td class="liste_titre center">'.$langs->trans('NewsReadCount').'</td>';
	print '<td class="liste_titre center">'.$langs->trans('Status').'</td>';
}
print '<td class="liste_titre"></td>';
print '</tr>';

$i = 0;
while ($i < min($num, $limit)) {
	$obj = $db->fetch_object($resql);
	$i++;

	$url = dol_buildpath('/news/card.php', 1).'?id='.((int) $obj->rowid);
	$unread = ($mode == 'received' && empty($obj->date_read));
	$author = trim($obj->firstname.' '.$obj->lastname);
	if ($author === '') {
		$author = (string) $obj->login;
	}

	print '<tr class="oddeven'.($unread ? ' news-unread' : '').'">';
	print '<td class="nowraponall">'.dol_print_date($db->jdate($obj->datec), 'dayhour', 'tzuserrel').'</td>';
	print '<td class="tdoverflowmax400">';
	if (!empty($obj->important)) {
		print '<span class="fa fa-exclamation-triangle news-important paddingright" title="'.dol_escape_htmltag($langs->trans('NewsImportant')).'"></span>';
	}
	print '<a href="'.$url.'">'.dol_escape_htmltag($obj->title).'</a>';
	print '</td>';

	if ($mode == 'received') {
		print '<td class="tdoverflowmax150">'.dol_escape_htmltag($author).'</td>';
		print '<td class="center nowraponall">';
		if ($unread) {
			print '<span class="badge badge-status1 badge-status">'.$langs->trans('NewsUnread').'</span>';
		} else {
			print dol_print_date($db->jdate($obj->date_read), 'dayhour', 'tzuserrel');
		}
		print '</td>';
	} else {
		if ($mode == 'all') {
			print '<td class="tdoverflowmax150">'.dol_escape_htmltag($author).'</td>';
		}
		$nbr = (int) $obj->nbrecipients;
		$nbread = (int) $obj->nbread;
		print '<td class="center nowraponall"><a href="'.$url.'">'.$nbread.' / '.$nbr.'</a>';
		if (!empty($obj->to_all)) {
			print ' <span class="opacitymedium">('.$langs->trans('NewsToAllShort').')</span>';
		}
		print '</td>';
		print '<td class="center">';
		if ((int) $obj->status == News::STATUS_WITHDRAWN) {
			print '<span class="badge badge-status8 badge-status">'.$langs->trans('NewsStatusWithdrawn').'</span>';
		} else {
			print '<span class="badge badge-status4 badge-status">'.$langs->trans('NewsStatusSent').'</span>';
		}
		print '</td>';
	}
	print '<td></td>';
	print '</tr>';
}

if ($num == 0) {
	print '<tr class="oddeven"><td colspan="'.($ncols + 1).'"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
}

print '</table>';
print '</div>';
print '</form>';

llxFooter();
$db->close();
