<?php
/* Copyright (C) 2026 Kim Wittkowski <kim@wittkowski-it.de>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    htdocs/custom/news/css/news.css.php
 * \ingroup news
 * \brief   CSS of module News (static, no Dolibarr environment needed)
 */

if (!defined('NOREQUIREUSER')) {
	define('NOREQUIREUSER', '1');
}
if (!defined('NOREQUIREDB')) {
	define('NOREQUIREDB', '1');
}
if (!defined('NOREQUIRESOC')) {
	define('NOREQUIRESOC', '1');
}
if (!defined('NOREQUIRETRAN')) {
	define('NOREQUIRETRAN', '1');
}
if (!defined('NOCSRFCHECK')) {
	define('NOCSRFCHECK', 1);
}
if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', 1);
}
if (!defined('NOLOGIN')) {
	define('NOLOGIN', 1);
}
if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', 1);
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', 1);
}
if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', '1');
}

header('Content-type: text/css');
header('Cache-Control: max-age=3600, public, must-revalidate');
?>
.news-banner {
	margin: 0 0 12px 0;
	padding: 8px 14px;
	border-radius: 4px;
	background: #fff4cc;
	border: 1px solid #f0d36b;
	color: #6b5200;
	font-weight: 600;
}
.news-banner a, .news-banner a:visited {
	color: #6b5200;
	text-decoration: underline;
}
.news-banner-important {
	background: #fde2e1;
	border-color: #e57373;
	color: #8b1c1c;
}
.news-banner-important a, .news-banner-important a:visited {
	color: #8b1c1c;
}
.news-important {
	color: #c62828;
}
tr.news-unread td {
	font-weight: bold;
}
.news-content {
	margin: 10px 0 20px 0;
	padding: 14px 18px;
	border: 1px solid var(--colortopbordertitle1, #ddd);
	border-radius: 4px;
	background: var(--colorbacklineimpair1, #fff);
	line-height: 1.5;
}
