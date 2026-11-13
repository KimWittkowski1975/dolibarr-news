-- Copyright (C) 2026 Kim Wittkowski <kim@wittkowski-it.de>
--
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 3 of the License, or
-- (at your option) any later version.
--
-- Fixed recipient list of a news item. Groups are resolved into their members at
-- send time, so the read list never changes when group membership changes later.
-- source: 'user' = picked directly, 'group' = reached through fk_usergroup, 'all' = "all users"

CREATE TABLE llx_news_recipient (
	rowid          integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	fk_news        integer NOT NULL,
	fk_user        integer NOT NULL,
	source         varchar(8) DEFAULT 'user' NOT NULL,
	fk_usergroup   integer NULL,
	date_read      datetime NULL,
	fk_actioncomm  integer NULL
) ENGINE=innodb;
