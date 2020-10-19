BEGIN;

CREATE TABLE /*_*/legallogin_accepted (
	lla_user int unsigned NOT NULL,
	lla_name varbinary(255) NOT NULL,
	lla_rev_id int unsigned NOT NULL,
	lla_timestamp varbinary(14) NOT NULL default '',
	PRIMARY KEY upr_page_user_type (lla_user, lla_name)
)/*$wgDBTableOptions*/;

COMMIT;
