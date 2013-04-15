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




	$db = GeographDatabaseConnection(true);
		
	$where = '';
	$andwhere = '';

	if (isset($_GET['prefix'])) {

		$andwhere = " AND prefix = ".$db->Quote($_GET['prefix']);
		$smarty->assign('theprefix', $_GET['prefix']);
	}

	if (!empty($_GET['tag'])) {

		if (strpos($_GET['tag'],':') !== FALSE) {
			list($prefix,$_GET['tag']) = explode(':',$_GET['tag'],2);

			$andwhere = " AND prefix = ".$db->Quote($prefix);
			$smarty->assign('theprefix', $prefix);
			$sphinxq = "tags:\"$prefix {$_GET['tag']}\"";
			$tag = "$prefix:{$_GET['tag']}";
			
		} elseif (isset($_GET['prefix'])) {
			#$sphinxq = "tags:\"{$_GET['prefix']} {$_GET['tag']}\"";
			$tag = "{$_GET['prefix']}:{$_GET['tag']}";
		} else {
			#$sphinxq = "tags:\"{$_GET['tag']}\"";
			$tag = "{$_GET['tag']}";
		}
			
		$col= $db->getCol("SELECT tag_id FROM tag WHERE status = 1 AND tag=".$db->Quote($_GET['tag']).$andwhere);
		
		if ($col) {
			$tag_id = $col[0];
			
		} else {
			die("unable to find tag");
		}

	} else {
		die("please specify a tag!");
	}

$map=new GeographMap;
//standard 1px national map
$map->setOrigin(0,-10);
$map->setImageSize(900,1300);
$map->setScale(1);

$map->type_or_user = -10;

$blankurl=$map->getImageUrl();



$map->transparent = true;
$map->tagId = $tag_id;
$map->setPalette(3);
$map->type_or_user = -12;



if (!empty($_GET['refresh']) && $USER->hasPerm("admin")) {
	$target=$_SERVER['DOCUMENT_ROOT'].$map->getImageFilename();
	unlink($target);
	$map->_renderMap();
}


$overlayurl=$map->getImageUrl();


$smarty->display('_std_begin.tpl');

print "<h2>Coverage map for Tag";
if (!empty($tag))
	print " <small><a href=\"/tags/?tag=".urlencode($tag)."\">".htmlentities($tag)."</a></small>";
print "</h2>";

?>
<div style="position:relative; height:1300px;width:900px">
	<div style="position:absolute;top:0;left:0"> 
		<img src="<? echo $blankurl; ?>"/>
	</div>
	<div style="position:absolute;top:0;left:0"> 
		<img src="<? echo $overlayurl; ?>"/>
	</div>
</div>

<?


$smarty->display('_std_end.tpl');

