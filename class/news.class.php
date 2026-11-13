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
 * \file    htdocs/custom/news/class/news.class.php
 * \ingroup news
 * \brief   News item with its fixed recipient list
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
dol_include_once('/news/lib/news.lib.php');

/**
 * One news item.
 *
 * Reading is deliberately NOT restricted by entity: a news item is addressed to
 * persons, and a recipient must see it in whatever entity they are working.
 */
class News extends CommonObject
{
	/** @var string */
	public $element = 'news';
	/** @var string */
	public $table_element = 'news';
	/** @var string */
	public $picto = 'fa-newspaper';

	const STATUS_SENT = 1;
	const STATUS_WITHDRAWN = 9;

	/** @var string */
	public $title = '';
	/** @var string HTML */
	public $content = '';
	/** @var int */
	public $important = 0;
	/** @var int */
	public $to_all = 0;
	/** @var int */
	public $status = self::STATUS_SENT;
	/** @var int */
	public $datec;
	/** @var int */
	public $fk_user_author = 0;
	/** @var int|string */
	public $date_withdraw = '';
	/** @var int */
	public $fk_user_withdraw = 0;

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
	 * Return the selectable users: active, internal, every entity.
	 *
	 * @return array<int,string> Full names keyed by user id
	 */
	public function getSelectableUsers()
	{
		$sql = "SELECT u.rowid, u.lastname, u.firstname, u.login";
		$sql .= " FROM ".$this->db->prefix()."user as u";
		$sql .= " WHERE u.statut = 1 AND (u.fk_soc IS NULL OR u.fk_soc = 0)";
		$sql .= " ORDER BY u.lastname, u.firstname";

		$out = array();
		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__." ".$this->db->lasterror(), LOG_ERR);
			return $out;
		}
		while ($obj = $this->db->fetch_object($resql)) {
			$name = trim($obj->firstname.' '.$obj->lastname);
			$out[(int) $obj->rowid] = ($name !== '' ? $name : $obj->login);
		}
		$this->db->free($resql);

		return $out;
	}

	/**
	 * Return the user groups that have at least one active internal member.
	 *
	 * @return array<int,array{name:string,nb:int}> Groups keyed by id
	 */
	public function getSelectableGroups()
	{
		$sql = "SELECT g.rowid, g.nom as name, COUNT(DISTINCT u.rowid) as nb";
		$sql .= " FROM ".$this->db->prefix()."usergroup as g";
		$sql .= " INNER JOIN ".$this->db->prefix()."usergroup_user as ugu ON ugu.fk_usergroup = g.rowid";
		$sql .= " INNER JOIN ".$this->db->prefix()."user as u ON u.rowid = ugu.fk_user";
		$sql .= " WHERE u.statut = 1 AND (u.fk_soc IS NULL OR u.fk_soc = 0)";
		$sql .= " GROUP BY g.rowid, g.nom";
		$sql .= " ORDER BY g.nom";

		$out = array();
		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__." ".$this->db->lasterror(), LOG_ERR);
			return $out;
		}
		while ($obj = $this->db->fetch_object($resql)) {
			$out[(int) $obj->rowid] = array('name' => (string) $obj->name, 'nb' => (int) $obj->nb);
		}
		$this->db->free($resql);

		return $out;
	}

	/**
	 * Resolve the picked targets into the fixed recipient list.
	 *
	 * Priority when somebody is reached more than once: direct pick, then the first
	 * group, then "all users". The author never receives their own news, inactive
	 * and external users are skipped.
	 *
	 * @param  int[] $userids  Directly picked users
	 * @param  int[] $groupids Picked groups
	 * @param  bool  $toall    "All users" picked
	 * @param  int   $authorid Author (excluded)
	 * @return array<int,array{source:string,fk_usergroup:int,entity:int}>|int Recipients keyed by user id, -1 on error
	 */
	public function resolveRecipients($userids, $groupids, $toall, $authorid)
	{
		$out = array();

		$add = function ($uid, $source, $gid, $entity) use (&$out, $authorid) {
			$uid = (int) $uid;
			if ($uid <= 0 || $uid == (int) $authorid || isset($out[$uid])) {
				return;
			}
			$out[$uid] = array('source' => $source, 'fk_usergroup' => (int) $gid, 'entity' => (int) $entity);
		};

		$active = " u.statut = 1 AND (u.fk_soc IS NULL OR u.fk_soc = 0)";

		// 1. Direct picks -- ids come from the browser, check them against the DB
		$ids = array_filter(array_map('intval', (array) $userids));
		if (!empty($ids)) {
			$sql = "SELECT u.rowid, u.entity FROM ".$this->db->prefix()."user as u";
			$sql .= " WHERE".$active." AND u.rowid IN (".$this->db->sanitize(implode(',', $ids)).")";
			$resql = $this->db->query($sql);
			if (!$resql) {
				$this->error = $this->db->lasterror();
				return -1;
			}
			while ($obj = $this->db->fetch_object($resql)) {
				$add($obj->rowid, 'user', 0, $obj->entity);
			}
			$this->db->free($resql);
		}

		// 2. Groups, resolved now (fixed list)
		$gids = array_filter(array_map('intval', (array) $groupids));
		if (!empty($gids)) {
			$sql = "SELECT DISTINCT ugu.fk_usergroup as gid, u.rowid, u.entity";
			$sql .= " FROM ".$this->db->prefix()."usergroup_user as ugu";
			$sql .= " INNER JOIN ".$this->db->prefix()."user as u ON u.rowid = ugu.fk_user";
			$sql .= " WHERE".$active." AND ugu.fk_usergroup IN (".$this->db->sanitize(implode(',', $gids)).")";
			$sql .= " ORDER BY ugu.fk_usergroup, u.rowid";
			$resql = $this->db->query($sql);
			if (!$resql) {
				$this->error = $this->db->lasterror();
				return -1;
			}
			while ($obj = $this->db->fetch_object($resql)) {
				$add($obj->rowid, 'group', $obj->gid, $obj->entity);
			}
			$this->db->free($resql);
		}

		// 3. Everybody
		if ($toall) {
			$sql = "SELECT u.rowid, u.entity FROM ".$this->db->prefix()."user as u WHERE".$active;
			$resql = $this->db->query($sql);
			if (!$resql) {
				$this->error = $this->db->lasterror();
				return -1;
			}
			while ($obj = $this->db->fetch_object($resql)) {
				$add($obj->rowid, 'all', 0, $obj->entity);
			}
			$this->db->free($resql);
		}

		return $out;
	}

	/**
	 * Create and send the news item.
	 *
	 * @param  User  $user     Author
	 * @param  int[] $userids  Directly picked users
	 * @param  int[] $groupids Picked groups
	 * @param  bool  $toall    Send to all users (caller checks the permission)
	 * @return int             New id, <0 on error, 0 when nobody would receive it
	 */
	public function send(User $user, $userids, $groupids, $toall)
	{
		global $conf;

		$this->title = trim((string) $this->title);
		if ($this->title === '') {
			$this->error = 'NewsErrorTitleRequired';
			return -2;
		}

		$recipients = $this->resolveRecipients($userids, $groupids, $toall, $user->id);
		if (!is_array($recipients)) {
			return -1;
		}
		if (empty($recipients)) {
			$this->error = 'NewsErrorNoRecipient';
			return 0;
		}

		$now = dol_now();
		$this->db->begin();

		$sql = "INSERT INTO ".$this->db->prefix()."news (entity, title, content, important, to_all, status, datec, fk_user_author)";
		$sql .= " VALUES (".((int) $conf->entity).", '".$this->db->escape(dol_trunc($this->title, 255, 'right', 'UTF-8', 1))."',";
		$sql .= " '".$this->db->escape((string) $this->content)."', ".($this->important ? 1 : 0).", ".($toall ? 1 : 0).",";
		$sql .= " ".self::STATUS_SENT.", '".$this->db->idate($now)."', ".((int) $user->id).")";
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}
		$this->id = (int) $this->db->last_insert_id($this->db->prefix()."news");
		$this->datec = $now;
		$this->fk_user_author = (int) $user->id;
		$this->to_all = $toall ? 1 : 0;
		$this->status = self::STATUS_SENT;

		$popup = newsPopupEnabled();
		foreach ($recipients as $uid => $r) {
			$eventid = 0;
			if ($popup) {
				$eventid = $this->createPopup($uid, $r['entity'], $user);
				if ($eventid < 0) {
					// A failing popup must not cost the news itself, the banner still shows it
					dol_syslog(__METHOD__." popup for user ".$uid." failed: ".$this->error, LOG_WARNING);
					$eventid = 0;
				}
			}
			$sql = "INSERT INTO ".$this->db->prefix()."news_recipient (fk_news, fk_user, source, fk_usergroup, fk_actioncomm)";
			$sql .= " VALUES (".((int) $this->id).", ".((int) $uid).", '".$this->db->escape($r['source'])."',";
			$sql .= " ".($r['fk_usergroup'] > 0 ? (int) $r['fk_usergroup'] : "NULL").", ".($eventid > 0 ? (int) $eventid : "NULL").")";
			if (!$this->db->query($sql)) {
				$this->error = $this->db->lasterror();
				$this->db->rollback();
				return -1;
			}
		}

		$this->db->commit();

		return $this->id;
	}

	/**
	 * Create the agenda event plus the browser reminder that makes the yellow popup appear.
	 *
	 * Note: ActionComm::create() always files the event under $conf->entity. The
	 * reminder however is read by core/ajax/check_notifications.php only for the
	 * entity the recipient is logged into, so it is filed under the recipient's own
	 * entity (superadmins with entity 0 under entity 1).
	 *
	 * @param  int  $uid    Recipient
	 * @param  int  $entity Recipient entity
	 * @param  User $author Author
	 * @return int          Event id, <0 on error
	 */
	protected function createPopup($uid, $entity, User $author)
	{
		global $conf;

		require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
		require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncommreminder.class.php';

		// Label in the recipient's language
		$target = new User($this->db);
		if ($target->fetch($uid, '', '', 1) <= 0) {
			$this->error = 'Unknown user '.$uid;
			return -1;
		}
		$outputlangs = new Translate('', $conf);
		$outputlangs->setDefaultLang(empty($target->conf->MAIN_LANG_DEFAULT) ? getDolGlobalString('MAIN_LANG_DEFAULT', 'auto') : $target->conf->MAIN_LANG_DEFAULT);
		$outputlangs->loadLangs(array('main', 'news@news'));

		$now = dol_now();
		$key = $this->important ? 'NewsPopupLabelImportant' : 'NewsPopupLabel';
		$label = $outputlangs->transnoentities($key, $author->getFullName($outputlangs), $this->title);

		$event = new ActionComm($this->db);
		$event->type_code = NEWS_EVENT_CODE;
		$event->code = NEWS_EVENT_CODE;
		$event->label = dol_trunc($label, 120, 'right', 'UTF-8', 1);
		$event->datep = $now;
		$event->datef = $now;
		$event->percentage = -1;
		$event->fulldayevent = 0;
		$event->userownerid = (int) $uid;
		$event->authorid = (int) $author->id;
		$event->note_private = '<a href="'.dol_buildpath('/news/card.php', 1).'?id='.((int) $this->id).'">'.dol_escape_htmltag($this->title).'</a>';

		// notrigger: a pure notification, other modules have no business reacting to it
		$eventid = $event->create($author, 1);
		if ($eventid <= 0) {
			$this->error = $event->error;
			return -1;
		}

		$reminder = new ActionCommReminder($this->db);
		// check_notifications.php selects "dateremind < NOW()", strictly less
		$reminder->dateremind = $now - 60;
		$reminder->typeremind = 'browser';
		$reminder->fk_user = (int) $uid;
		$reminder->offsetvalue = 0;
		$reminder->offsetunit = 'i';
		$reminder->status = ActionCommReminder::STATUS_TODO;
		$reminder->fk_actioncomm = (int) $eventid;
		$reminder->entity = ((int) $entity > 0 ? (int) $entity : 1);
		if ($reminder->create($author, 1) <= 0) {
			$this->error = $reminder->error;
			return -1;
		}

		return $eventid;
	}

	/**
	 * Load a news item.
	 *
	 * @param  int $id Id
	 * @return int     1 found, 0 not found, -1 error
	 */
	public function fetch($id)
	{
		$sql = "SELECT rowid, entity, title, content, important, to_all, status, datec, fk_user_author, date_withdraw, fk_user_withdraw";
		$sql .= " FROM ".$this->db->prefix()."news WHERE rowid = ".((int) $id);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);
		if (!$obj) {
			return 0;
		}
		$this->id = (int) $obj->rowid;
		$this->entity = (int) $obj->entity;
		$this->title = (string) $obj->title;
		$this->content = (string) $obj->content;
		$this->important = (int) $obj->important;
		$this->to_all = (int) $obj->to_all;
		$this->status = (int) $obj->status;
		$this->datec = $this->db->jdate($obj->datec);
		$this->fk_user_author = (int) $obj->fk_user_author;
		$this->date_withdraw = $this->db->jdate($obj->date_withdraw);
		$this->fk_user_withdraw = (int) $obj->fk_user_withdraw;

		return 1;
	}

	/**
	 * Recipients with read state.
	 *
	 * @return array<int,array<string,mixed>> Rows keyed by user id
	 */
	public function fetchRecipients()
	{
		$sql = "SELECT r.fk_user, r.source, r.fk_usergroup, r.date_read, u.lastname, u.firstname, u.login, g.nom as groupname";
		$sql .= " FROM ".$this->db->prefix()."news_recipient as r";
		$sql .= " LEFT JOIN ".$this->db->prefix()."user as u ON u.rowid = r.fk_user";
		$sql .= " LEFT JOIN ".$this->db->prefix()."usergroup as g ON g.rowid = r.fk_usergroup";
		$sql .= " WHERE r.fk_news = ".((int) $this->id);
		$sql .= " ORDER BY r.date_read IS NULL DESC, u.lastname, u.firstname";

		$out = array();
		$resql = $this->db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__." ".$this->db->lasterror(), LOG_ERR);
			return $out;
		}
		while ($obj = $this->db->fetch_object($resql)) {
			$name = trim($obj->firstname.' '.$obj->lastname);
			$out[(int) $obj->fk_user] = array(
				'id'        => (int) $obj->fk_user,
				'name'      => ($name !== '' ? $name : (string) $obj->login),
				'source'    => (string) $obj->source,
				'groupid'   => (int) $obj->fk_usergroup,
				'groupname' => (string) $obj->groupname,
				'date_read' => $this->db->jdate($obj->date_read),
			);
		}
		$this->db->free($resql);

		return $out;
	}

	/**
	 * Tell whether a user is a recipient.
	 *
	 * @param  int  $userid User id
	 * @return bool
	 */
	public function isRecipient($userid)
	{
		$sql = "SELECT rowid FROM ".$this->db->prefix()."news_recipient";
		$sql .= " WHERE fk_news = ".((int) $this->id)." AND fk_user = ".((int) $userid);
		$resql = $this->db->query($sql);
		if (!$resql) {
			return false;
		}
		$found = ($this->db->num_rows($resql) > 0);
		$this->db->free($resql);

		return $found;
	}

	/**
	 * Tell whether a user may open this news item at all.
	 *
	 * @param  User $user User
	 * @return bool
	 */
	public function canRead(User $user)
	{
		if ((int) $this->fk_user_author === (int) $user->id || newsCanSeeAll($user)) {
			return true;
		}

		return $this->status == self::STATUS_SENT && $this->isRecipient($user->id);
	}

	/**
	 * Tell whether a user may see the read status of this news item.
	 *
	 * @param  User $user User
	 * @return bool
	 */
	public function canSeeReadStatus(User $user)
	{
		return (int) $this->fk_user_author === (int) $user->id || newsCanSeeAll($user);
	}

	/**
	 * Mark as read for one user. Also closes a still pending popup.
	 *
	 * @param  int $userid User id
	 * @return int         1 newly marked, 0 already read or not a recipient, -1 error
	 */
	public function markRead($userid)
	{
		$sql = "UPDATE ".$this->db->prefix()."news_recipient SET date_read = '".$this->db->idate(dol_now())."'";
		$sql .= " WHERE fk_news = ".((int) $this->id)." AND fk_user = ".((int) $userid)." AND date_read IS NULL";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$n = $this->db->affected_rows($resql);

		// Already read through the banner: the popup has nothing left to say
		$sql = "UPDATE ".$this->db->prefix()."actioncomm_reminder SET status = 1";
		$sql .= " WHERE status = 0 AND fk_user = ".((int) $userid);
		$sql .= " AND fk_actioncomm IN (SELECT fk_actioncomm FROM ".$this->db->prefix()."news_recipient";
		$sql .= " WHERE fk_news = ".((int) $this->id)." AND fk_user = ".((int) $userid)." AND fk_actioncomm IS NOT NULL)";
		$this->db->query($sql);

		return $n > 0 ? 1 : 0;
	}

	/**
	 * Withdraw: recipients no longer see it, pending popups are removed.
	 *
	 * @param  User $user User withdrawing
	 * @return int        1 OK, -1 error
	 */
	public function withdraw(User $user)
	{
		$this->db->begin();

		$sql = "UPDATE ".$this->db->prefix()."news SET status = ".self::STATUS_WITHDRAWN.",";
		$sql .= " date_withdraw = '".$this->db->idate(dol_now())."', fk_user_withdraw = ".((int) $user->id);
		$sql .= " WHERE rowid = ".((int) $this->id)." AND status = ".self::STATUS_SENT;
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}

		$sql = "DELETE FROM ".$this->db->prefix()."actioncomm_reminder WHERE status = 0";
		$sql .= " AND fk_actioncomm IN (SELECT fk_actioncomm FROM ".$this->db->prefix()."news_recipient";
		$sql .= " WHERE fk_news = ".((int) $this->id)." AND fk_actioncomm IS NOT NULL)";
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}

		$this->db->commit();
		$this->status = self::STATUS_WITHDRAWN;

		return 1;
	}

	/**
	 * Delete for good, including read list and agenda events.
	 *
	 * @param  User $user User deleting
	 * @return int        1 OK, -1 error
	 */
	public function delete(User $user)
	{
		require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';

		$eventids = array();
		$sql = "SELECT fk_actioncomm FROM ".$this->db->prefix()."news_recipient";
		$sql .= " WHERE fk_news = ".((int) $this->id)." AND fk_actioncomm IS NOT NULL";
		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$eventids[] = (int) $obj->fk_actioncomm;
			}
			$this->db->free($resql);
		}

		$this->db->begin();

		foreach ($eventids as $eid) {
			$event = new ActionComm($this->db);
			if ($event->fetch($eid) > 0 && $event->type_code == NEWS_EVENT_CODE) {
				if ($event->delete($user, 1) < 0) {
					$this->error = $event->error;
					$this->db->rollback();
					return -1;
				}
			}
		}

		foreach (array('news_recipient' => 'fk_news', 'news' => 'rowid') as $table => $field) {
			$sql = "DELETE FROM ".$this->db->prefix().$table." WHERE ".$field." = ".((int) $this->id);
			if (!$this->db->query($sql)) {
				$this->error = $this->db->lasterror();
				$this->db->rollback();
				return -1;
			}
		}

		$this->db->commit();

		return 1;
	}

	/**
	 * Count unread news of a user (cached per request).
	 *
	 * @param  DoliDB $db     Database handler
	 * @param  int    $userid User id
	 * @return array{nb:int,important:int,lastid:int} Counters, lastid = newest unread news
	 */
	public static function countUnread($db, $userid)
	{
		static $cache = array();
		$userid = (int) $userid;
		if (isset($cache[$userid])) {
			return $cache[$userid];
		}

		$res = array('nb' => 0, 'important' => 0, 'lastid' => 0);
		$sql = "SELECT COUNT(r.rowid) as nb, SUM(n.important) as nbimp, MAX(n.rowid) as lastid";
		$sql .= " FROM ".$db->prefix()."news_recipient as r";
		$sql .= " INNER JOIN ".$db->prefix()."news as n ON n.rowid = r.fk_news";
		$sql .= " WHERE r.fk_user = ".$userid." AND r.date_read IS NULL AND n.status = ".self::STATUS_SENT;
		$resql = $db->query($sql);
		if ($resql) {
			if ($obj = $db->fetch_object($resql)) {
				$res = array('nb' => (int) $obj->nb, 'important' => (int) $obj->nbimp, 'lastid' => (int) $obj->lastid);
			}
			$db->free($resql);
		}
		$cache[$userid] = $res;

		return $res;
	}

	/**
	 * Link to the card.
	 *
	 * @param  int    $withpicto Add picto
	 * @param  string $option    Unused
	 * @param  int    $notooltip Unused
	 * @param  string $morecss   Extra CSS
	 * @param  int    $save_lastsearch_value Unused
	 * @return string
	 */
	public function getNomUrl($withpicto = 0, $option = '', $notooltip = 0, $morecss = '', $save_lastsearch_value = -1)
	{
		$url = dol_buildpath('/news/card.php', 1).'?id='.((int) $this->id);
		$out = '<a href="'.$url.'"'.($morecss ? ' class="'.dol_escape_htmltag($morecss).'"' : '').'>';
		if ($withpicto) {
			$out .= img_picto('', $this->picto, 'class="pictofixedwidth"');
		}
		$out .= dol_escape_htmltag($this->title).'</a>';

		return $out;
	}
}
