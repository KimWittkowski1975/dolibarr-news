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
 * \file    htdocs/custom/news/card.php
 * \ingroup news
 * \brief   Create a news item, or show one (and mark it read for the recipient)
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

require_once DOL_DOCUMENT_ROOT.'/core/class/doleditor.class.php';
dol_include_once('/news/class/news.class.php');
dol_include_once('/news/lib/news.lib.php');

global $db, $langs, $user, $conf;

$langs->loadLangs(array('news@news', 'users'));

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');

if (!isModEnabled('news') || $user->socid > 0 || !$user->hasRight('news', 'read')) {
	accessforbidden();
}

$object = new News($db);
if ($id > 0) {
	$res = $object->fetch($id);
	if ($res <= 0) {
		accessforbidden($langs->trans('NewsErrorNotFound'));
	}
	if (!$object->canRead($user)) {
		accessforbidden();
	}
}

$canwrite = $user->hasRight('news', 'write');
$cansendall = $user->hasRight('news', 'sendall');
$candelete = $user->hasRight('news', 'delete');
$canwithdraw = ($object->id > 0 && $object->status == News::STATUS_SENT
	&& ((int) $object->fk_user_author === (int) $user->id || newsCanSeeAll($user)));

/*
 * Actions
 */

if ($action == 'add' && $canwrite) {
	$object->title = GETPOST('title', 'alphanohtml');
	$object->content = GETPOST('content', 'restricthtml');
	$object->important = GETPOSTINT('important') ? 1 : 0;

	$userids = array();
	$groupids = array();
	foreach ((array) GETPOST('recipients', 'array:aZ09') as $key) {
		if (preg_match('/^u_(\d+)$/', $key, $m)) {
			$userids[] = (int) $m[1];
		} elseif (preg_match('/^g_(\d+)$/', $key, $m)) {
			$groupids[] = (int) $m[1];
		}
	}
	// Server-side check: the checkbox is hidden without the permission, but never trust the browser
	$toall = ($cansendall && GETPOSTINT('toall'));

	$res = $object->send($user, $userids, $groupids, $toall);
	if ($res > 0) {
		setEventMessages($langs->trans('NewsSent', count($object->fetchRecipients())), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$res);
		exit;
	}
	setEventMessages($langs->trans($object->error ? $object->error : 'Error'), null, 'errors');
	$action = 'create';
}

if ($action == 'confirm_withdraw' && $confirm == 'yes' && $canwithdraw) {
	if ($object->withdraw($user) > 0) {
		setEventMessages($langs->trans('NewsWithdrawn'), null, 'mesgs');
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.$object->id);
		exit;
	}
	setEventMessages($object->error, null, 'errors');
}

if ($action == 'confirm_delete' && $confirm == 'yes' && $candelete && $object->id > 0) {
	if ($object->delete($user) > 0) {
		setEventMessages($langs->trans('NewsDeleted'), null, 'mesgs');
		header('Location: '.dol_buildpath('/news/list.php', 1).'?mode='.(newsCanSeeAll($user) ? 'all' : 'sent'));
		exit;
	}
	setEventMessages($object->error, null, 'errors');
}

// Opening the news is what counts as "read"
if ($object->id > 0 && empty($action) && $object->status == News::STATUS_SENT) {
	$object->markRead($user->id);
}

/*
 * View
 */

$form = new Form($db);

if ($action == 'create') {
	if (!$canwrite) {
		accessforbidden();
	}

	llxHeader('', $langs->trans('NewsCreate'), '', '', 0, 0, '', '', '', 'mod-news page-card');
	print load_fiche_titre($langs->trans('NewsCreate'), '', 'fa-newspaper');

	$choices = array();
	foreach ($object->getSelectableGroups() as $gid => $g) {
		$choices['g_'.$gid] = array('id' => 'g_'.$gid, 'label' => $g['name'].' ('.$g['nb'].')', 'picto' => 'group');
	}
	foreach ($object->getSelectableUsers() as $uid => $name) {
		if ($uid == $user->id) {
			continue;
		}
		$choices['u_'.$uid] = array('id' => 'u_'.$uid, 'label' => $name, 'picto' => 'user');
	}
	$selected = (array) GETPOST('recipients', 'array:aZ09');

	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add">';

	print dol_get_fiche_head(array(), '');
	print '<table class="border centpercent tableforfieldcreate">';

	print '<tr><td class="titlefieldcreate fieldrequired">'.$langs->trans('NewsTitle').'</td>';
	print '<td><input type="text" name="title" class="minwidth500 maxwidth100onsmartphone" maxlength="255" value="'.dol_escape_htmltag(GETPOST('title', 'alphanohtml')).'" autofocus></td></tr>';

	print '<tr><td class="fieldrequired">'.$langs->trans('NewsRecipients').'</td><td>';
	print $form->multiselectarray('recipients', $choices, $selected, 0, 0, 'minwidth500 maxwidth100onsmartphone', 0, 0, '', '', $langs->trans('NewsRecipientsPlaceholder'));
	if ($cansendall) {
		print '<br><label class="paddingtop inline-block"><input type="checkbox" name="toall" value="1"'.(GETPOSTINT('toall') ? ' checked' : '').'> '.$langs->trans('NewsToAll').'</label>';
	}
	print '</td></tr>';

	print '<tr><td>'.$form->textwithpicto($langs->trans('NewsImportant'), $langs->trans('NewsImportantHelp')).'</td>';
	print '<td><input type="checkbox" name="important" value="1"'.(GETPOSTINT('important') ? ' checked' : '').'></td></tr>';

	print '<tr><td class="tdtop">'.$langs->trans('NewsContent').'</td><td>';
	$editor = new DolEditor('content', GETPOST('content', 'restricthtml'), '', 250, 'dolibarr_notes', '', false, true, isModEnabled('fckeditor'), ROWS_8, '90%');
	$editor->Create();
	print '</td></tr>';

	print '</table>';
	print dol_get_fiche_end();

	print $form->buttonsSaveCancel('NewsSend', 'Cancel', array(), 0, '', dol_buildpath('/news/list.php', 1).'?mode=sent');
	print '</form>';

	llxFooter();
	$db->close();
	exit;
}

if (!($object->id > 0)) {
	header('Location: '.dol_buildpath('/news/list.php', 1).'?mode=received');
	exit;
}

llxHeader('', $object->title, '', '', 0, 0, '', '', '', 'mod-news page-card');

if ($action == 'withdraw') {
	print $form->formconfirm($_SERVER['PHP_SELF'].'?id='.$object->id, $langs->trans('NewsWithdraw'), $langs->trans('NewsConfirmWithdraw'), 'confirm_withdraw', '', 0, 1);
}
if ($action == 'delete') {
	print $form->formconfirm($_SERVER['PHP_SELF'].'?id='.$object->id, $langs->trans('Delete'), $langs->trans('NewsConfirmDelete'), 'confirm_delete', '', 0, 1);
}

$author = new User($db);
$author->fetch($object->fk_user_author);

$titlehtml = ($object->important ? '<span class="fa fa-exclamation-triangle news-important paddingright" title="'.dol_escape_htmltag($langs->trans('NewsImportant')).'"></span>' : '').dol_escape_htmltag($object->title);
print load_fiche_titre($titlehtml, '', 'fa-newspaper');

print dol_get_fiche_head(array(), '');
print '<table class="border centpercent tableforfield">';
print '<tr><td class="titlefield">'.$langs->trans('NewsFrom').'</td><td>'.$author->getNomUrl(-1).'</td></tr>';
print '<tr><td>'.$langs->trans('Date').'</td><td>'.dol_print_date($object->datec, 'dayhour', 'tzuserrel').'</td></tr>';
if ($object->status == News::STATUS_WITHDRAWN) {
	print '<tr><td>'.$langs->trans('Status').'</td><td><span class="badge badge-status8 badge-status">'.$langs->trans('NewsStatusWithdrawn').'</span> ';
	print '<span class="opacitymedium">'.dol_print_date($object->date_withdraw, 'dayhour', 'tzuserrel').'</span></td></tr>';
}
print '</table>';
print dol_get_fiche_end();

print '<div class="news-content">'.dolPrintHTML($object->content).'</div>';

// Buttons
print '<div class="tabsAction">';
if ($canwithdraw) {
	print dolGetButtonAction('', $langs->trans('NewsWithdraw'), 'default', $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=withdraw&token='.newToken(), '', true);
}
if ($candelete) {
	print dolGetButtonAction('', $langs->trans('Delete'), 'delete', $_SERVER['PHP_SELF'].'?id='.$object->id.'&action=delete&token='.newToken(), '', true);
}
print '</div>';

// Read status: only for the author and for users with the "all news" permission
if ($object->canSeeReadStatus($user)) {
	$recipients = $object->fetchRecipients();
	$nbread = 0;
	foreach ($recipients as $r) {
		if (!empty($r['date_read'])) {
			$nbread++;
		}
	}

	print load_fiche_titre($langs->trans('NewsReadStatus').' <span class="opacitymedium">('.$nbread.' / '.count($recipients).')</span>', '', '');
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans('User').'</td>';
	print '<td>'.$langs->trans('NewsReachedVia').'</td>';
	print '<td class="center">'.$langs->trans('NewsReadOn').'</td>';
	print '</tr>';
	foreach ($recipients as $r) {
		print '<tr class="oddeven">';
		print '<td>'.dol_escape_htmltag($r['name']).'</td>';
		print '<td>';
		if ($r['source'] == 'group') {
			print '<span class="fa fa-users paddingright opacitymedium"></span>'.dol_escape_htmltag($r['groupname']);
		} elseif ($r['source'] == 'all') {
			print '<span class="opacitymedium">'.$langs->trans('NewsToAllShort').'</span>';
		} else {
			print '<span class="opacitymedium">'.$langs->trans('NewsDirect').'</span>';
		}
		print '</td>';
		print '<td class="center">';
		if (!empty($r['date_read'])) {
			print img_picto('', 'tick', 'class="paddingright"').dol_print_date($r['date_read'], 'dayhour', 'tzuserrel');
		} else {
			print '<span class="badge badge-status1 badge-status">'.$langs->trans('NewsUnread').'</span>';
		}
		print '</td>';
		print '</tr>';
	}
	if (empty($recipients)) {
		print '<tr class="oddeven"><td colspan="3"><span class="opacitymedium">'.$langs->trans('None').'</span></td></tr>';
	}
	print '</table>';
	print '</div>';
}

llxFooter();
$db->close();
