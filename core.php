<?php
/*
    RPCS3.net Compatibility List (https://github.com/AniLeo/rpcs3-compatibility)
    Copyright (C) 2017 AniLeo
    https://github.com/AniLeo or ani-leo@outlook.com

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License along
    with this program; if not, write to the Free Software Foundation, Inc.,
    51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
*/

// Calls for the file that contains the config variables
if (!@include("lib/compat/config.php")) throw new Exception("Compat: config is missing. Failed to include config.php");
// Calls for the file that contains the functions needed
if (!@include("lib/compat/functions.php")) throw new Exception("Compat: functions is missing. Failed to include functions.php");

// Turns off notice/error reporting for regular users
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

// Start of time calculations
$time = microtime();
$time = explode(' ', $time);
$time = $time[1] + $time[0];
$start = $time;

// Initialize variables with the default values
// Results per page [Default: 50 (from config)]
$r = $a_pageresults[$c_pageresults];
// Display status (0-All; 1-Playable; 2-Ingame; 3-Intro; 4-Loadable; 5-Nothing) [Default: 0]
$s = 0;
// Character searched by [Default: none]
$c = "";
// Order by [Default: none]
$o = "";
// Search box content [Default: none]
$sf = "";
// Search by region [Default: none]
$f = "";

$scount = array();

/***
 Obtain values from GET
***/

// Get requested 'r' parameter and convert it to the amount results per page to display
if (isset($_GET['r'])) {
	foreach (range(min(array_keys($a_pageresults)), max(array_keys($a_pageresults))) as $rid) { 
		if ($_GET['r'] == $rid) { $r = $a_pageresults[$rid]; }
	}
	// If 'r' isn't any of the above values or not provided it will remain as default [50]
}

// Get requested 's' parameter and convert it to the status ID
if (isset($_GET['s'])) {
	foreach (range(min(array_keys($a_title)), max(array_keys($a_title))) as $sid) {
		if ($_GET['s'] == $sid) { $s = $sid; }
	}
	// If 's' isn't any of the above values or not provided it will remain as default [0]
}

// Order by
if (isset($_GET['o']) && isValidOrder($_GET['o'])) {
	$o = strtolower($_GET['o']);
}

// Search by character: Get character
if (isset($_GET['c'])) {
	// For each letter between a to z: Check if one is selected
	foreach (range('a', 'z') as $char) {
		// strToLower is there in case someone decides to manually write the URL and use UpperCase chars
		if ($_GET['c'] == strtolower($char)) { $c = strtolower($char); }
	}
	if ($_GET['c'] == "09")  { $c = "09"; }   // Numbers
	if ($_GET['c'] == "sym") { $c = "sym"; } // Symbols
}

// Search box: Get provided input
if (isset($_GET['sf']) && !empty($_GET['sf']) && isValid($_GET['sf'])) {
	$sf = $_GET['sf'];
}

// Search by region
if (isset($_GET['f'])) {
	if ($_GET['f'] == "a" || $_GET['f'] == "h" || $_GET['f'] == "e" || $_GET['f'] == "u" || $_GET['f'] == "j") { $f = strtolower($_GET['f']); }
}


/***
 Database Queries
***/

// Query generation, activate!
$genquery = " WHERE ";

// QUERYGEN: Status
if ($s > min(array_keys($a_title))) { $genquery .= " status = $s "; } 

// QUERYGEN: Character
if ($c != "") {
	if ($c == '09') {
		if ($s > min(array_keys($a_title))) { $genquery .= " AND "; }
		$genquery = $genquery . " (game_title LIKE '0%' OR game_title LIKE '1%' OR game_title LIKE '2%'
		OR game_title LIKE '3%' OR game_title LIKE '4%' OR game_title LIKE '5%' OR game_title LIKE '6%' OR game_title LIKE '7%'
		OR game_title LIKE '8%' OR game_title LIKE '9%') ";
	} elseif ($c == 'sym') {
		if ($s > min(array_keys($a_title))) { $genquery .= " AND "; }
		$genquery = $genquery . " (game_title LIKE '.%' OR game_title LIKE '&%') "; // TODO: Add more symbols when they show up
	} else {
		if ($s > min(array_keys($a_title))) { $genquery .= " AND "; }
		$genquery = $genquery . " game_title LIKE '".$c."%' ";
	}
}

// QUERYGEN: Searchbox
if ($sf != "") {
	if ($s > min(array_keys($a_title)) && $c == "") { $genquery .= " AND "; }
	if ($c != "") { $genquery .= " AND "; }
	$ssf = mysqli_real_escape_string($db, $sf);
	$genquery .= " (game_title LIKE '%".$ssf."%' OR game_id LIKE '%".$ssf."%') ";
}

// QUERYGEN: Search by region
if ($f != "") {
	if ($s > min(array_keys($a_title)) && $c == "") { $genquery .= " AND "; }
	if ($c != "" || $sf != "") { $genquery .= " AND "; }
	$genquery .= " SUBSTR(game_id, 3, 1) = '".$f."' ";
}

// QUERYGEN: Order
if ($o == "") {
	if ($genquery == " WHERE ") { $genquery = " "; }
	$genquery .= " ORDER BY status ASC, game_title ASC ";
} else {
	if ($genquery == " WHERE ") { $genquery = " "; }
	if ($o == "1a") { $genquery .= " ORDER BY game_id ASC "; }
	if ($o == "1d") { $genquery .= " ORDER BY game_id DESC "; }
	if ($o == "2a") { $genquery .= " ORDER BY game_title ASC "; }
	if ($o == "2d") { $genquery .= " ORDER BY game_title DESC "; }
	if ($o == "3a") { $genquery .= " ORDER BY status ASC "; }
	if ($o == "3d") { $genquery .= " ORDER BY status DESC "; }
	if ($o == "4a") { $genquery .= " ORDER BY last_edit ASC "; }
	if ($o == "4d") { $genquery .= " ORDER BY last_edit DESC "; }
}

if ($genquery == " WHERE ") { $genquery = " "; }
// Query generation, end.


// Page calculation according to the user's search
$pagesCmd = "SELECT count(*) AS c FROM ".db_table." $genquery ;";
$pagesQry = mysqli_query($db, $pagesCmd);
$pages = ceil(mysqli_fetch_object($pagesQry)->c / $r);


// Select the count of games in each status
$scquery = array();
foreach (range((min(array_keys($a_title))+1), max(array_keys($a_title))) as $sc) { 
	if ($sf != "") {
		$ssf = mysqli_real_escape_string($db, $sf);
		$scquery[$sc] = "SELECT count(*) AS c FROM ".db_table." WHERE (game_title LIKE '%$ssf%' OR game_id LIKE '%$ssf%') AND status = $sc";
	} else {
		$scquery[$sc] = "SELECT count(*) AS c FROM ".db_table." WHERE status = $sc";
	}
}

foreach (range((min(array_keys($a_title))+1), max(array_keys($a_title))) as $sc) { 
	if ($c != "" && $c != "09" && $c != "sym") {
		$scquery[$sc] .= " AND game_title LIKE '$c%'";
	}
	if ($c == "09") {
		$scquery[$sc] .= " AND (game_title LIKE '0%' OR game_title LIKE '1%' OR game_title LIKE '2%'
		OR game_title LIKE '3%' OR game_title LIKE '4%' OR game_title LIKE '5%' OR game_title LIKE '6%' OR game_title LIKE '7%'
		OR game_title LIKE '8%' OR game_title LIKE '9%') ";
	}
	if ($c == "sym") {
		$scquery[$sc] .= " AND (game_title LIKE '.%' OR game_title LIKE '&%') ";
	}
	$scount[$sc] = mysqli_fetch_object(mysqli_query($db, $scquery[$sc]))->c;
}

// Get the total count of entries present in the database (not subjective to search params)
$games = mysqli_fetch_object(mysqli_query($db, "SELECT count(*) AS c FROM ".db_table))->c;

// Instead of querying the database once more add all the previous counts to get the total count (subjective to search params)
foreach (range((min(array_keys($a_title))+1), max(array_keys($a_title))) as $sc) {
	$scount[0] += $scount[$sc];
}

// Get current page user is on
// And calculate the number of pages according selected status and results per page
if (isset($_GET['p'])) {
	$currentPage = intval($_GET['p']);
	if ($currentPage > $pages) { $currentPage = 1; }		
} else { $currentPage = 1; }

// Run the main query 
$sqlCmd = "SELECT game_id, game_title, build_commit, thread_id, status, last_edit
			FROM ".db_table." "
			.$genquery.
			"LIMIT ".($r*$currentPage-$r).", $r;";
			$sqlQry = mysqli_query($db, $sqlCmd);
			
// End MySQL connection as it won't be required from here on
mysqli_close($db);


/*****************************************************************************************************************************/


/*******************************
 * General: Combined Search    *
 *   Results per Page          *
 *******************************/
foreach (range(min(array_keys($a_pageresults)), max(array_keys($a_pageresults))) as $rid) { 
	// If the value for results per page is valid and other than the default one
	if ($r == $a_pageresults[$rid] && $r != $a_pageresults[$c_pageresults]) { $g_pageresults = "r=$rid&"; } 
}
if (!isset($g_pageresults)) { $g_pageresults = ""; }


/***********
 * Sort By *
 ***********/
function getSortBy() {
	global $a_title, $a_desc, $g_pageresults, $scount, $c, $s, $sf;

	foreach (range(min(array_keys($a_title)), max(array_keys($a_title))) as $i) { 
		// Displays status description when hovered on
		$s_sortby .= "<a title='$a_desc[$i]' href=\"?"; 
		
		// Combined search: results per page
		$s_sortby .= $g_pageresults;
		// Combined search: search by character
		if ($c != "") {$s_sortby .= "c=$c&";}
		// Combined search: searchbox
		if ($sf != "" && $scount[0] > 0)	{$s_sortby .= "sf=".urlencode($sf)."&";} 
		
		$s_sortby .= "s=$i\">"; 
		
		$temp = "$a_title[$i]&nbsp;($scount[$i])";
		
		// If the current selected status, highlight with bold
		if ($s == $i) { $s_sortby .= highlightBold($temp); }
		else { $s_sortby .= $temp; }

		$s_sortby .= "</a>"; 
	}
	return $s_sortby;
}


/********************
 * Results per page *
 ********************/
function getResultsPerPage() {
	global $a_pageresults, $s, $c, $sf, $s_pageresults, $scount, $r;
	
	foreach (range(min(array_keys($a_pageresults)), max(array_keys($a_pageresults))) as $i) { 
		$s_pageresults .= "<a href=\"?"; 
		
		// Combined search: sort by status
		if ($s > min(array_keys($a_title))) {$s_pageresults .= "s=$s&";} 
		// Combined search: search by character
		if ($c != "") {$s_pageresults .= "c=$c&";} 
		// Combined search: searchbox
		if ($sf != "" && $scount[0] > 0) {$s_pageresults .= "sf='".urlencode($sf)."'&";} 
		
		$s_pageresults .= "r=$i\">"; 
		
		// If the current selected status, highlight with bold
		if ($r == $a_pageresults[$i]) { $s_pageresults .= highlightBold($a_pageresults[$i]);} 
		else { $s_pageresults .= $a_pageresults[$i]; }

		$s_pageresults .= "</a>";
		
		// If not the last value then add a separator for the next value
		if ($i < max(array_keys($a_pageresults))) {$s_pageresults .= "&nbsp;•&nbsp;";} 
	}
	return $s_pageresults;
}


/***********************
 * Status descriptions *
 ***********************/
function getStatusDescriptions() {
	global $a_desc, $a_color, $a_title;
	
	foreach (range((min(array_keys($a_desc))+1), max(array_keys($a_desc))) as $i) { 
		$s_descontainer .= '<div id="compat-con-status">
								<div id="compat-ico-status" style="background:#'.$a_color[$i].'"></div>
								<div id="compat-tx1-status"><strong>'.$a_title[$i].':</strong> '.$a_desc[$i].'</div>
							</div>';
	}	
	return $s_descontainer;
}


/***********************************
 * Clickable URL: Character search *
 **********************************/
function getCharSearch() {
	global $g_pageresults, $s, $c, $a_css, $a_title;
	
	/* ALL */
	$s_charsearch .= "<td><a href=\"?";

	// Combined search: results per page
	$s_charsearch .= $g_pageresults;
	// Combined search: search by status
	if ($s > min(array_keys($a_title))) {$s_charsearch .= "s=$s&";} 

	$s_charsearch .= "\"><div id=\"{$a_css["CHARACTER_SEARCH"]}\">"; 
	if ($c == "") { $s_charsearch .= highlightBold("All"); }
	else { $s_charsearch .= "All"; }
	$s_charsearch .= "</div></a></td>"; 

	/* A-Z */
	foreach (range('a', 'z') as $i) { 
		$s_charsearch .= "<td><a href=\"?"; 
		
		// Combined search: results per page
		$s_charsearch .= $g_pageresults;
		// Combined search: search by status
		if ($s > min(array_keys($a_title))) {$s_charsearch .= "s=$s&";} 
		
		$s_charsearch .= "c=$i\"><div id=\"{$a_css["CHARACTER_SEARCH"]}\">";
		if ($c == $i) { $s_charsearch .= highlightBold(strToUpper($i)); }
		else { $s_charsearch .= strToUpper($i); }
		$s_charsearch .= "</div></a></td>"; 
	} 

	/* Numbers */
	$s_charsearch .= "<td><a href=\"?";

	// Combined search: results per page
	$s_charsearch .= $g_pageresults;
	// Combined search: search by status
	if ($s > min(array_keys($a_title))) {$s_charsearch .= "s=$s&";} 

	$s_charsearch .= "c=09\"><div id=\"{$a_css["CHARACTER_SEARCH"]}\">"; 
	if ($c == "09") { $s_charsearch .= highlightBold("0-9"); }
	else { $s_charsearch .= "0-9"; }
	$s_charsearch .= "</div></a></td>"; 
	
	
	/* Symbols */
	$s_charsearch .= "<td><a href=\"?";

	// Combined search: results per page
	$s_charsearch .= $g_pageresults;
	// Combined search: search by status
	if ($s > min(array_keys($a_title))) {$s_charsearch .= "s=$s&";} 

	$s_charsearch .= "c=sym\"><div id=\"{$a_css["CHARACTER_SEARCH"]}\">"; 
	if ($c == "sym") { $s_charsearch .= highlightBold("#"); }
	else { $s_charsearch .= "#"; }
	$s_charsearch .= "</div></a></td>";
	
	return $s_charsearch;
}


/*****************
 * Table Headers *
 *****************/
function getTableHeaders() {
	global $s, $c, $g_pageresults, $sf, $scount, $o;
	
	/* Commonly used code: so we don't have to waste lines repeating this */
	$common .= "<th><a href =\"?";

	// Order support: Sort by status
	if ($s > min(array_keys($a_title))) {$common .= "s=$s&";} 
	// Order support: Results per page
	$common .= $g_pageresults;
	// Order support: Search by character
	if ($c != "") {$common .= "c=$c&";} 
	// Order support: Searchbox
	if ($sf != "" && $scount[0] > 0) {$common .= "sf='".urlencode($sf)."'&";} 
	
	
	/* Game ID */
	$s_tableheaders .= $common;
	// Order by: Game ID (ASC, DESC)
	if ($o == "1a") { $s_tableheaders .= "o=1d\">Game ID &nbsp; &#8593;</a></th>"; }
	elseif ($o == "1d") { $s_tableheaders .= "\">Game ID &nbsp; &#8595;</a></th>"; }
	else { $s_tableheaders .= "o=1a\">Game ID</a></th>"; } 

	/* Game Title */
	$s_tableheaders .= $common;
	// Order by: Game Title (ASC, DESC)
	if ($o == "2a") { $s_tableheaders .= "o=2d\">Game Title &nbsp; &#8593;</a></th>"; }
	elseif ($o == "2d") { $s_tableheaders .= "\">Game Title &nbsp; &#8595;</a></th>"; }
	else { $s_tableheaders .= "o=2a\">Game Title</a></th>"; }

	/* Build Used */
	$s_tableheaders .= "<th>Build Used</th>";

	/* Status */
	$s_tableheaders .= $common;
	// Order by: Status (ASC, DESC)
	if ($o == "3a") { $s_tableheaders .= "o=3d\">Status &nbsp; &#8593;</a></th>"; }
	elseif ($o == "3d") { $s_tableheaders .= "\">Status &nbsp; &#8595;</a></th>"; }
	else { $s_tableheaders .= "o=3a\">Status</a></th>"; }

	/* Last Updated */
	$s_tableheaders .= $common;
	// Order by: Last Updated (ASC, DESC)
	if ($o == "4a") { $s_tableheaders .= "o=4d\">Last Updated &nbsp; &#8593;</a></th>"; }
	elseif ($o == "4d") { $s_tableheaders .= "\">Last Updated &nbsp; &#8595;</a></th>"; }
	else { $s_tableheaders .= "o=4a\">Last Updated</a></th>"; }
	
	return $s_tableheaders;
}


/*****************
 * Table Content *
 *****************/
function getTableContent() {
	global $sqlQry;
	
	if ($sqlQry) {
		if (mysqli_num_rows($sqlQry) > 0) {
			while($row = mysqli_fetch_object($sqlQry)) {
				$s_tablecontent .= '<tr>
				<td>'.getGameRegion($row->game_id).'&nbsp;&nbsp;'.getThread($row->game_id, $row->thread_id).'</td>
				<td>'.getGameMedia($row->game_id).'&nbsp;&nbsp;'.getThread($row->game_title, $row->thread_id).'</td>
				<td>'.getCommit($row->build_commit).'</td>
				<td>'.getColoredStatus($row->status).'</td>
				<td>'.$row->last_edit.'</td>
				</tr>';
			}	
		} // No results is handled on pagesCounter
	} else {
		// Query generator fail error
		$s_tablecontent .= "<p class=\"compat-tx1-criteria\">Please try again. If this error persists, please contact the RPCS3 team.</p>";
	}
	return $s_tablecontent;
}


/*****************
 * Pages Counter *
 *****************/
function getPagesCounter() {
	global $pages, $sf, $currentPage, $s, $c, $o, $g_pageresults, $f;
	
	// IF no results are found then the amount of pages is 0
	// Shows no results found message
	if ($pages == 0) { 
		if ($sf != "") { $s_pagescounter .= "Results for '$sf' Game ID or Game Title not found."; }
		else { $s_pagescounter .= 'No results found using the selected search criteria.'; }
	} 
	// ELSE it shows current page and total pages
	else { $s_pagescounter .= 'Page '.$currentPage.' of '.$pages.' - '; }
			
	// Loop for each page link and make it properly clickable until there are no more pages left
	for ($i=1; $i<=$pages; $i++) { 
		$s_pagescounter .= "<a href=\"?";
		
		// Page support: Sort by status
		if ($s > min(array_keys($a_title))) {$s_pagescounter .= "s=$s&";} 
		// Page support: Results per page
		$s_pagescounter .= $g_pageresults;
		// Page support: Search by character
		if ($c != "") {$s_pagescounter .= "c=$c&";} 
		// Page support: Search by region
		if ($f != "") {$s_pagescounter .= "f=$f&";} 
		// Page support: Order by
		if ($o != "") {$s_pagescounter .= "o=$o&";} 
		
		// Display number of the page
		$s_pagescounter .= "p=$i\">";
		if ($i == $currentPage) { $s_pagescounter .= highlightBold($i); }
		else {$s_pagescounter .= $i; }
		
		$s_pagescounter .= "</a>&nbsp;&#32;"; 
	}
	
	return $s_pagescounter;
}


/*****************************************************************************************************************************/


// End of time calculations
$time = microtime();
$time = explode(' ', $time);
$time = $time[1] + $time[0];
$finish = $time;
$total_time = round(($finish - $start), 4);

?>
