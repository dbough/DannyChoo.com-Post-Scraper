<?php
/**
 * dc_include.php
 *
 * Globals and a class needed by dc_post_scraper.php 
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

// Required library.  You can get it from http://sourceforge.net/projects/simplehtmldom/files/.
include "simple_html_dom.php";
// This was needed for some domains.
date_default_timezone_set("America/New_York"); 

// PDO Stuff.  http://www.php.net/manual/en/ref.pdo-mysql.php
define("DB_DSN", "mysql:host=HOSTNAME;dbname=DATABASE_NAME");
define("DB_USERNAME", "DATABASE USER");
define("DB_PASSWORD", "DATABASE PASSWORD");

/*
 Determines how many archive pages to scan.  You can get the total number of 
 archive pages by going to http://www.dannychoo.com/en/posts/page/1.
*/
define("PAGE_DEPTH", "1");

class DC_API {

	/**
	 * Holds database object
	 * @var object
	 */
	var $dbh;
	
	function __construct()
	{	
		/**
		 * Database handle.
		 * @var object
		 */
		$this->dbh = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD);;
	}

	/**
	 * Insert post info into table
	 * @param string $title 
	 * @param string $url
	 */
	function addPost($title, $url)
	{
		// We're only getting the URI.  We need to add the hostname.
		$q =  "INSERT IGNORE INTO `posts` " .
			"(`title`, `url`, `first_found`, `last_updated`) " .
			"VALUES " . 
			"(?, ?, ?, ?)";

		$sth = $this->dbh->prepare($q);
		$sth->execute(array($title, $url, time(), time()));
	}

	/**
	 * Get posts without create dates and descriptions
	 * @return arra
	 */
	function getUnprocessedPosts()
	{
		$q = "SELECT `id`, `url` FROM `posts` " .
			"WHERE `create_date` IS NULL " .
			"AND `description` IS NULL";

		$sth = $this->dbh->prepare($q);
		$sth->execute();
		return $sth->fetchAll();
	}

	/**
	 * Add category info
	 * @param string $name
	 * @param string $url 
	 * @return int
	 */
	function addCategory($name, $url)
	{
		$q = "INSERT IGNORE INTO `categories` " .
			"(`name`, `url`) " . 
			"VALUES " .
			"(?, ?)";

		$sth = $this->dbh->prepare($q);
		$sth->execute(array($name, $url));

		// Get id of last insert
		$lastId = $this->dbh->lastInsertId('id');

		// If the last category was a dupicate, get the ID
		if (!$lastId) {
			$lastId = $this->getCategoryId($name);
		}

		return $lastId;

	}

	/**
	 * Get category id by name.
	 * @param  string $name
	 * @return int
	 */
	function getCategoryId($name)
	{
		$q = "SELECT `id` FROM `categories` " .
			"WHERE `name` = ? ";

		$sth = $this->dbh->prepare($q);
		$sth->execute(array($name));

		return $sth->fetchColumn();

	}

	/**
	 * Update post with meta data.
	 * @param  int $id
	 * @param  string $desc
	 * @param  int $catId
	 * @param  int $date
	 */
	function updatePost($id, $desc, $catId, $date)
	{
		$q = "UPDATE `posts` SET " .
			"`description` = ?, " .
			"`category_id` = ?, " .
			"`create_date` = ?, " .
			"`last_updated` = ? " .
			"WHERE `id` = ?";

		$sth = $this->dbh->prepare($q);
		$sth->execute(array($desc, $catId, $date, time(), $id));
	}

	/**
	 * Determine if URL is accessible.
	 * @param  string $url
	 * @return bool
	 */
	function checkUrl($url)
	{
		$headers = @get_headers($url);
		if ($headers[0] == "HTTP/1.1 200 OK") {
			return true;
		} 
		else {
			return false;
		}
	}

	/**
	 * Delete a post.
	 * @param  int $id
	 */
	function deletePost($id)
	{
		$q = "DELETE FROM `posts` " .
			"WHERE `id` = ?";

		$sth = $this->dbh->prepare($q);
		$sth->execute(array($id));
	}
}
