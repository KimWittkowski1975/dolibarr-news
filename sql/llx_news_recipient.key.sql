-- Copyright (C) 2026 Kim Wittkowski <kim@wittkowski-it.de>
--
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 3 of the License, or
-- (at your option) any later version.

ALTER TABLE llx_news_recipient ADD UNIQUE INDEX uk_news_recipient (fk_news, fk_user);
-- The unread counter runs on every page, it must be cheap
ALTER TABLE llx_news_recipient ADD INDEX idx_news_recipient_user (fk_user, date_read);
