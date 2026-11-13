-- Copyright (C) 2026 Kim Wittkowski <kim@wittkowski-it.de>
--
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 3 of the License, or
-- (at your option) any later version.
--
-- One short message. Deliberately NOT filtered by entity when read: a news item
-- is addressed to persons, not to a company. entity only records where it was written.

CREATE TABLE llx_news (
	rowid             integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity            integer DEFAULT 1 NOT NULL,
	title             varchar(255) NOT NULL,
	content           text,
	important         smallint DEFAULT 0 NOT NULL,
	to_all            smallint DEFAULT 0 NOT NULL,
	status            smallint DEFAULT 1 NOT NULL,
	datec             datetime NOT NULL,
	fk_user_author    integer NOT NULL,
	date_withdraw     datetime NULL,
	fk_user_withdraw  integer NULL,
	tms               timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=innodb;
