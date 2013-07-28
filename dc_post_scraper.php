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

// The DC_API class holds methods we'll need.
$dcps = new DCPS();

/*
    First we want to gather URLs and titles.  
    The PAGE_DEPTH global determines how many archive pages we scrape - default is 1.
    You can browse to the first page of the archive to determine how many total pages there are:

    http://www.dannychoo.com/en/posts/page/1 

 */
for($i=1;$i<=PAGE_DEPTH;$i++) {
    if ($i == 1) {
        $url = "http://www.dannychoo.com/en/posts";
    }
    else {
        $url = "http://www.dannychoo.com/en/posts/page/" . $i;
    }

    // Make sure web page exists.
    $urlExists = $dcps->checkUrl($url);
    if (!$urlExists) {
        continue;
    }

    // Create an object out HTML
    $html = file_get_html($url);

    /*
        We want to stop this loop if there is a problem getting 
        html.  Prevents fatal errors.
    */
    if (!$html || !is_object($html)) {
        continue;
    }

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
            continue;
        }

        /*
            The URL and Title can be retreived from an anchor element with the "thumbnail post" class:

            <a href="/en/post/26974/Anime+Festival+Asia+Indonesia+2013.html" class="thumbnail post" style="width:75px;height:75px;" title="Anime Festival Asia Indonesia 2013">
         */
        $a = $element->find('a[class="thumbnail post"]');
        if ($a && $a[0]->title) {
            $title = $a[0]->title;
            $postUrl = $a[0]->href;
        }

        // Insert results into the database.
        if ($title && $dcps->checkUrl("http://www.dannychoo.com" . $postUrl)) {
            $dcps->addPost($title, $postUrl);
        }
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
$posts = $dcps->getUnprocessedPosts();

foreach ($posts as $post) {
    $url = "http://www.dannychoo.com" . $post['url'];

    // Make sure web page exists.
    $urlExists = $dcps->checkUrl($url);
    if (!$urlExists) {
        // If it doesn't, delete it from the posts table.
        $dcps->deletePost($post['id']);
        continue;
    }

    // Build an object from html
    $html = file_get_html($url);
    
    /*
        We want to stop this loop if there is a problem getting 
        html.  Prevents fatal errors.
     */
    if (!$html || !is_object($html)) {
        continue;
    }

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
    $category = $html->find('div[class=category]', 0);
    $categoryInfo = (is_object($category)) ? $category->find('a', 0) : NULL;
    
    if ($categoryInfo && is_object($categoryInfo)) {
        if (strpos($categoryInfo->href, "/en/posts/category") !== false) {
            $catUrl = $categoryInfo->href;
            $catName = $categoryInfo->plaintext;
            // We only want category info if the URL AND Name exist.
            if ($catUrl && $catName) {
                $catId = $dcps->addCategory($catName, $catUrl);
            }
        }
    }
    else {
        $catId = NULL;
    }
    
    /*
        Get create date as a unix timestamp.  Example:
        <div class="published-at">Wed 2013/07/03 04:37 JST</div>
     */
    $date = $html->find('div[class="published-at"]', 0);
    $createDate = (is_object($date)) ? strtotime($date->plaintext) : NULL;

    // Update post with new data.
    $dcps->updatePost($post['id'], $desc, $catId, $createDate);

    /*
        Clear the last $html object.  Needed due to a 
        PHP 5 memory leak:  http://simplehtmldom.sourceforge.net/manual_faq.htm#memory_leak
     */
    $html->clear();
    unset($html);
}

