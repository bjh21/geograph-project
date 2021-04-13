<?php
/**
 * $Project: GeoGraph $
 * $Id: places.php 5786 2009-09-12 10:18:04Z barry $
 * 
 * GeoGraph geographic photo archive project
 * This file copyright (C) 2011 barry hunter (geo@barryhunter.co.uk)
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

	$USER->mustHavePerm("tagsmod");


$smarty->display('_std_begin.tpl');

$db = GeographDatabaseConnection(false);

	if (!empty($_POST)) {

		$updates = $_POST;
		$updates['subject'] = strtolower($updates['subject']);

		$db->Execute('INSERT INTO subjects SET `'.implode('` = ?,`',array_keys($updates)).'` = ?',array_values($updates));

	}
	print "<p>Scroll to bottom for action form</p>";

	dump_sql_table("select tag_id,t.tag,t.user_id,t.created,t.updated,canonical,sum(gt.status = 2) as images from tag t left join subjects s on (t.tag = s.subject) left join tag_report using (tag_id) left join gridimage_tag gt using (tag_id) where t.prefix = 'subject' and t.status = 1 and s.subject is NULL and report_id IS NULL GROUP BY tag_id",
		'Unoffical Subjects (been created as a subject:prefix tag, but not in the offical tag list)');

	dump_sql_table("select tag_id,tag.user_id,tag_report.tag,tag2,tag_report.status,approver_id from tag inner join tag_report using (tag_id) where prefix = 'subject' and tag.status = 0 order by tag_id",
		'Previouslly created subject tags, that have already been redirected');
	print "Any time one of these tags is added to an image, the tag will automatically be converted to the second form";

	?>
	<br><br>
	<h2>Action a change</h2>

	<form method=post>
		Choose subject to edit: <select name="subject" required><option></option>
			<?
			foreach ($db->getCol("SELECT t.tag FROM tag t left join subjects s on (t.tag = s.subject) left join tag_report r using (tag_id) where prefix = 'subject' and subject is null and t.status = 1  AND r.report_id IS NULL ORDER BY t.tag") as $value) {
				print "<option>$value</option>";
			} ?>
		</select><br><br>


	<p>For subject selected above, there are few options (choose ONE)</p>


	<h3>1. Merge to exist subject, and create a new tag for the detail</h3>

	<p>While its important to canonalize the subjects, also dont want to 'loose' the additional detail the user entered, for example, [subject:masonic lodge] could be split to [subject:building] and [masonic lodge], </p>

	<input type=button value="report form" onclick="location.href='/tags/report.php?admin=1&tag='+encodeURIComponent('subject:'+this.form.elements['subject'].value);"> (and select 'split' option)


	<h3>2. Rename the tag to be the same as existing Subject tag.</h3>

	<p>Effectively merges the two tags, and removes the unapproved tag. 

	<input type=button value="report form" onclick="location.href='/tags/report.php?admin=1&tag='+encodeURIComponent('subject:'+this.form.elements['subject'].value);"> (and select 'spelling' option)
		 Will get a warning about not using the form to merge, or edit top: tags. In this instance it can be ignored</p>

	<p><a href="/stuff/subjects.php">Full subject list for reference</a></p>



        <h3>3. Rename the tag remove the 'subject:' prefix</h3>

	<p>Make it jsut like any other normal tag.

	 <input type=button value="report form" onclick="location.href='/tags/report.php?admin=1&tag='+encodeURIComponent('subject:'+this.form.elements['subject'].value);"> (and select 'spelling' option)
		Will get a warning about not using the form to merge, or edit top: tags. In this instance it can be ignored</p>


	<h3>4. Create a New Approved Subject</h3>
	(Note, if the capitaliation of the tag needs fixing first (capitalization IS import for subject tags), then use the admin form to change. <b>Once the action has been actioned, return here</b>)
	<br>

		<input type=submit value="approve this subject">
	</form>


<?

$smarty->display('_std_end.tpl');

function dump_sql_table($sql,$title,$autoorderlimit = false) {
	global $db;
	static $idx = 1;

        $recordSet = $db->Execute($sql.(($autoorderlimit)?" order by count desc limit 25":'')) or die ("Couldn't select photos : $sql " . $db->ErrorMsg() . "\n");

        print "<H3>$title</H3>";

if ($recordSet->recordCount() ==0) {
        print "0 rows";
        return;
}

	$row = $recordSet->fields;

	if ($idx ==1)
		print "<script src=\"".smarty_modifier_revision("/sorttable.js")."\"></script>";

        print "<TABLE border='1' cellspacing='0' cellpadding='2' class=\"report sortable\" id=\"list$idx\"><THEAD><TR>";
        foreach ($row as $key => $value) {
                print "<TH>$key</TH>";
        }
        print "</TR></THEAD><TBODY>";
        $keys = array_keys($row);
        $first = $keys[0];
	while (!$recordSet->EOF) {
		$row = $recordSet->fields;
                print "<TR>";
                $align = "left";
                if (is_null($row[$first])) {
                        $row['team'] = '-EVERYONE-';
                }
                foreach ($row as $key => $value) {
                        $align = is_numeric($value)?"right":'left';
                        print "<TD ALIGN=$align>".htmlentities($value)."</TD>";
                }
                if (!empty($row['ip'])) {
                        print "<td>".gethostbyaddr($row['ip'])."</TD>";
                }
                print "</TR>";
		$recordSet->MoveNext();
	}
        print "</TR></TBODY></TABLE>";

	$idx++;
}


