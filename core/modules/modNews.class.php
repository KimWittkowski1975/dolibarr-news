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
 * \defgroup   news     Module News
 * \brief      News module descriptor.
 *
 * \file       htdocs/custom/news/core/modules/modNews.class.php
 * \ingroup    news
 * \brief      Description and activation file for module News
 */
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';
require_once __DIR__.'/../../lib/news.lib.php';

/**
 * Description and activation class for module News
 *
 * Short internal messages to users and user groups, with a per-recipient read state.
 */
class modNews extends DolibarrModules
{
	/**
	 * Constructor. Define names, constants, directories, boxes, permissions
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $langs, $conf;
		$this->db = $db;

		// Numero reserved in the development helper DB. Rights use 501261..501265.
		$this->numero = 501260;
		$this->rights_class = 'news';
		$this->family = "other";
		$this->module_position = '90';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = "Short internal news to users and user groups, with read status";
		$this->descriptionlong = "Send short messages to single users, user groups or all users. Groups are resolved into a fixed recipient list at send time. Recipients see a banner and optionally a browser popup (agenda reminder). Senders see who has read their news; a dedicated permission shows the read state of all news.";
		$this->editor_name = 'Kim Wittkowski';
		$this->editor_url = 'kim@wittkowski-it.de';
		$this->version = '1.0.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'fa-newspaper';

		$this->module_parts = array(
			'triggers' => 0,
			'login' => 0,
			'substitutions' => 0,
			'menus' => 0,
			'tpl' => 0,
			'barcode' => 0,
			'models' => 0,
			'printing' => 0,
			'theme' => 0,
			'css' => array('/news/css/news.css.php'),
			'js' => array(),
			// main         = printMainArea (banner) and menuLeftMenuItems (unread counter)
			// actioncard   = click on the browser popup opens the agenda card -> forward to the news
			'hooks' => array('main', 'actioncard'),
			'moduleforexternal' => 0,
		);

		$this->dirs = array();
		$this->config_page_url = array("setup.php@news");

		$this->hidden = false;
		// Agenda is optional: without it there is simply no popup, the banner still works
		$this->depends = array();
		$this->requiredby = array();
		$this->conflictwith = array();

		$this->langfiles = array("news@news");

		$this->phpmin = array(7, 4);
		$this->need_dolibarr_version = array(22, 0);

		$this->warnings_activation = array();
		$this->warnings_activation_ext = array();

		$this->const = array(
			0 => array('NEWS_POPUP_ENABLED', 'chaine', '1', 'Create an agenda event with browser reminder per recipient', 0, 'current', 0),
		);

		$this->tabs = array();
		$this->dictionaries = array();
		$this->boxes = array();
		$this->cronjobs = array();

		// Permissions
		$this->rights = array();
		$r = 0;

		$this->rights[$r][0] = 501261;
		$this->rights[$r][1] = 'Read news addressed to me';
		$this->rights[$r][2] = 'r';
		$this->rights[$r][3] = 1;
		$this->rights[$r][4] = 'read';
		$r++;

		$this->rights[$r][0] = 501262;
		$this->rights[$r][1] = 'Create and send news';
		$this->rights[$r][2] = 'w';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'write';
		$r++;

		$this->rights[$r][0] = 501263;
		$this->rights[$r][1] = 'Send news to all users';
		$this->rights[$r][2] = 'w';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'sendall';
		$r++;

		$this->rights[$r][0] = 501264;
		$this->rights[$r][1] = 'See read status of all news and withdraw any news';
		$this->rights[$r][2] = 'r';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'readall';
		$r++;

		$this->rights[$r][0] = 501265;
		$this->rights[$r][1] = 'Delete news';
		$this->rights[$r][2] = 'd';
		$this->rights[$r][3] = 0;
		$this->rights[$r][4] = 'delete';
		$r++;

		// Menus: one block in the left menu of the home page
		$this->menu = array();
		$r = 0;

		$this->menu[$r++] = array(
			'fk_menu'  => 'fk_mainmenu=home',
			'type'     => 'left',
			'titre'    => 'NewsMenuTitle',
			'prefix'   => 'fa-newspaper',
			'mainmenu' => 'home',
			'leftmenu' => 'news',
			'url'      => '/news/list.php?mode=received',
			'langs'    => 'news@news',
			'position' => 1000,
			'enabled'  => 'isModEnabled("news")',
			'perms'    => '$user->hasRight("news", "read")',
			'target'   => '',
			'user'     => 0,
		);
		$this->menu[$r++] = array(
			'fk_menu'  => 'fk_mainmenu=home,fk_leftmenu=news',
			'type'     => 'left',
			'titre'    => 'NewsMenuCreate',
			'mainmenu' => 'home',
			'leftmenu' => 'news_create',
			'url'      => '/news/card.php?action=create',
			'langs'    => 'news@news',
			'position' => 1001,
			'enabled'  => 'isModEnabled("news")',
			'perms'    => '$user->hasRight("news", "write")',
			'target'   => '',
			'user'     => 0,
		);
		$this->menu[$r++] = array(
			'fk_menu'  => 'fk_mainmenu=home,fk_leftmenu=news',
			'type'     => 'left',
			'titre'    => 'NewsMenuSent',
			'mainmenu' => 'home',
			'leftmenu' => 'news_sent',
			'url'      => '/news/list.php?mode=sent',
			'langs'    => 'news@news',
			'position' => 1002,
			'enabled'  => 'isModEnabled("news")',
			'perms'    => '$user->hasRight("news", "write")',
			'target'   => '',
			'user'     => 0,
		);
		$this->menu[$r++] = array(
			'fk_menu'  => 'fk_mainmenu=home,fk_leftmenu=news',
			'type'     => 'left',
			'titre'    => 'NewsMenuReceived',
			'mainmenu' => 'home',
			'leftmenu' => 'news_received',
			'url'      => '/news/list.php?mode=received',
			'langs'    => 'news@news',
			'position' => 1003,
			'enabled'  => 'isModEnabled("news")',
			'perms'    => '$user->hasRight("news", "read")',
			'target'   => '',
			'user'     => 0,
		);
		$this->menu[$r++] = array(
			'fk_menu'  => 'fk_mainmenu=home,fk_leftmenu=news',
			'type'     => 'left',
			'titre'    => 'NewsMenuAll',
			'mainmenu' => 'home',
			'leftmenu' => 'news_all',
			'url'      => '/news/list.php?mode=all',
			'langs'    => 'news@news',
			'position' => 1004,
			'enabled'  => 'isModEnabled("news")',
			'perms'    => '$user->hasRight("news", "readall")',
			'target'   => '',
			'user'     => 0,
		);
	}

	/**
	 * Function called when module is enabled.
	 *
	 * @param  string $options Options when enabling module ('', 'noboxes')
	 * @return int             1 if OK, 0 if KO
	 */
	public function init($options = '')
	{
		$this->remove($options);

		$result = $this->_load_tables('/news/sql/');
		if ($result < 0) {
			return -1;
		}

		// Own agenda event type, so popup events can be filtered or hidden in the agenda
		$sql = array(
			"DELETE FROM ".MAIN_DB_PREFIX."c_actioncomm WHERE code = '".$this->db->escape(NEWS_EVENT_CODE)."'",
			"INSERT INTO ".MAIN_DB_PREFIX."c_actioncomm (id, code, type, libelle, module, active, position)"
			." VALUES (".((int) NEWS_EVENT_TYPE_ID).", '".$this->db->escape(NEWS_EVENT_CODE)."', 'module', 'News', 'news@news', 1, 91)",
		);

		return $this->_init($sql, $options);
	}

	/**
	 * Function called when module is disabled. News and agenda events are kept.
	 *
	 * @param  string $options Options when enabling module ('', 'noboxes')
	 * @return int             1 if OK, 0 if KO
	 */
	public function remove($options = '')
	{
		$sql = array(
			"DELETE FROM ".MAIN_DB_PREFIX."c_actioncomm WHERE code = '".$this->db->escape(NEWS_EVENT_CODE)."'",
		);

		return $this->_remove($sql, $options);
	}
}
