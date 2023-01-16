<?php
/**
 * $Project: GeoGraph $
 * $Id: xmas.php 6235 2009-12-24 12:33:07Z barry $
 * 
 * GeoGraph geographic photo archive project
 * This file copyright (C) 2005 Barry Hunter (geo@barryhunter.co.uk)
 * 
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */

require_once('geograph/global.inc.php');
require_once('geograph/map.class.php');
require_once('geograph/mapmosaic.class.php');
init_session();

$smarty = new GeographPage;


$route = !empty($_GET['id'])?intval($_GET['id']):822;




$map=new GeographMap;
//standard 1px national map
$map->setOrigin(0,-10);
$map->setImageSize(900,1300);
$map->setScale(1);

$map->type_or_user = -10;

$blankurl=$map->getImageUrl();



$map->transparent = true;
$map->topicId = $route;
$map->setPalette(3);
$map->type_or_user = -12;


$target=$_SERVER['DOCUMENT_ROOT'].$map->getImageFilename();

if (!file_exists($target) || filemtime($target) < (time()-3600*6) || (!empty($_GET['refresh']) && $USER->hasPerm("admin")) ) {
	unlink($target);
	$map->_renderMap();
}


$overlayurl=$map->getImageUrl()."&amp;dummy=".filemtime($target);


$smarty->display('_std_begin.tpl');

print "<h2>Route map for Thread #$route</h2>";

print "<p>Map rendered: ".date('M, jS \a\t H:m',filemtime($target))."</p>";

print "<p>Grid-Squares with photo(s) posted in the thread, are shown in red. This includes images from the whole thread, no paging.</p>";

?>
<div class=map style="position:relative; height:1300px;width:900px">
	<div>
		<img src="<? echo $blankurl; ?>"/>
	</div>
	<div>
		<img src="<? echo $overlayurl; ?>" class="main"/>
	</div>
	<? if ($route == 27300 || $route == 32922 || !empty($_GET['p'])) { ?>
		<div style="position:absolute;top:1px;left:0">
			<img src="<? echo $overlayurl; ?>"/>
		</div>
		<div style="position:absolute;top:0;left:1px">
			<img src="<? echo $overlayurl; ?>"/>
		</div>
		<div style="position:absolute;top:0;left:-1px">
			<img src="<? echo $overlayurl; ?>"/>
		</div>
		<div style="position:absolute;top:-1px;left:0">
			<img src="<? echo $overlayurl; ?>"/>
		</div>

	<? } ?>
</div>
<style>
	div.map div {
		position:absolute;top:0;left:0;
	}
	div.map img {
		image-rendering: -webkit-optimize-contrast; image-rendering: crisp-edges;
	}
	div.map img.main {
		filter: drop-shadow(0px 0px 3px black);
	}
	div.map img.main:hover {
                filter: drop-shadow(0px 0px 1px black);
        }
</style>


<?


$smarty->display('_std_end.tpl');


