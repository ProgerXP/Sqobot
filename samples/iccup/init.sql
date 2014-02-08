
CREATE TABLE IF NOT EXISTS `st_queue` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `url` text COLLATE latin1_bin NOT NULL,
  `created` datetime NOT NULL,
  `started` datetime DEFAULT NULL,
  `error` text CHARACTER SET latin1 COLLATE latin1_general_ci NOT NULL,
  `site` varchar(50) COLLATE latin1_bin NOT NULL,
  `extra` text COLLATE latin1_bin NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `urlu` (`url`(50)),
  KEY `error` (`error`(1)),
  KEY `site` (`site`),
  KEY `started` (`started`,`site`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 COLLATE=latin1_bin AUTO_INCREMENT=66518 ;




INSERT INTO `st_queue` (`id`, `url`, `created`, `started`, `error`, `site`, `extra`) VALUES
(1, 'http://ru.iccup.com/dota/ladder/5x5.html', '2013-03-09 09:24:15', NULL, 'Done.', 'iccup.ladder', ''),
(2, 'http://ru.iccup.com/dota/ladder/5x5/page2.html', '2013-03-09 09:24:15', NULL, 'Completed OK. Keeping entry as per $keepDone option.', 'iccup.ladder', ''),
(3, 'http://ru.iccup.com/dota/ladder/5x5/page3.html', '2013-03-09 09:24:15', NULL, 'Completed OK. Keeping entry as per $keepDone option.', 'iccup.ladder', ''),
(4, 'http://ru.iccup.com/dota/ladder/5x5/page4.html', '2013-03-09 09:24:15', NULL, 'Completed OK. Keeping entry as per $keepDone option.', 'iccup.ladder', ''),
(5, 'http://ru.iccup.com/dota/ladder/5x5/page5.html', '2013-03-09 09:24:15', NULL, 'Completed OK. Keeping entry as per $keepDone option.', 'iccup.ladder', ''),
(6, 'http://ru.iccup.com/dota/ladder/5x5/page6.html', '2013-03-09 09:24:15', NULL, 'Completed OK. Keeping entry as per $keepDone option.', 'iccup.ladder', ''),
(7, 'http://ru.iccup.com/dota/ladder/5x5/page7.html', '2013-03-09 09:24:15', NULL, 'Completed OK. Keeping entry as per $keepDone option.', 'iccup.ladder', ''),
(8, 'http://ru.iccup.com/dota/ladder/5x5/page8.html', '2013-03-09 09:24:15', NULL, 'Completed OK. Keeping entry as per $keepDone option.', 'iccup.ladder', '');




INSERT INTO `st_queue` (`url`, `created`, `started`, `error`, `site`, `extra`) VALUES
('http://ru.iccup.com/dota/ladder/5x5/page9.html', '2013-03-09 09:24:15', NULL, 'Completed OK. Keeping entry as per $keepDone option.', 'iccup.ladder', ''),
('http://ru.iccup.com/dota/ladder/5x5/page10.html', '2013-03-09 10:02:10', null, '', 'iccup.ladder', ''),
('http://ru.iccup.com/dota/ladder/5x5/page11.html', '2013-03-09 10:08:34', null, '', 'iccup.ladder', ''),
('http://ru.iccup.com/dota/ladder/5x5/page12.html', '2013-03-09 10:08:34', null, '', 'iccup.ladder', ''),
('http://ru.iccup.com/dota/ladder/5x5/page13.html', '2013-03-09 10:08:34', NULL, 'Completed OK. Keeping entry as per $keepDone option.', 'iccup.ladder', '');

