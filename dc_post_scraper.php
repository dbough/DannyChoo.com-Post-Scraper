<?php
/**
 * dc_post_scraper.php
 *
 * Gets post data from DannyChoo.com and stores it in a MySQL database.
 *
 * @author Dan Bough daniel.bough@gmail.com / http://www.danielbough.com
 * @copyright Copyright (C) 2010-2013
 * @version  0.1.0
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA
 * 
 */

// Required library
include "dc_include.php";

//The DC_API class holds methods we'll need.
$dcApi = new DC_API();

/*
    First we want to gather URLs and titles.  
    The PAGE_DEPTH global determines how many archive pages we scrape - default is 1.
    You can browse to the first page of the archive to determine how many total pages there are:

    http://www.dannychoo.com/page/en/post/all/all/all/all/all/all/1.html

 */
for($i=1;$i<=PAGE_DEPTH;$i++) {
    // Create an object out HTML
    $html = file_get_html('http://www.dannychoo.com/page/en/post/all/all/all/all/all/all/' . $i . '.html');

    /*
      We now look for list elements with a class name that starts with "post-".  Example element:

        <li class="post-26974">
            <a href="/en/post/26974/Anime+Festival+Asia+Indonesia+2013.html" class="thumbnail post" style="width:75px;height:75px;" title="Anime Festival Asia Indonesia 2013"><img alt="Anime Festival Asia Indonesia 2013" height="75" src="http://farm4.staticflickr.com/3691/9148608379_b1cab35df6_s.jpg" width="75" /></a>
            <div class="caption">
                <h5><a href="/en/post/26974/Anime+Festival+Asia+Indonesia+2013.html" title="Anime Festival Asia Indonesia 2013">Anime Festival Asia Indonesia 2013</a></h5>
                <p class="summary">See you at Anime Festival Asia Indonesia 2013 this September 6,7,8 which takes place at the Jakarta Con...</p>
                <div class="meta">
                    <div class="published-at">Thu 13/06/27</div>
                    <a href="/en/post/26974/Anime+Festival+Asia+Indonesia+2013.html#comments" class="comments-link"><i class="icon-comment"></i> 38</a> <div class="pageviews "><i class="icon-fire"></i> 95580</div>
                </div>
            </div>
        </li>
     */
    foreach($html->find('li[class^="post-"]') as $element) {
        /*
            Some list elements contain the "with-badge" class.  We don't want info from those!

            <li class="post-26974 with-badge">
         */
        if (strpos($element->class, "with-badge")) {
            break;
        }

        /*
            The URL and Title can be retreived from an anchor element with the "thumbnail post" class:

            <a href="/en/post/26974/Anime+Festival+Asia+Indonesia+2013.html" class="thumbnail post" style="width:75px;height:75px;" title="Anime Festival Asia Indonesia 2013">
         */
        $a = $element->find('a[class="thumbnail post"]');
        if ($a && $a[0]->title && !strpos($a[0]->href, "The+page+you+was+looking+for+was+eaten+but+check+these+out+instead")) {
            $title = $a[0]->title;
            $url = $a[0]->href;
        }

        // Insert results into the database
        // @todo - Add error logging.
        $dcApi->addPost($title, $url);
    }

    /*
        Clear the last $html object.  Needed due to a 
        PHP 5 memory leak:  http://simplehtmldom.sourceforge.net/manual_faq.htm#memory_leak
     */
    $html->clear();
    unset($html);
}


/*
    Now that we've populated basic post data we need to fill in the gaps.
    Build an array of post IDs and URLs that do not have create dates or descriptions.  
*/
$posts = $dcApi->getUnprocessedPosts();

foreach ($posts as $post) {
    // Build an object from html
    $html = file_get_html($post['url']);

    /*
       Get the post description.  Example:

       <meta name="description" 
       content="Are you ready for Anime Expo 2013? I&#x27;m not! 
       The reason is that there is so much going on at AX that one cant 
       possibly be ready for the onslaught of Japanese animation, industry guests, concerts, merchandise, panels.
       We expect about 130,000 attende..."> 

     */
    $desc = $html->find('meta[name=description]', 0)->content;

    /*
        Get category info.  Example:
        <div class="category"><a href="/en/posts/category/booth">Culture Japan Booth</a></div>
     */
    $categoryInfo = $html->find('div[class=category]', 0);
    if (is_object($categoryInfo)) {
        if (strpos($categoryInfo->find('a', 0)->href, "/en/posts/category") !== false) {
            $catUrl = $categoryInfo->href;
            $catName = $categoryInfo->plaintext;
            $catId = $dcApi->addCategory($catName, $catUrl);
        }
    }
    
    /*
        Get create date as a unix timestamp.  Example:
        <div class="published-at">Wed 2013/07/03 04:37 JST</div>
     */
    $date = strtotime($html->find('div[class="published-at"]', 0)->plaintext);

    // Update post with new data.
    $dcApi->updatePost($post['id'], $desc, $catId, $date);

    /*
        Clear the last $html object.  Needed due to a 
        PHP 5 memory leak:  http://simplehtmldom.sourceforge.net/manual_faq.htm#memory_leak
     */
    $html->clear();
    unset($html);
}

