DannyChoo.com Post Scraper v0.2.1
=================================
DannyChoo.com Post Scraper is a PHP library that allows you to glean data from DannyChoo.com and store it in a MySQL database.  

Check out http://dc-api.danielbough.com for an API that uses data gathered by this library.

Data Gathered
-------------  
- Post URL
- Post Title
- Post Description
- Post Publish Date
- Post Category  
- Category URL
- First Photo URL

Author
------
Dan Bough  
daniel.bough AT gmail.com  
http://www.danielbough.com

License
-------
This software is free to use under the GPL license.  
http://www.gnu.org/licenses/gpl-3.0.txt

Requirements
------------
- MySQL Database (v5+)
- PHP Simple HTML DOM Parser (http://sourceforge.net/projects/simplehtmldom/files/).

Instructions
------------
- Download the latest version of PHP Simple HTML DOM Parser from http://sourceforge.net/projects/simplehtmldom/files/, which, at the time of this writing, is 1.5.  This should be stored in the same location as `dc_include.php` and `dc_post_scraper.php`. (If you'd like to store it somewhere else, you will need to change the path at the top of `dc_include.php`).
- Add your MySQL database info to `dc_include.php`.
- Create a MySQL database (InnoDB) and import the table structure in `dc_scraper_database.sql`.
- Run `dc_post_scrapper.php` (add `-d` for debug output).

Notes
-----
- You can adjust the number of archive pages scanned by updating the `PAGE_DEPTH` global variable in dc_include.php.  The default is 1, however there are (at the time of this writing) at least 179 pages. 
- The more archive pages you scan at a time, the longer this script will take.  There are nearly 6000 posts - it could take a few hours to parse them all.  If you don't mind waiting, I'd set the `PAGE_DEPTH` variable to the max number of pages, run the script, and walk away.  After you have them all, change the `PAGE_DEPTH` to 1 and run the script once per day.
- It takes approximately 5 minutes to scrape & store 1 archive page worth of posts (36 max).

Changelog
---------

**v0.2.1**

- Change the format of the Changelog section of the README file.
- Fixed a few bugs caused by HTML changes on DannyChoo.com.
- Added basic debug functionality to `dc_post_scraper.php`

**v0.2.0**
- Added code to grab the first photo url of a post.  Cleaned up some typo-s.
