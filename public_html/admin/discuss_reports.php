<?php
/**
 * $Project: GeoGraph $
 * $Id: suggestions.php 6586 2010-04-02 20:10:46Z barry $
 * 
 * GeoGraph geographic photo archive project
 * This file copyright (C) 2005 Paul Dixon (paul@elphin.com)
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

$USER->hasPerm("director") || $USER->mustHavePerm("forum");

$smarty = new GeographPage;

$db = GeographDatabaseConnection(false);


if (!empty($_POST) && !empty($_POST['action'])) {
	$sqls = $open = array();
	$tcols = '`'.implode('`,`',$db->getCol("DESCRIBE geobb_topics")).'`';
	$pcols = '`'.implode('`,`',$db->getCol("DESCRIBE geobb_posts")).'`';

	if (!empty($_POST['topic_id']) && !empty($_POST['action']['new'])) {
		$u = array();
		$u['topic_id'] = intval($_POST['topic_id']);
                $u['user_id'] = $USER->user_id;
                $u['moderator'] = $USER->user_id;
		$u['resolution'] = 'delt'; //close it rightaway!

                $db->Execute('INSERT INTO discuss_report SET created=NOW(),`'.implode('` = ?, `',array_keys($u)).'` = ?',array_values($u));
		$report_id = $db->Insert_ID();
		$_POST['action'][$report_id] = $_POST['action']['new'];
		unset($_POST['action']['new']);
	}

	foreach ($_POST['action'] as $report_id => $action) {
		$r = intval($report_id);
		$w = "report_id = $r";
		$i = "$r AS report_id";

		if ($action) {
			$report = $db->getRow("SELECT * FROM discuss_report WHERE $w");
			$t = "topic_id = {$report['topic_id']}";

			$update_topic = 0;

			require_once('geograph/event.class.php');

			switch ($action) {
				case 'delete_thread':
					new Event(EVENT_DELTOPIC, $report['topic_id']);
					$sqls[] = "REPLACE INTO geobb_topics_quar SELECT $tcols,$i FROM geobb_topics WHERE $t";
					$sqls[] = "DELETE FROM geobb_topics WHERE $t";
					$sqls[] = "REPLACE INTO geobb_posts_quar SELECT $pcols,$i FROM geobb_posts WHERE $t";
					$sqls[] = "DELETE FROM geobb_posts WHERE $t";
					$open[$r] = 1; break;
				case 'delete_post':
					new Event('topic_edit', $report['topic_id']);
					$sqls[] = "REPLACE INTO geobb_posts_quar SELECT $pcols,$i FROM geobb_posts WHERE $t AND post_id = {$report['post_id']}";
					$sqls[] = "DELETE FROM geobb_posts WHERE $t AND post_id = {$report['post_id']}";

					$open[$r] = 1; $update_topic = 1; break;
				case 'delete_onwards':
					new Event('topic_edit', $report['topic_id']);
					$sqls[] = "REPLACE INTO geobb_posts_quar SELECT $pcols,$i FROM geobb_posts WHERE $t AND post_id => {$report['post_id']}";
					$sqls[] = "DELETE FROM geobb_posts WHERE $t AND post_id => {$report['post_id']}";
					$open[$r] = 1; $update_topic = 1; break;

				case 'restore_thread':
					new Event(EVENT_NEWTOPIC, $report['topic_id']);
					$sqls[] = "INSERT IGNORE INTO geobb_topics SELECT $tcols FROM geobb_topics_quar WHERE $t AND $w";
					$sqls[] = "INSERT IGNORE INTO geobb_posts SELECT $pcols FROM geobb_posts_quar WHERE $t AND $w";
					break;
				case 'restore_post':
					new Event(EVENT_NEWREPLY, $report['post_id']);
					$sqls[] = "INSERT IGNORE INTO geobb_posts SELECT $pcols FROM geobb_posts_quar WHERE $t AND post_id = {$report['post_id']} AND $w";
					$update_topic = 1; break;
				case 'restore_onwards':
					new Event('topic_edit', $report['topic_id']);
					$sqls[] = "INSERT IGNORE INTO geobb_posts SELECT $pcols FROM geobb_posts_quar WHERE $t AND post_id => {$report['post_id']} AND $w";
					$update_topic = 1; break;

				case 'open':
				case 'rejected':
				case 'delt': $sqls[] = "UPDATE discuss_report SET resolution = '$action' WHERE $w"; break;
			}
			$sqls[] = "INSERT INTO discuss_report_log SET $w, user_id = {$USER->user_id}, action = '$action'";

			if (!empty($update_topic))
				$sqls[] = "UPDATE geobb_topics SET topic_last_post_id = (SELECT MAX(post_id) FROM geobb_posts AS t1 WHERE $t),posts_count=(SELECT COUNT(*) FROM geobb_posts AS t2 WHERE $t) WHERE $t";
		}
	}

	if (!empty($sqls)) {
		if (!empty($open)) {
			$sqls[] = "UPDATE discuss_report SET resolution = 'open' WHERE report_id IN(".implode(',',array_keys($open)).") AND resolution = 'new'";
		}

		#print "<pre>";
		#print_r($sqls);
		#print "</pre>";
		$db->Execute("LOCK TABLES geobb_topics WRITE, geobb_posts WRITE, discuss_report WRITE, geobb_posts AS t1 WRITE, geobb_posts AS t2 WRITE,
					geobb_topics_quar WRITE, geobb_posts_quar WRITE, discuss_report_log WRITE");
		foreach ($sqls as $sql) {
			#print "<pre>$sql</pre>";
			$db->Execute($sql);
			#print "Affected: ".$db->Affected_Rows();
		}
		$db->Execute("UNLOCK TABLES");
	}
}


#############################

$sql = array();

$sql['columns'] = "r.*,realname,t1.forum_id AS forum_id1,t2.forum_id AS forum_id2,COALESCE(t1.topic_title,t2.topic_title) AS thread,t1.posts_count";
$sql['columns'] .= ",CONCAT(p1.post_time,' by ',p1.poster_name) AS post1,CONCAT(p2.post_time,' by ',p2.poster_name) AS post2,COALESCE(p1.post_text,p2.post_text) AS post_text";

$sql['tables'] = array();
$sql['tables']['r'] = 'discuss_report r';
$sql['tables']['u'] = 'INNER JOIN user u USING (user_id)';

$sql['tables']['t1'] = 'LEFT JOIN geobb_topics t1 ON (t1.topic_id = r.topic_id)';
$sql['tables']['p1'] = 'LEFT JOIN geobb_posts p1 ON (p1.post_id = r.post_id)';

$sql['tables']['t2'] = 'LEFT JOIN geobb_topics_quar t2 ON (t2.topic_id = r.topic_id)';
$sql['tables']['p2'] = 'LEFT JOIN geobb_posts_quar p2 ON (p2.post_id = r.post_id)';


$sql['wheres'] = array();

if (!empty($_GET['topic_id'])) {
	$smarty->assign("title",'Reports related to topic #'.intval($_GET['topic_id']));
	$smarty->assign('topic_id',intval($_GET['topic_id']));
	$sql['wheres'][] = "r.`topic_id` = ".intval($_GET['topic_id']);
} elseif (empty($_GET['all'])) {
	$smarty->assign("title",'New or Open reports');
	$sql['wheres'][] = "`resolution` in ('new','open')";
} else {
	$smarty->assign("title",'Latest 50 reports');
}

if (!empty($_GET['user_id'])) {
	$smarty->assign("title",'Reports related to user #'.intval($_GET['user_id']));
	$sql['wheres'][] = "r.`user_id` = ".intval($_GET['user_id']);
}

#$sql['group'] = 'r.topic_id';

$sql['order'] = 'r.report_id desc';

$sql['limit'] = 50;




$query = sqlBitsToSelect($sql);

$ADODB_FETCH_MODE = ADODB_FETCH_ASSOC;
$data = $db->getAll($query);

foreach ($data as $idx => $row) {
	$data[$idx]['comment'] = strip_tags($row['comment']);
}

$smarty->assign_by_ref('data',$data);

$logs = $db->getAll("SELECT l.*,realname FROM discuss_report_log l LEFT JOIN user USING (user_id) ORDER BY log_id DESC LIMIT 200");

$smarty->assign_by_ref('logs',$logs);

if (!empty($_GET['topic_id'])) {
	$where = array();
	$where[] = "status = 1";
	$where[] = "for_topic_id = ".intval($_GET['topic_id']);
	if (!empty($USER->user_id)) {
        	$in = explode(',',$USER->rights);
	        $in[] = 'all';
	        $where[] = "( for_user_id = {$USER->user_id} OR for_right IN ('".implode("','",$in)."') )";
	} else {
        	//dont want to check for_user_id, as it might be a private thread, for a specific right, without user_id)
	        $where[] = "for_right = 'all'";
	}
	$threads = $db->getAll("SELECT * FROM comment_thread WHERE ".implode(' AND ',$where));
	$smarty->assign_by_ref('threads',$threads);
}

#############################

$smarty->display('admin_discuss_reports.tpl');


