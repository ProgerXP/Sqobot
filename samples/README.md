# Sqobot Samples

This directory can be removed, it is not necessary for Sqobot.

## booru

Image crawler. Fetches tagged image data from Danbooru (http://danbooru.donmai.us) and Gelbooru (http://gelbooru.com), also generating thumbnails along the way. All `.php` scripts are put to `user/`.

## emls.ru

A Russian realty trade resource. `db.sql` - slice of sample crawled data. `el1-1.html` - prefetched example of the server page being parsed by this script (put to `user/`). `main.conf` - config file (put in Sqobot root directory). `SEmlsList.php` - the actual crawler/parser (put to `user/`).

## iccup

Retrieves online game match statistics from http://iccup.com. Is seeded with a ladder URL (`SIccupLadder.php`) where it locates the matches (`SIccupMatches.php`), finally parsing all the necessary data from individual games (`SIccupMatch.php`).