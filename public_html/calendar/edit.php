<?php
/**
 * $Project: GeoGraph $
 * $Id: conversion.php 5502 2009-05-13 14:18:23Z barry $
 * 
 * GeoGraph geographic photo archive project
 * This file copyright (C) 2005 BArry Hunter (geo@barryhunter.co.uk)
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
init_session();

$smarty = new GeographPage;
$USER->mustHavePerm("basic");


$db = GeographDatabaseConnection(false);

####################################

$row = $db->getRow("SELECT * FROM calendar WHERE calendar_id = ".intval($_GET['id']));

if (empty($row) || $row['user_id'] != $USER->user_id)
	die("Calendar not found");


####################################

if (!empty($_POST)) {
	$updates= array();
	if (isset($_POST['calendar_title']) && $_POST['calendar_title'] != $row['title'])
		$updates['title'] = $_POST['calendar_title'];

	if (!empty($updates))
		$db->Execute('UPDATE calendar SET `'.implode('` = ?,`',array_keys($updates)).'` = ?'.
			' WHERE calendar_id = '.$row['calendar_id'], array_values($updates));

	foreach ($_POST['title'] as $id => $title) {
		$updates = array();
		$updates['title'] = $title;
		$updates['grid_reference'] = $_POST['grid_reference'][$id];
		//realname. We dont allow this be edited?
		$updates['imagetaken'] = $_POST['imagetaken'][$id];

		if (!empty($updates))
			$db->Execute('UPDATE gridimage_calendar SET `'.implode('` = ?,`',array_keys($updates)).'` = ?'.
				' WHERE calendar_id = '.$row['calendar_id'].' AND gridimage_id='.intval($id), array_values($updates));
	}


	if (!empty($_POST['proceed'])) {
		header("Location: order.php?id=".intval($row['calendar_id']));
		exit;
	}
}

####################################

$smarty->assign('calendar',$row);

require_once('geograph/imagelist.class.php');
$imagelist=new ImageList;
$imagelist->_setDB($db);//to reuse the same connection

//this is NOT normal rows, but gridimage_calendar has enough rows, that it works! (at least to get thumbnails!)
$sql = "SELECT * FROM gridimage_calendar
	INNER JOIN gridimage_size using (gridimage_id)
	WHERE calendar_id = {$row['calendar_id']} ORDER BY sort_order";
$imagelist->_getImagesBySql($sql);

foreach ($imagelist->images as $key => &$image) {
	if (false) { //if external upload!

	} elseif ($image->original_width > 640) {
		$alturl = $image->_getOriginalpath(true,true,'_640x640');
		if (basename($alturl) != "error.jpg") {
			//these is a special image to use as the preview. previe of the larger not the original 640px upload!
			$image->preview_url = $alturl;
		} else {
			//in this case the 640px should serve as an ok preview!
			$image->preview_url = $image->_getFullpath(true,true);
		}
		$image->width  = $image->original_width;
		$image->height = $image->original_height;
	} else {
		//there is no larger version available
		$image->preview_url = $image->_getFullpath(true,true);
	}

	//A4 = 210 x 297 mm	8.3 x 11.7 inches
	$w = 11.7 - 0.5905; //15mm border (0.5905 in inches)
	$h = 8.3 - 0.5905; //inches! as dpI
	$ratioA4 = $w/$h;
	$ratioImg = $image->width/$image->height;

	if ($ratioImg > $ratioA4) {
		//$image->dpi = "W".intval($image->width / $w)." {$image->width} into $w";
		$image->dpi = intval($image->width / $w);
	} else {
		$image->dpi = intval($image->height / $h);
	}
	if ($image->dpi > 300)
		$image->dpi = 300; //todo?

	if ($image->sort_order > 0)
		$image->month = date('F',strtotime(sprintf('2000-%02d-01',$image->sort_order)));
	else
		$image->month = "Cover Image";
}


$smarty->assign_by_ref('images', $imagelist->images);


$smarty->display('calendar_edit.tpl');




