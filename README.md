DannyChoo.com Post Scraper v0.1.0
=================================
DannyChoo.com Post Scraper is a PHP library that allows you to glean data from DannyChoo.com and store it in a MySQL database.  

It is part of a bigger project that will offer a RESTful API to gather DannyChoo.com post info.

Data Gathered
-------------  
- Post URL
- Post Title
- Post Description
- Post Publish Date
- Post Category  
- Category URL
- Tag Name (future addition)

*This library does NOT gather post images or content!*

Author
------
Dan Bough  
daniel.bough AT gmail.com  
http://www.danielbough.com

License
-------
This software is free to use under the GPLv3 license.

Requirements
------------
- MySQL Database (v5+)
- PHP Simple HTML DOM Parser (http://sourceforge.net/projects/simplehtmldom/files/).

Instructions
------------
- Download the latest version of PHP Simple HTML DOM Parser from http://sourceforge.net/projects/simplehtmldom/files/, which, at the time of this writing, is 1.5.  This should be stored in the same location as `dc_include.php` and `dc_post_scraper.php`. (If you'd like to store it somewhere else, you will need to change the path at the top of `dc_include.php`).
- Add your MySQL database info to `dc_include.php`.
- Create a MySQL database (InnoDB) and import the table structure in `dc_scraper_database.sql`.
- Run `dc_post_scrapper.php`.

Notes
-----
- You can adjust the number of archive pages scanned by updating the `PAGE_DEPTH` global variable in dc_include.php.  The default is 1, however there are (at the time of this writing) at least 179 pages. 
- The more archive pages you scan at a time, the longer this script will take.  There are nearly 6000 posts - it could take a few hours to parse them all.  If you don't mind waiting, I'd set the `PAGE_DEPTH` variable to the max number of pages, run the script, and walk away.  After you have them all, change the `PAGE_DEPTH` to 1 and run the script once per day.



