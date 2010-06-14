
CREATE TABLE IF NOT EXISTS `ezxapprovelocation_items` (
  `collaboration_id` int(11) NOT NULL default '0',
  `id` int(11) NOT NULL auto_increment,
  `workflow_process_id` int(11) NOT NULL default '0',
  `target_node_ids` varchar(255) NOT NULL default '',
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;

--