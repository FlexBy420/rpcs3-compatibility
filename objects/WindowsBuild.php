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
if (!@include_once(__DIR__."/../functions.php")) throw new Exception("Compat: functions.php is missing. Failed to include functions.php");


class WindowsBuild {

  public $pr;         // Int
  public $commit;     // String (40)
  public $author;     // String
  public $authorID;   // Int
  public $merge;      // Datetime
  public $version;    // String
  public $additions;  // Int
  public $deletions;  // Int
  public $files;      // Int
  public $checksum;   // String
  public $size;       // Int
  public $filename;   // String

  public $sizeMB;     // Float
  public $fulldate;   // String
  public $diffdate;   // String
  public $url;        // String


  function __construct($pr, $commit, $authorID, $merge, $version, $additions, $deletions, $files, $checksum, $size, $filename) {

    global $c_appveyor;

    $db = getDatabase();

    $this->pr = (Int) $pr;
    $this->commit = (String) $commit;

    // Gets author username from ID
    $this->author = (String) mysqli_fetch_object(mysqli_query($db, "SELECT username FROM `contributors` WHERE id = {$authorID};"))->username;
    $this->authorID = $authorID;

    $this->merge = $merge;

    $this->version = substr($version, 0, 4) == "1.0." ? str_replace("1.0.", "0.0.0-", $version) : $version;

    // A bug in GitHub API returns +0 -0 on some PRs
    if (!is_null($files) && $files > 0) {
      $this->additions = (Int) $additions;
      $this->deletions = (Int) $deletions;
      $this->files = (Int) $files;
    } else {
      $this->additions = '?';
      $this->deletions = '?';
      $this->files = '?';
    }

    if (!is_null($checksum)) {
      $this->checksum = (String) $checksum;
    }

    if (!is_null($size)) {
      $this->size = (Int) $size;
      $this->sizeMB = !is_null($this->size) ? round(((int)$this->size) / 1024 / 1024, 1) : 0;
    }

    if (!is_null($filename)) {
      $this->filename = $filename;
    }

    $this->fulldate = date_format(date_create($this->merge), "Y-m-d");
    $this->diffdate = getDateDiff($this->merge);

    // AppVeyor builds expire after 6 months
    if (strtotime($this->merge) + 15640418 > time() || strtotime($this->merge) > 1528416000) {
      // All PRs starting 2018-06-02 are hosted on rpcs3/rpcs3-binaries-win
      $this->url = strtotime($this->merge) > 1528416000 ? "https://github.com/RPCS3/rpcs3-binaries-win/releases/download/build-{$this->commit}/{$this->filename}" : "{$c_appveyor}{$version}/artifacts";
    }

    mysqli_close($db);

  }


  public static function rowToBuild($row) {
    return new WindowsBuild($row->pr, $row->commit, $row->author, $row->merge_datetime, $row->appveyor, $row->additions, $row->deletions, $row->changed_files, $row->checksum, $row->size, $row->filename);
  }

  public static function getLast() {
    $db = getDatabase();
    $query = mysqli_query($db, "SELECT * FROM builds_windows ORDER BY merge_datetime DESC LIMIT 1;");
    if (mysqli_num_rows($query) === 0) return null;
    $row = mysqli_fetch_object($query);
    mysqli_close($db);

    return self::rowToBuild($row);
  }

}