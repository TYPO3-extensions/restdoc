#
# Table structure for table 'tx_restdoc_tocs'
#
CREATE TABLE tx_restdoc_toc (
	pid int(11) DEFAULT '0' NOT NULL,
	root varchar(255) DEFAULT '' NOT NULL,
	document varchar(255) DEFAULT '' NOT NULL,
	title varchar(255) DEFAULT '' NOT NULL,
	checksum char(32) DEFAULT '' NOT NULL,
	tstamp int(11) unsigned DEFAULT '0' NOT NULL,
	crdate int(11) unsigned DEFAULT '0' NOT NULL,
	lastmod varchar(255) DEFAULT '' NOT NULL,
	url varchar(2048) DEFAULT '' NOT NULL
) ENGINE=InnoDB;