CREATE TABLE `gbv_stat` (
  `identnum` bigint(20) unsigned NOT NULL,
  `identrep` enum(_repoidentifiers_) collate ascii_bin NOT NULL,
  `date` date NOT NULL,
  `counter` int(11) unsigned NOT NULL,
  `counter_abstract` int(11) unsigned NOT NULL,
  `robots` int(11) unsigned NOT NULL,
  `country` char(2) collate ascii_bin NOT NULL default '--',
  UNIQUE KEY `identnum` (`identnum`,`identrep`,`date`)
) ENGINE=MyISAM DEFAULT CHARSET=ascii COLLATE=ascii_bin;

CREATE TABLE `gbv_stat_files` (
  `file` char(128) collate ascii_bin NOT NULL,
  `mdtm` datetime default NULL COMMENT 'Last modification timestamp of source file according to http header',
  `date` date NOT NULL,
  `hash` char(40) collate ascii_bin NOT NULL,
  `proc` datetime NOT NULL,
  PRIMARY KEY  (`file`)
) ENGINE=MyISAM DEFAULT CHARSET=ascii COLLATE=ascii_bin;