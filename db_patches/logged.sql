BEGIN;

CREATE TABLE /*_*/legallogin_logged (
	lll_user int unsigned NOT NULL PRIMARY KEY,
	lll_count int unsigned NOT NULL,
	lll_timestamp varbinary(14) NOT NULL default ''
)/*$wgDBTableOptions*/;

COMMIT;
