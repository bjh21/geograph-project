<?php
/**
 * $Project: GeoGraph $
 * $Id: submissions.php 6368 2010-02-13 19:45:59Z barry $
 * 
 * GeoGraph geographic photo archive project
 * This file copyright (C) 2007 Barry Hunter (geo@barryhunter.co.uk)
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
require_once('geograph/imagelist.class.php');
init_session();


$smarty = new GeographPage;

$USER->mustHavePerm("basic");

customGZipHandlerStart();


$smarty->display("_std_begin.tpl");

print "<h2>Geograph Image Issue Report Form</h2>";

print "<p>Please let us know here if unable to view an image on geograph. This includes if having issues with fresh submissions.  </p>";

print "<p>If have multiple images to report, will have to submit multiple times";

if (!empty($_POST)) {

/*
 CREATE TABLE `image_report_form` (
  `report_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `created` timestamp NOT NULL DEFAULT current_timestamp(),
  `image_url` varchar(255) NOT NULL,
  `gridimage_id` int(10) unsigned NOT NULL,
  `affected` varchar(1024) NOT NULL,
  `page_url` varchar(255) NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'new',
  `updated` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE current_timestamp(),
  PRIMARY KEY (`report_id`),
  KEY `gridimage_id` (`gridimage_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
*/


	$db = GeographDatabaseConnection(false);

	$updates = array();

	if (!empty($_POST['bulk'])) {
		$lines = explode("\n",str_replace("\r",'',$_POST['bulk']));
print "<pre>";
		$count = 0;
		foreach ($lines as $line) {
			if (empty($line))
				continue;
			$bits = explode("\t",$line);
			if ($bits[0] == "Timestamp" || empty($bits[1]))
				continue;


//get dates like "19/08/2018 09:32:21" which Google DOcs provided in UK format, but strtotime is US absed?
$bits[0] = preg_replace('/(\d+)\/(\d+)\/(\d{4})/','$2/$1/$3', $bits[0]);

			$updates["created"] = date('Y-m-d H:i:s',strtotime($bits[0]));
			$updates["image_url"] = $bits[1];

			$str = preg_replace('/[\w:\/\.]*\/(\d{6,7})_\w{8}(_\w+)?\.jpg/','$1',$bits[1]); //replace any thumbnail urls with just the id.
        		$updates["gridimage_id"] = trim(preg_replace('/[^\d]+/',' ',$str));

			$updates["affected"] = $bits[2];
			$updates["page_url"] = $bits[3];

print_r($updates);
			$db->Execute('INSERT INTO image_report_form SET `'.implode('` = ?,`',array_keys($updates)).'` = ?',array_values($updates));
			$count += $db->Affected_Rows();
		}
		print "Count = $count;";
print "</pre>";

	} elseif (!empty($_POST['image_url'])) {
		$updates["image_url"] = $_POST['image_url'];

		$str = preg_replace('/[\w:\/\.]*\/(\d{6,7})_\w{8}(_\w+)?\.jpg/','$1',$_POST['image_url']); //replace any thumbnail urls with just the id.
        	$updates["gridimage_id"] = trim(preg_replace('/[^\d]+/',' ',$str));

		$updates["affected"] = implode(', ',$_POST['affected']);
		$updates["page_url"] = $_POST['page_url'];
                $updates["user_id"] = intval($USER->user_id);

		$db->Execute('INSERT INTO image_report_form SET created = NOW(),`'.implode('` = ?,`',array_keys($updates)).'` = ?',array_values($updates));
		print "Thank you, report recorded for {$updates["gridimage_id"]}";
	}

}




?>
<form method=post>
<table border=0 cellspacing=0 cellpadding=5>
<?

if (!empty($_GET['bulk'])) {
	print "<textarea name=bulk cols=80 rows=30></textarea>";
}

?>
<tr>
	<th>URL of the affected Image</th>
	<td><input type=text name=image_url placeholder="enter image-id here" maxlength="128" size="60" required ></td>
	<td><small>can be link of the photo page, probably something like "http://www.geograph.org.uk/photo/99999", a direct link to the .jpg file, - or at least just the Image ID. 
</tr>
<tr>
	<th>What's affected?</th>
	<td><small>Tick any that you know are affected, tick as many as needed
</tr>
<?
$list = "
Tiny Thumbnail (on Map Mosaic)
Small Thumbnail (120px)
Medium Thumbnail (213px)
Large Thumbnail (as seen on POTD/homepage)
Image Shown on Photo Page
The Photo page itself not functional
Preview shown on More SIzes
Largest Available via More Sizes
larger Mid-Resolution Downloads (from More Sizes)
The Stamped Image
";

foreach (explode("\n",trim($list)) as $idx => $value) { ?>
	<tr>
        	<th></th>
	        <td colspan=2><input type=checkbox name="affected[]" value="<? echo $value; ?>" id="c<? echo $idx; ?>">
		<label for="c<? echo $idx; ?>"><? echo $value; ?>
<? } ?>
<tr>
	<th>URL of page where see this</th>
	<td><input type=text name=page_url placeholder="https://www.geograph.org.uk/...." maxlength="128" size="60"> (optional)</td>
	<td><small>Page where seeing the missing image - eg forum thread, article, search result, etc. If its not the Photo Page itself.
</tr>
<tr>
	<td>
	<td><input type=submit value="submit"></td>
</tr>
</table>
</form>
<?




$smarty->display("_std_end.tpl");
