<?php
/**
 * dc_post_scraper.php
 *
 * Gets post data from DannyChoo.com and stores it in a MySQL database.
 *
 * @author Dan Bough daniel.bough@gmail.com / http://www.danielbough.com
 * @copyright Copyright (C) 2013-2015
 * @version  0.2.1
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

$options = getopt('d');

$debug = (isset($options['d'])) ? true : false;

// The DC_API class holds methods we'll need.
$dcps = new DCPS();

/*
    First we want to gather URLs and titles.  
    The PAGE_DEPTH global determines how many archive pages we scrape - default is 1.
    You can browse to the first page of the archive to determine how many total pages there are:

    http://www.dannychoo.com/en/posts/page/1 

 */
if ($debug) error_log("PAGE_DEPTH:  " . PAGE_DEPTH);
for($i=1;$i<=PAGE_DEPTH;$i++) {
    if ($i == 1) {
        $url = "http://www.dannychoo.com/en/posts";
    }
    else {
        $url = "http://www.dannychoo.com/en/posts/page/" . $i;
    }

    if ($debug) error_log("Checking archive page for posts.  url: " . $url);

    // Make sure web page exists.
    $urlExists = $dcps->checkUrl($url);
    if (!$urlExists) {
        if ($debug) error_log("url: " . $url . " does not exist!  Moving on.");
        if (PAGE_DEPTH >= $i) {
            if ($debug) error_log("Done.");
            exit;
        }
        continue;
    }

    // Create an object out HTML
    $html = new simple_html_dom();
    $html->load_file($url);

    /*
        We want to stop this loop if there is a problem getting 
        html.  Prevents fatal errors.
    */
    if (!$html || !is_object($html)) {
        if ($debug) error_log("Failed to load " . $url . " into an object!");
        continue;
    }

    /*
      We now look for list elements with a class name that starts with "post-".  Example element:
        <div class="col-xs-6 col-sm-6 col-md-3 post-27287 thumbnail-small">
          <a href="/en/post/27287/Smart+Doll+Plus.html" title="Smart Doll Plus">
          <img alt="Smart Doll Plus" class="thumbnail-img" src="//images.dannychoo.com/cgm/images/post/20150331/27287/186107/medium/f02024d7614131c22a673d6163684672.jpg" />
          </a>      <div class="caption">
          <h4>
            <a href="/en/post/27287/Smart+Doll+Plus.html" title="Smart Doll Plus">
              Smart Doll Plus
            </a>
          </h4>
        </div>
     */
    $foundPost = false;
    foreach($html->find('[class^="post-"]') as $element) {
        /*
            The URL and Title can be retreived from an H4 anchor element 
            <h4>
              <a href="/en/post/27287/Smart+Doll+Plus.html" title="Smart Doll Plus">
                Smart Doll Plus
              </a>
            </h4>
         */
        $a = $element->find('h4',0)->find('a',0);
        if ($a && $a->title) {
            if ($debug) error_log("Found post!  title: " . $a->title . " url: " . $a->href);
            $foundPost = true;
        }

        // Insert results into the database.
        if ($a->title && $dcps->checkUrl("http://www.dannychoo.com" . $a->href)) {
            if ($debug) error_log("Storing post!  title: " . $a->title . " url: " . $a->href . ".  Also removing create_date and description!");
            $dcps->addPost($a->title, $a->href);
        }
        else {
            if ($debug) error_log("Unable to access post URL: " . $a->href);
        }
    }

    /*
        Clear the last $html object.  Needed due to a 
        PHP 5 memory leak:  http://simplehtmldom.sourceforge.net/manual_faq.htm#memory_leak
     */
    $html->clear();
    unset($html);
    if ($debug && !$foundPost) {
        error_log("Unable to locate posts.  Exiting!");
        exit;
    }
}


/*
    Now that we've populated basic post data we need to fill in the gaps.
    Build an array of post IDs and URLs that do not have create dates or descriptions.  
*/
$posts = $dcps->getUnprocessedPosts();

if ($debug) error_log("Getting info for " . count($posts) . " posts.");

foreach ($posts as $post) {
    $url = "http://www.dannychoo.com" . $post['url'];
    if ($debug) error_log("Geting meta info from " . $url);

    // Make sure web page exists.
    $urlExists = $dcps->checkUrl($url);
    if (!$urlExists) {
        if ($debug) error_log("url: " . $url . " does not exist!  Deleting it and moving on.");
        // If it doesn't, delete it from the posts table.
        $dcps->deletePost($post['id']);
        continue;
    }

    // Build an object from html
    $html = new simple_html_dom();
    $html->load_file($url);
    
    /*
        We want to stop this loop if there is a problem getting 
        html.  Prevents fatal errors.
     */
    if (!$html || !is_object($html)) {
        if ($debug) error_log("Failed to load " . $url . " into an object!");
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

        OR

        <div class="category-trail"><a href="/en/posts/category/visit">Places to visit in Japan</a></div>
     */
    $category = ( $html->find('[class=category]', 0) ) ? $html->find('[class=category]', 0) : $html->find('[class=category-trail]', 0);

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
    $date = $html->find('[class="published-at"]', 0);
    $createDate = (is_object($date)) ? strtotime($date->plaintext) : NULL;

    /*
        Get the url of the first photo in the post
        <img alt="8137536450_8d09b94cb7_o" class="main" height="523" src="http://farm9.static.flickr.com/8045/8137536450_8d09b94cb7_o.jpg" width="930" />
     */
    $photo = $html->find('img[class="main"]', 0);
    $photoUrl = ($photo && is_object($photo)) ? $photo->src : NULL;

    // Update post with new data.
    $dcps->updatePost($post['id'], $desc, $photoUrl, $catId, $createDate);

    /*
        Clear the last $html object.  Needed due to a 
        PHP 5 memory leak:  http://simplehtmldom.sourceforge.net/manual_faq.htm#memory_leak
     */
    $html->clear();
    unset($html);
}
if ($debug) error_log("Done!");