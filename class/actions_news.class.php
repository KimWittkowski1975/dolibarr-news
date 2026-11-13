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
 * \file    htdocs/custom/news/class/actions_news.class.php
 * \ingroup news
 * \brief   Hooks: unread banner, unread counter in the left menu, popup forwarding
 */

dol_include_once('/news/class/news.class.php');

/**
 * Hook class of module News
 */
class ActionsNews
{
	/** @var DoliDB */
	public $db;
	/** @var string */
	public $error = '';
	/** @var string[] */
	public $errors = array();
	/** @var array<mixed> */
	public $results = array();
	/** @var string */
	public $resprints = '';

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Tell whether the current user takes part in news at all.
	 *
	 * @return bool
	 */
	protected function userEligible()
	{
		global $user;

		return is_object($user) && !empty($user->id) && empty($user->socid)
			&& isModEnabled('news') && $user->hasRight('news', 'read');
	}

	/**
	 * Banner "You have X new news" on top of every page.
	 *
	 * @param  array<string,mixed> $parameters Hook parameters
	 * @param  object|null         $object     Unused
	 * @param  string              $action     Unused
	 * @param  HookManager         $hookmanager Hook manager
	 * @return int
	 */
	public function printMainArea($parameters, &$object, &$action, $hookmanager)
	{
		global $user, $langs;

		if (!$this->userEligible() || GETPOST('dol_openinpopup', 'aZ09') || GETPOST('optioncss', 'aZ09') == 'print') {
			return 0;
		}
		// The card of a news item marks it read itself; no banner on our own pages
		if (strpos((string) $_SERVER['PHP_SELF'], '/news/') !== false) {
			return 0;
		}

		$c = News::countUnread($this->db, $user->id);
		if ($c['nb'] <= 0) {
			return 0;
		}

		$langs->load('news@news');
		if ($c['nb'] == 1) {
			$url = dol_buildpath('/news/card.php', 1).'?id='.$c['lastid'];
			$text = $langs->trans('NewsBannerOne');
		} else {
			$url = dol_buildpath('/news/list.php', 1).'?mode=received&search_unread=1';
			$text = $langs->trans('NewsBannerMany', $c['nb']);
		}
		$important = ($c['important'] > 0);
		if ($important) {
			$text .= ' '.$langs->trans('NewsBannerImportant');
		}

		$this->resprints = '<div class="news-banner'.($important ? ' news-banner-important' : '').'">'
			.'<span class="fa fa-newspaper paddingright"></span>'
			.'<a href="'.$url.'">'.dol_escape_htmltag($text).'</a>'
			.'</div>';

		return 0;
	}

	/**
	 * Add the unread counter to the left menu entry "News".
	 *
	 * @param  array<string,mixed>                  $parameters Hook parameters
	 * @param  array<int,array<string,mixed>>       $object     Left menu entries
	 * @param  string                               $action     Unused
	 * @param  HookManager                          $hookmanager Hook manager
	 * @return int
	 */
	public function menuLeftMenuItems($parameters, &$object, &$action, $hookmanager)
	{
		global $user;

		if (!$this->userEligible() || !is_array($object) || ($parameters['mainmenu'] ?? '') !== 'home') {
			return 0;
		}

		$c = News::countUnread($this->db, $user->id);
		if ($c['nb'] <= 0) {
			return 0;
		}

		$changed = false;
		foreach ($object as $i => $entry) {
			if (isset($entry['leftmenu']) && in_array($entry['leftmenu'], array('news', 'news_received'), true)
				&& strpos((string) ($entry['url'] ?? ''), '/news/list.php') !== false) {
				$object[$i]['titre'] .= ' <span class="badge '.($c['important'] > 0 ? 'badge-danger' : 'badge-warning').' marginleftonlyshort">'.$c['nb'].'</span>';
				$changed = true;
			}
		}
		if (!$changed) {
			return 0;
		}

		// 1 = replace the menu with our (otherwise unchanged) copy. HookManager hands
		// $this->results (not a resArray property of ours) to the caller as resArray.
		$this->results = $object;

		return 1;
	}

	/**
	 * The yellow popup links to the agenda card of its event. For a news event,
	 * forward to the news item instead so that opening it also marks it read.
	 *
	 * @param  array<string,mixed> $parameters Hook parameters
	 * @param  object|null         $object     ActionComm
	 * @param  string              $action     Current action
	 * @param  HookManager         $hookmanager Hook manager
	 * @return int
	 */
	public function doActions($parameters, &$object, &$action, $hookmanager)
	{
		global $user;

		$contexts = explode(':', (string) ($parameters['context'] ?? ''));
		if (!in_array('actioncard', $contexts) || !empty($action) || !$this->userEligible()) {
			return 0;
		}

		$eventid = GETPOSTINT('id');
		if ($eventid <= 0) {
			return 0;
		}

		$sql = "SELECT fk_news FROM ".$this->db->prefix()."news_recipient";
		$sql .= " WHERE fk_actioncomm = ".((int) $eventid)." AND fk_user = ".((int) $user->id);
		$resql = $this->db->query($sql);
		if ($resql && ($obj = $this->db->fetch_object($resql))) {
			header('Location: '.dol_buildpath('/news/card.php', 1).'?id='.((int) $obj->fk_news));
			exit;
		}

		return 0;
	}
}
