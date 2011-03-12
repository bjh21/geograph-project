<?php
/**
 * $Project: GeoGraph $
 * $Id: ecard.php 3886 2007-11-02 20:14:19Z barry $
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

$smarty = new GeographPage;
$template='frame.tpl';	

customExpiresHeader(3600*6,true,true);

if (isset($_REQUEST['id'])) {
	$cacheid = intval($_REQUEST['id']);
	
	if (!$smarty->is_cached($template, $cacheid)) {
		
		$image=new GridImage();
		$ok = $image->loadFromId($_REQUEST['id']);

		if (!$ok || $image->moderation_status=='rejected') {
			//clear the image
			$image=new GridImage;
			header("HTTP/1.0 410 Gone");
			header("Status: 410 Gone");
			$template = "static_404.tpl";
		} else {
			//bit late doing it now, but at least if smarty doesnt have it cached we might be able to prevent generating the whole page
			customCacheControl(strtotime($image->upd_timestamp),$cacheid);

			$smarty->assign_by_ref('image', $image);
		}
	}
} else {
	header("HTTP/1.0 404 Not Found");
	header("Status: 404 Not Found");
	$template = "static_404.tpl";
}


if (strpos($_SERVER['HTTP_REFERER'],$_SERVER['HTTP_HOST']) === FALSE) {
        $smarty->assign("external",true);
}

$smarty->display($template, $cacheid);
