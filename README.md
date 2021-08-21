# getvod

getvod manages the directory structure for your Emby/Plex strm files. By default it adds the dicrectory structure for every STRM in your m3u playlist.  Getvod will delete empty directories and streams that are no longer in the provider list.  

For this reason, do not use this with existing directories with media files.

Need help ? Contact us on https://www.facebook.com/rockmym3u or email help@rockmym3u.com

# setup 

Edit the script with your favorite editor and change the following varialbes:

$tv="/media/vod_tv/"; //Library for TV Shows & Must have trailing /

$movie="/media/vod_movie/"; // Library for Movies & Must have trailing /

$mwlistsurl = 'https://mwlists.com/m3u/strm.php?key=xxxxxx';    //where xxxxx is your mwlists m3u playlist or rockmym3u.com link

$includegroup = false; //include group name in folder name

Run the script daily:

php getvod.php

# License 

GetVod is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version. GetVod is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details. You should have received a copy of the GNU General Public License along with GetVod. If not, see http://www.gnu.org/licenses/.
