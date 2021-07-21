<?php
/**
 * @package     GetVod
 * @author      John Martin (help@mwlists.com)
 * @copyright   (C) 2001 - 2021 MWLISTS.COM.  All rights reserved.
 * @license     http://www.gnu.org/licenses/old-licenses/gpl-2.0-standalone.html
 * @link        https://mwlists.com
 *
GetVod is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
GetVod is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
You should have received a copy of the GNU General Public License along with GetVod. If not, see http://www.gnu.org/licenses/.
 */

ini_set('memory_limit', '4G'); // upper memory limit for large stream file
//
// Modify these variables for your setup
//
$tv="/media/vod_tv/"; //Library for TV Shows &  Must have trailing /
$movie="/media/vod_movie/"; // Library for Movies & Must have trailing /

$mwlistsurl = 'https://mwlists.com/m3u/strm.php?key=xxxxxxxx';  // change xxxxxxxx to your Playlist Key
$includegroup = false; //include group name in folder name

//
//stuff to remove
//
$lefttrim = "4K-"; // trim this text from the left of the file name
$remove[] = "'"; //remove stuff we don't want in the file name
$remove[] = '"';
$remove[] = '-';

///////////////////////////////////////////////////////////////////////////////////////////////////////
//                                   Do not Modify below this point                                 //
//////////////////////////////////////////////////////////////////////////////////////////////////////
$type = 0; //type of entry 0 for tv 1 for movie
$tvitems = array();  //an array of the tv items from mwlists
$movieitems = array(); //same for movies

// not needed for production
//register_shutdown_function("fatal_handler");

function fatal_handler()
{
    $errfile = "unknown file";
    $errstr  = "shutdown";
    $errno   = E_CORE_ERROR;
    $errline = 0;

    $error = error_get_last();

    if ($error !== null) {
        $errno   = $error["type"];
        $errfile = $error["file"];
        $errline = $error["line"];
        $errstr  = $error["message"];
        print("Error: Line=$errline, message: $errstr \n\n");
    }
}

//
// validate the settings
//
if (!is_dir($tv)) {
    print("\n Directory ($tv) does not exist\n\n");
    die();
}

if (!is_dir($movie)) {
    print("\n Directory ($movie) does not exist\n\n");
    die();
}

$dirtv = getDirContents($tv);// directory contents tv only .strm files will be added to array
$dirmovie = getDirContents($movie); //directory contents movie only .strm files will be added to array`

try {
    $json = file_get_contents($mwlistsurl);
    $obj = json_decode($json);
} catch (exception $e) {
    print("\n Tregedy struck contacting MWLISTS.COM, try again later\n\n");
    echo $e->getMessage();
    print("\n\n");
    die();
}

if (json_last_error() != 0) {
    print("\n File does not seem to be a JSON file\n\n");
    die();
}

if ($json === false) {
    print("\n Error geting file from MWLISTS\n\n");
    die();
}

if (count($obj)<100) { //less than 100 VODs maybe it died?
    print("\n File seems incomplete\n\n");
    die();
}

//
//Loop through the list from MWLISTS and create the file for emby to read
//
foreach ($obj as $key) {
    $file=str_replace(' ', '_', $key->tvg_name);
    $file=str_replace($remove, "", $file);
    $group=str_replace(' ', '_', $key->group_title);
    $group=str_replace($remove, "", $group);
    $url=$key->url;

    if (preg_match("/S(?:100|\d{1,2})/", $file)) { // this is a TV Episode
        $type = 0;//tv

        $x = preg_split('/S(?:100|\d{1,2})/', $file);
        $n = rtrim($x[0], '_'); // this is the name of the series

        preg_match_all('/S(?:100|\d{1,2})/', $file, $m);
        $s = $m[0][0];// this is the season number

        //printf("Series Name: $n  Season: $s Stream url: $url\n");
        if ($includegroup) {
            $dir =$tv.$group.'/'.$n.'/'.$s.'/'; // this is the full directory path the season episode
        } else {
            $dir =$tv.$n.'/'.$s.'/'; // this is the full directory path the season episode
        }
    } else {
        $type = 1;//movie
        if ($includegroup) {
            $dir = $movie.$group.'/';
        } else {
            $dir = $movie; // for emby we don't need an elaborate path for the movies
        }
    }

    if ($lefttrim != null) { // leave as null and nothing gets removed
        $file = ltrim($file, $lefttrim); //remove left characters
    }
    $safe_filename = Vod::sanitizeFileName($file); // use a safe file name

    //
    //if type is tv, create the structure and maintain an array of stream names
    //
    if ($type ==0) { //tv items need a season stucture
        $tvitems[] = ($dir.$safe_filename.".strm"); // add the stream file name to an array; use later to delete streams provide does not have

        //
        //create the directory structure
        //
        if ($includegroup) { // group directory name
                  if (!file_exists($tv.$group)) {
                      mkdir($tv.$group, 0777, true); //season name
                  }


            if (!file_exists($tv.$group.'/'.$n)) {
                mkdir($tv.$group.'/'.$n, 0777, true); //season name
            }

            if (!file_exists($tv.$group.'/'.$n.'/'.$s)) {
                mkdir($tv.$group.'/'.$n.'/'.$s, 0777, true); //season i.e. 01
            }
        } else {
            //
            //
            if (!file_exists($tv.$n)) {
                mkdir($tv.$n, 0777, true); //season name
            }

            if (!file_exists($tv.$n.'/'.$s)) {
                mkdir($tv.$n.'/'.$s, 0777, true); //season i.e. 01
            }
        }
    } else {
        $movieitems[] = ($dir.$safe_filename.".strm"); //the streams are all in the same directory; add the name of the stream to an array
        if ($includegroup) { // group directory name
            if (!file_exists($movie.$group)) {
                mkdir($movie.$group, 0777, true); //group title in directory name
            }
        }
    }

    if (!is_file($dir.$safe_filename.".strm")) { // if the stream does not exist create it and add the url
        print("Created: ".$dir.$safe_filename.".strm\n");
        file_put_contents($dir.$safe_filename.".strm", $url);     // Save our content to the file.
    }
}

//
//delete tv strm not in provider
//
foreach ($dirtv as $n) {
    if (!(in_array($n, $tvitems))) {
        print_r("Delete $n\n");//the getDirContents only collects .strm files, therefore we don't have to check the file name we are about to delete
        unlink($n);
    }
}

//
//delete movie strm not in provider
//
foreach ($dirmovie as $n) {
    if (!(in_array($n, $movieitems))) {
        print_r("Delete $n\n"); //the getDirContents only collects .strm files, therefore we don't have to check the file name we are about to delete
        unlink($n);
    }
}

return;

//
//Built in php functions are too slow; so we built our own
//
function getDirContents($dir, &$results = array())
{
    $files = scandir($dir);

    foreach ($files as $key => $value) {
        $path = realpath($dir . DIRECTORY_SEPARATOR . $value);
        if (!is_dir($path)) {
            if (strpos($path, '.strm')) { // only stream files are checked and deleted
                $results[] = $path;
            }
        } elseif ($value != "." && $value != "..") {
                getDirContents($path, $results);
                RemoveEmptySubFolders($path); //remove empty directories
        }
    }

    return $results;
}

function RemoveEmptySubFolders($path)
{
  $empty=true;
  foreach (glob($path.DIRECTORY_SEPARATOR."*") as $file)
  {
     $empty &= is_dir($file) && RemoveEmptySubFolders($file);
  }
  if($empty){
          printf("Remove Empty Directory". $path . "\n");
  }
  return $empty && rmdir($path);
}

class Vod
{
    /**
     * Returns a safe filename, for a given platform (OS), by replacing all
     * dangerous characters with an underscore.
     *
     * @param string $dangerous_filename The source filename to be "sanitized"
     * @param string $platform The target OS
     *
     * @return Boolean string A safe version of the input filename
     */
    public static function sanitizeFileName($dangerous_filename, $platform = 'Unix')
    {
        if (in_array(strtolower($platform), array('unix', 'linux'))) {
            // our list of "dangerous characters", add/remove characters if necessary
            $dangerous_characters = array(" ", '"', "'", "&", "/", "\\", "?", "#");
        } else {
            // no OS matched? return the original filename then...
            return $dangerous_filename;
        }

        // every forbidden character is replace by an underscore
        $dangerous_filename = ltrim($dangerous_filename,'.');   //remove . although legel is a hidden file in Linux
        return str_replace($dangerous_characters, '_', $dangerous_filename);
    }
}
