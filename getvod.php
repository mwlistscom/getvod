<?php
/**
 * @package     GetVod
 * @version     1.2 BETA
 * @author      John Martin (help@mwlists.com)
 * @copyright   (C) 2001 - 2022 ROCKMYM3U.COM All rights reserved.
 * @license     http://www.gnu.org/licenses/old-licenses/gpl-2.0-standalone.html
 * @link        https://rockmym3u.com
 *
 *
 * GetVod is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * GetVod is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License along with GetVod. If not, see http://www.gnu.org/licenses/.
 *
 * Version 1.2
 *   Improved download reliability
 *   Various code changes for better performance
 *   Added option to write movie strm files into sub directories
 *   Added curl option, uncomment the current download method if you prefer; you will need curl built into php
 *   Added override for tv and movie; allows you to override the logic to make a TV strm into the movie dir;
 *      we didn't test the tv override
 *   Added more information print statements
 *   Corrected my bad spelling
 *   Bug could not remove directories with [ ] in their name due to stupid php bugs; so if we have a vod with [] in
 *       the name we rename it using ()
 *        This means if you have directories or strm files with a [ or ] in their name you will have the manually delete them
 *
 * Version 1.3
 *  remove if isset from directory check
 */

ini_set('memory_limit', '4G'); // upper memory limit for large stream file
//
// Modify these variables for your setup
//
$tv = "/media/vod_tv/"; //Library for TV Shows &  Must have trailing /
$movie = "/media/vod_movie/"; // Library for Movies & Must have trailing /
$strmdir = "/media/"; // Where we store the strm file
$strm = "rockmym3u.strm"; // the downloaded file
$mwlistsurl = 'https://rockmym3u.com/m3u/strm.php?key=xxxxxxxxx';   //Playlist from RockMyM3U M3U Editor
$includegroup = false; //include group name in folder name
$overwritecontents = false; // this may cause a lengthy media scan depending on your setup; maybe use this once a month ?
$moviedir = false;  //put movies into a directory example : /movies/The_Shawshank_Redemption_(1994)/The_Shawshank_Redemption_(1994).strm
//
//stuff to remove
//
$lefttrim = ""; // trim this text from the left of the file name
$remove[] = "'"; //remove stuff we don't want in the file name
$remove[] = '"';
$remove[] = '-';

//
// Override  one per line
//
$thisisamovie[] = 'S1m0ne';  // https://www.imdb.com/title/tt0258153/
$thisisatvshow[] = '';
///////////////////////////////////////////////////////////////////////////////////////////////////////
//                                   Do not Modify below this point                                  //
//////////////////////////////////////////////////////////////////////////////////////////////////////
$type = 0; //type of entry 0 for tv 1 for movie
$tvitems = array();  //an array of the tv items from mwlists
$movieitems = array(); //same for movies

// not needed for production
//register_shutdown_function("fatal_handler");
function fatal_handler()
{
    $errfile = "unknown file";
    $errstr = "shutdown";
    $errno = E_CORE_ERROR;
    $errline = 0;

    $error = error_get_last();

    if ($error !== null) {
        $errno = $error["type"];
        $errfile = $error["file"];
        $errline = $error["line"];
        $errstr = $error["message"];
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

if (!is_dir($strmdir)) {
    print("\n Directory ($strmdir) does not exist\n\n");
    die();
}


try {
    print("Download : $mwlistsurl\n");
    //
    // Most users don't have curl with their PHP build
    //
    $in = fopen($mwlistsurl, "rb") or die ('Error opening STRM file from RockMyM3U! ');
    $fp = fopen($strmdir . $strm, 'w+') or die ('Error opening STRM file for write ! Check working directory permissions ! ');

    while (!feof($in)) {
        fwrite($fp, fread($in, 8192));
    }
    fclose($fp);
    fclose($in);


    /*
     * Curl Implementation
     *
    set_time_limit(0);
    $path = $strmdir.$strm; // the json strm file we'll download
    $fp = fopen($path, 'w+') or die ('Error opening STRM file for write ! Check working directory permissions ! ');

    $ch = curl_init($mwlistsurl);
    curl_setopt($ch, CURLOPT_FILE, $fp);

    $data = curl_exec($ch);
    if (curl_errno($ch)) {
            echo "the cURL error is : " . curl_error($ch);
            curl_close($ch);
            fclose($fp);
            die;
    }

    curl_close($ch);
    fclose($fp);


     */

    $obj = json_decode(file_get_contents($strmdir . $strm)); // load the file into a object; would probably not work on a pi due to memory
} catch (exception $e) {
    print("\n Tragedy struck contacting RockMyM3U, try again later\n\n");
    echo $e->getMessage();
    print("\n\n");
    die();
}

if (json_last_error() != 0) {
    print("\n File does not seem to be a JSON file\n\n");
    die();
}

if ($obj === null || is_bool($obj)) {
    print("\n Error geting file from MWLISTS; not a json file or file corrupt\n\n");
    die();
}

if (count($obj) < 100) { //less than 100 VODs maybe it died?
    print("\n File seems incomplete less then 100 vod? If so modify source code.\n\n");
    die();
}
print("Processing File for new VOD\n");
//
//Loop through the list from rockmym3u and create the file for emby to read
//
foreach ($obj as $key) {
    $file = str_replace(' ', '_', $key->tvg_name);
    $file = str_replace('[', "(", $file);
    $file = str_replace(']', ")", $file);
    $file = str_replace($remove, "", $file);
    $group = str_replace(' ', '_', $key->group_title);
    $group = str_replace($remove, "", $group);
    $url = $key->url;

    if ($lefttrim != null) { // leave as null and nothing gets removed
        $file = ltrim($file, $lefttrim); //remove left characters
    } // we should probably add a rtrim here but nobody asked so, Bob's your uncle?

    $safe_filename = Vod::sanitizeFileName($file); // use a safe file name

    // in this if else code we are -
    // checking to see if the .strm is a movie or tv show
    // depending on the type we are going to set the directory structure
    // based on the user prefs.  At the end $dir will contain the
    // intended destination for the stream
    //
    if ((preg_match("/S(?:100|\d{1,2})/", $file) || isin($file, $thisisatvshow)) && !(isin($file, $thisisamovie))) { // this is a TV Episode
        $type = 0;//tv

        $x = preg_split('/S(?:100|\d{1,2})/', $file);
        $n = rtrim($x[0], '_'); // this is the name of the series

        preg_match_all('/S(?:100|\d{1,2})/', $file, $m);
        $s = $m[0][0];// this is the season number

        //printf("Series Name: $n  Season: $s Stream url: $url\n");
        if ($includegroup) {
            $dir = $tv . $group . '/' . $n . '/' . $s . '/'; // this is the full directory path the season episode
        } else {
            $dir = $tv . $n . '/' . $s . '/'; // this is the full directory path the season episode
        }
    } else { // this is a Movie
        $type = 1;//movie

        if ($includegroup) {
            if ($moviedir) { // place the strm in it's own folder
                $dir = $movie . $group . '/' . $safe_filename . '/';
            } else {
                $dir = $movie . $group . '/';
            }
        } else {

            if ($moviedir) { // place the strm into it's own folder
                $dir = $movie . $safe_filename . '/'; // for emby we don't need an elaborate path for the movies
            } else {
                $dir = $movie;
            }
        }
    }

    //
    //if type is tv, create the structure and maintain an array of stream names
    //
    if ($type == 0) { //tv items need a season stucture
        $tvitems[] = ($dir . $safe_filename . ".strm"); // add the stream file name to an array; use later to delete streams provide does not have

        //
        //create the directory structure
        //
        if ($includegroup) { // group directory name
            if (!file_exists($tv . $group)) {
                mkdir($tv . $group, 0777, true); //season name
            }


            if (!file_exists($tv . $group . '/' . $n)) {
                mkdir($tv . $group . '/' . $n, 0777, true); //season name
            }

            if (!file_exists($tv . $group . '/' . $n . '/' . $s)) {
                mkdir($tv . $group . '/' . $n . '/' . $s, 0777, true); //season i.e. 01
            }
        } else {
            //
            if (!file_exists($tv . $n)) {
                mkdir($tv . $n, 0777, true); //season name
            }

            if (!file_exists($tv . $n . '/' . $s)) {
                mkdir($tv . $n . '/' . $s, 0777, true); //season i.e. 01
            }
        }
    } else { // this is a movie, so let's create the directories
        $movieitems[] = ($dir . $safe_filename . ".strm"); //Add the name of the stream to an array
        if ($includegroup) { // group directory name
            if (!file_exists($movie . $group)) {
                mkdir($movie . $group, 0777, true); //group title in directory name
            }
            if (!file_exists($movie . $group . '/' . $safe_filename)) {
                mkdir($movie . $group . '/' . $safe_filename, 0777, true); //group title in directory name
            }

        } else {
            if (!file_exists($movie . $safe_filename)) {
                mkdir($movie . $safe_filename, 0777, true); // don't need group title
            }

        }
    }

    // now we have the file name, and the directory it's intened for, lets write the strm file
    if (!is_file($dir . $safe_filename . ".strm")) { // if the stream does not exist create it and add the url
        print("Created: " . $dir . $safe_filename . ".strm\n");
        file_put_contents($dir . $safe_filename . ".strm", $url);     // Save our content to the file.
    } elseif ($overwritecontents) {
        print("Updated: " . $dir . $safe_filename . ".strm\n");
        file_put_contents($dir . $safe_filename . ".strm", $url);     // Save our content to the file.
    }
}

print_r("Crosscheck anything we have the provider deleted\n");

$dirtv = getDirContents($tv);// directory contents tv only .strm files will be added to array
$dirmovie = getDirContents($movie); //directory contents movie only .strm files will be added to array`


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

//print_r("Checking for empty directories in Movie Library\n");
//RemoveEmptySubFolders($dirmovie);
//print_r("Checking for empty directories in TV Library\n");
//RemoveEmptySubFolders($dirtv);

print_r("Finished\n\n");
exit(0);
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

function stringEndsWith($haystack, $needle, $case = true)
{
    $expectedPosition = strlen($haystack) - strlen($needle);
    if ($case) {
        return strrpos($haystack, $needle, 0) === $expectedPosition;
    }
    return strripos($haystack, $needle, 0) === $expectedPosition;
}

function RemoveEmptySubFolders($path)
{
    $empty = true;
    foreach (glob($path . DIRECTORY_SEPARATOR . "*") as $file) {
        $empty &= is_dir($file) && RemoveEmptySubFolders($file);
    }
    if ($empty) {
        printf("Remove Empty Directory : " . $path . "\n");
    }
    return $empty && rmdir($path);
}


function isin($search, $needles)
{ // seach the string of array values
    $find = ($search != str_ireplace($needles, "XX", $search)) ? true : false;
    return ($find);
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
        $dangerous_filename = ltrim($dangerous_filename, '.');   //remove . although legel is a hidden file in Linux
        return str_replace($dangerous_characters, '_', $dangerous_filename);
    }
}
