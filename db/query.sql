DROP TABLE IF EXISTS `plugin_lucenesearch_contents_temp`;

CREATE TABLE `plugin_lucenesearch_contents_temp` (
  `id` VARCHAR(255) NOT NULL,
  `uri` TEXT NOT NULL,
  `host` VARCHAR(255) NOT NULL,
  `content` LONGTEXT NOT NULL ,
  `html` LONGTEXT NOT NULL ,
        PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `plugin_lucenesearch_frontend_crawler_todo`;

CREATE TABLE `plugin_lucenesearch_frontend_crawler_todo` (
  `id` VARCHAR(255) NOT NULL,
  `uri` TEXT NOT NULL,
  `depth` int(11) unsigned,
  `cookiejar` TEXT,
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `plugin_lucenesearch_frontend_crawler_noindex`;

CREATE TABLE `plugin_lucenesearch_frontend_crawler_noindex` (
  `id` VARCHAR(255) NOT NULL,
  `uri` TEXT NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `plugin_lucenesearch_indexer_todo`;

CREATE TABLE `plugin_lucenesearch_indexer_todo` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `content` LONGTEXT NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;