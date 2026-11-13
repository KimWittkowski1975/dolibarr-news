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
 * \file    htdocs/custom/news/lib/news.lib.php
 * \ingroup news
 * \brief   Common functions for module News
 */

/** Code of the agenda event type used for the popup events */
if (!defined('NEWS_EVENT_CODE')) {
	define('NEWS_EVENT_CODE', 'AC_NEWS');
}

/** Id of that type in llx_c_actioncomm (module numero, far above core ids) */
if (!defined('NEWS_EVENT_TYPE_ID')) {
	define('NEWS_EVENT_TYPE_ID', 501260);
}

/**
 * Prepare admin pages header
 *
 * @return array<int,array{0:string,1:string,2:string}> Tabs
 */
function newsAdminPrepareHead()
{
	global $langs, $conf;

	$langs->load("news@news");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/news/admin/setup.php", 1);
	$head[$h][1] = $langs->trans("Settings");
	$head[$h][2] = 'settings';
	$h++;

	$head[$h][0] = dol_buildpath("/news/admin/about.php", 1);
	$head[$h][1] = $langs->trans("NewsAbout");
	$head[$h][2] = 'about';
	$h++;

	complete_head_from_modules($conf, $langs, null, $head, $h, 'news@news');

	return $head;
}

/**
 * Prerequisites shown on the setup page. Without the browser reminder the popup
 * is created in the database but never displayed -- make that visible.
 *
 * @return array<int,array{key:string,ok:bool}> Requirement states
 */
function newsCheckRequirements()
{
	return array(
		array('key' => 'NewsReqAgendaModule', 'ok' => isModEnabled('agenda')),
		array('key' => 'NewsReqBrowserReminder', 'ok' => (bool) getDolGlobalString('AGENDA_REMINDER_BROWSER')),
		array('key' => 'NewsReqEditor', 'ok' => isModEnabled('fckeditor')),
	);
}

/**
 * Tell whether the popup (agenda event + browser reminder) is to be created.
 *
 * @return bool
 */
function newsPopupEnabled()
{
	return isModEnabled('agenda') && getDolGlobalInt('NEWS_POPUP_ENABLED', 1) > 0;
}

/**
 * Tell whether a user may see the read status of every news item.
 *
 * @param  User $user User
 * @return bool
 */
function newsCanSeeAll($user)
{
	return !empty($user->admin) || $user->hasRight('news', 'readall');
}
