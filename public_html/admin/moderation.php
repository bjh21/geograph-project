<?php
/**
 * $Project: GeoGraph $
 * $Id: moderation.php 8917 2019-03-24 11:40:19Z barry $
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
require_once('geograph/gridimage.class.php');
require_once('geograph/gridsquare.class.php');
require_once('geograph/imagelist.class.php');

init_session();

if (isset($CONF['curtail_level']) && $CONF['curtail_level'] > 5 && strpos($_SERVER['HTTP_REFERER'],'editimage') === FALSE) {
	header("HTTP/1.1 503 Service Unavailable");
	die("the servers are currently very busy - moderation is disabled to allow things to catch up, will be automatically re-enabled when load returns to normal");
}

if (!empty($_GET['style'])) {
	$USER->getStyle();
	if (!empty($_SERVER['QUERY_STRING'])) {
		$query = preg_replace('/style=(\w+)/','',$_SERVER['QUERY_STRING']);
		header("HTTP/1.0 301 Moved Permanently");
		header("Status: 301 Moved Permanently");
		header("Location: /admin/moderation.php?".$query);
		exit;
	}
	header("Location: /admin/moderation.php");
	exit;
}

#customGZipHandlerStart();

$db = GeographDatabaseConnection(false);

$smarty = new GeographPage;

if (!empty($CONF['moderation_message'])) {
        $smarty->assign("status_message",$CONF['moderation_message']);
}

//doing some moderating?
if (isset($_GET['gridimage_id']))
{
	//user may have an expired session, or playing silly buggers,
	//either way, we want to check for admin status on the session
	if ($USER->hasPerm('moderator') || isset($_GET['remoderate']))
	{
		$gridimage_id=intval($_GET['gridimage_id']);
		$status=$_GET['status'];

		$image=new GridImage;
		if ($image->loadFromId($gridimage_id))
		{
			if (isset($_GET['remoderate']))
			{
				if ($USER->hasPerm('basic'))
				{
					$status = $db->Quote($status);
					$db->Execute("REPLACE INTO moderation_log SET user_id = {$USER->user_id}, gridimage_id = $gridimage_id, new_status=$status, old_status='{$image->moderation_status}',created=now(),type = 'dummy'");
					print "classification $status recorded";

					$mkey = $USER->user_id;
					$memcache->name_delete('udm',$mkey);
				}
				else
				{
					echo "NOT LOGGED IN";
				}
			}
			else
			{
				//we really need this not be interupted
				ignore_user_abort(TRUE);
				set_time_limit(3600);

				$status2 = $db->Quote($status);
				$db->Execute("INSERT INTO moderation_log SET user_id = {$USER->user_id}, gridimage_id = $gridimage_id, new_status=$status2, old_status='{$image->moderation_status}',created=now(),type = 'real'");

				$info=$image->setModerationStatus($status, $USER->user_id);
				echo $info;
				flush;

				if ($status == 'rejected')
				{
					$ticket=new GridImageTroubleTicket();
					$ticket->setSuggester($USER->user_id);
					$ticket->setModerator($USER->user_id);
					$ticket->setPublic('everyone');
					$ticket->setImage($gridimage_id);
					if (!empty($_GET['comment'])) {
						$ticket->setNotes("Auto-generated ticket, as a result of Moderation. Rejecting this image because: ".stripslashes($_GET['comment']));
					} else {
						$ticket->setNotes("Auto-generated ticket, as a result of Moderation. Please leave a comment to explain the reason for rejecting this image.");
					}

					$status=$ticket->commit(isset($_GET['autoclose'])?'closed':'open');

					echo " <a href=\"/editimage.php?id={$gridimage_id}\"><B>View Ticket</b></a>";
				}

				//clear caches involving the image
				$ab=floor($gridimage_id/10000);
				$smarty->clear_cache('', "img$ab|{$gridimage_id}|");

				//clear the users profile cache
				//todo - maybe we only need to do this if a recent image?
				$ab=floor($image->user_id/10000);
				$smarty->clear_cache('', "user$ab|{$image->user_id}|");

				$memcache->name_delete('us',$image->user_id);
			}
		}
		else
		{
			echo "FAIL";
		}
	}
	else
	{
		echo "NOT LOGGED IN";
	}

	exit;
}

if (!empty($_GET['abandon'])) {
	$USER->hasPerm('moderator') || $USER->mustHavePerm("ticketmod");

	$db->Execute("DELETE FROM gridsquare_moderation_lock WHERE user_id = {$USER->user_id}");

	$db->Execute("DELETE FROM gridimage_moderation_lock WHERE user_id = {$USER->user_id}");

	header("Location: /admin/");
	exit;
}


$limit = (isset($_GET['limit']) && is_numeric($_GET['limit']))?min(100,intval($_GET['limit'])):15;
if ($limit > 15) {
	dieUnderHighLoad(0.5);
} else {
	dieUnderHighLoad(1.3);
}


if (!empty($_GET['relinquish'])) {
	$USER->mustHavePerm('basic');
	$db->Execute("UPDATE user SET rights = REPLACE(REPLACE(rights,'traineemod',''),'moderator','alumni') WHERE user_id = {$USER->user_id}");

	//reload the user object
	$_SESSION['user'] = new GeographUser($USER->user_id);

	header("Location: /profile.php?edit=1");
	exit;

} elseif (!empty($_GET['apply'])) {
	$USER->mustHavePerm('basic');
        $smarty->assign('is_mod',$USER->hasPerm('moderator')); //can still be useful to know!

	if ($_GET['apply'] == 2) {

		$db->Execute("UPDATE user SET rights = CONCAT(rights,',traineemod') WHERE user_id = {$USER->user_id}");

		$mods=$db->GetCol("select email from user where FIND_IN_SET('admin',rights)>0 OR FIND_IN_SET('coordinator',rights)>0");

		$url = $CONF['SELF_HOST'].'/admin/moderator_admin.php?stats='.$USER->user_id;

		mail(implode(',',$mods), "[Geograph] Moderator Application ({$USER->user_id})",
"Dear Admin,

I have just completed verification.

Comments:
{$_POST['comments']}

Click the following link to review the application:

$url

Regards,

{$USER->realname}".($USER->nickname?" (aka {$USER->nickname})":''),
				"From: {$USER->realname} <{$USER->email}>");

		header("Location: /profile.php");
		exit;
	}

	$count = $db->getRow("select count(*) as total,sum(created > date_sub(now(),interval 60 day)) as recent from moderation_log WHERE user_id = {$USER->user_id} AND type = 'dummy'");
	if ($count['total'] > 0) {
		$limit = 10;
	}

	//make sure they only do verifications
	$_GET['remoderate'] = 1;

	$smarty->assign('apply', 1);

} elseif (isset($_GET['moderator'])) {
	($USER->user_id == 10124) || $USER->hasPerm("director") || $USER->mustHavePerm('admin');
} else {
	$USER->mustHavePerm('moderator');
}

#############################
# check if needs to remoderate

if (!isset($_GET['moderator']) && !isset($_GET['review']) && !isset($_GET['remoderate'])) {

	$mkey = $USER->user_id;
	$count =& $memcache->name_get('udm',$mkey);

	if (empty($count)) {
		if ($db->readonly) {
			$db2 =& $db;
		} else {
			$db2 = GeographDatabaseConnection(true);
		}

		$count = $db2->getRow("select count(*) as total,sum(created > date_sub(now(),interval 60 day)) as recent from moderation_log WHERE user_id = {$USER->user_id} AND type='dummy'");

		$memcache->name_set('udm',$mkey,$count,$memcache->compress,$memcache->period_med);
	}

	if ($count['total'] == 0) {
		$_GET['remoderate'] = 1;
		$limit = 25;
	} elseif ($count['recent'] < 5) {
		$_GET['remoderate'] = 1;
		$limit = 10;
	}
}

#############################
# find the list of squares with self pending images, and exclude them...

$sql = "select distinct gridsquare_id
from
	gridimage as gi
where
	(moderation_status = 2) and
	gi.user_id = {$USER->user_id}
order by null";

$recordSet = $db->Execute($sql);
while (!$recordSet->EOF)
{
	$db->Execute("REPLACE INTO gridsquare_moderation_lock SET user_id = {$USER->user_id}, gridsquare_id = {$recordSet->fields['gridsquare_id']},lock_type = 'cantmod'");

	$recordSet->MoveNext();
}
$recordSet->Close();

#############################
# define the images to moderate

$sql_where2 = "
	and (l.gridsquare_id is null OR
			(l.user_id = {$USER->user_id} AND lock_type = 'modding') OR
			(l.user_id != {$USER->user_id} AND lock_type = 'cantmod')
		)";
$sql_columns = $sql_from = '';
if (isset($_GET['review'])) {
	$mid = intval($USER->user_id);

	$sql_columns = ", new_status,moderation_log.user_id as ml_user_id,v.realname as ml_realname, DATE(moderation_log.created) as ml_created";
	$sql_from = " inner join moderation_log on(moderation_log.gridimage_id=gi.gridimage_id AND moderation_log.type='real')
				inner join user v on(moderation_log.user_id=v.user_id)";

	$sql_where = "(moderation_log.user_id = $mid && gi.moderator_id != $mid)";

	$sql_where = "($sql_where and moderation_status != new_status)";

	$sql_order = "gridimage_id desc";

	$smarty->assign('review', 1);
	$sql_where2 = '';

} elseif (isset($_GET['moderator'])) {
	$mid = intval($_GET['moderator']);

	if (isset($_GET['verify'])) {
		$sql_columns = ", new_status,moderation_log.user_id as ml_user_id,v.realname as ml_realname, DATE(moderation_log.created) as ml_created";
		$sql_from = " inner join moderation_log on(moderation_log.gridimage_id=gi.gridimage_id AND moderation_log.type='dummy')
					inner join user v on(moderation_log.user_id=v.user_id)";
		if ($mid == 0) {
			$sql_where = "1";
		} else {
			$sql_where = "(moderation_log.user_id = $mid or gi.moderator_id = $mid)";
		}

		if ($_GET['verify'] == 2) {
			$sql_where = "($sql_where and moderation_status != new_status)";
		}
		$sql_order = "gridimage_id desc";
	} elseif ($mid == 0) {
		$sql_columns = ", m.realname as mod_realname";
		$sql_where = "(moderation_status != 2) and moderator_id != {$USER->user_id}";
		$sql_from = " inner join user m on(moderator_id=m.user_id)";
		$sql_order = "gridimage_id desc";
	} else {
		$sql_where = "(moderation_status != 2) and moderator_id = $mid";
		$sql_order = "gridimage_id desc";
	}

	if (isset($_GET['status']) && ($statuses = $_GET['status']) ) {
		if (is_array($statuses))
			$sql_where.=" and moderation_status in ('".implode("','", $statuses)."') ";
		elseif (strpos($statuses,',') !== FALSE)
			$sql_where.=" and moderation_status in ('".implode("','", explode(',',$statuses))."') ";
		elseif (is_int($statuses))
			$sql_where.=" and moderation_status = $statuses ";
		else
			$sql_where.=" and moderation_status = '$statuses' ";
	}

	$smarty->assign('moderator', 1);
	$sql_where2 = '';
} elseif (isset($_GET['user_id'])) {
	$sql_where = "gi.user_id = ".intval($_GET['user_id']);
	$sql_order = "gridimage_id desc";
	$smarty->assign('remoderate', 1);
} elseif (isset($_GET['image'])) {
	$sql_where = "gi.gridimage_id = ".intval($_GET['image']);
	$sql_order = "gridimage_id desc";
	$smarty->assign('remoderate', 1);
} elseif (isset($_GET['remoderate'])) {
	$sql_where = "moderation_status > 2 and moderator_id != {$USER->user_id} and submitted > date_sub(now(),interval 10 day) ";
	$sql_order = "gridimage_id desc";
	$smarty->assign('remoderate', 1);
} else {
	$sql_where = "(moderation_status = 2)";
	$sql_order = "gridimage_id asc";
}

if (isset($_GET['xmas'])) {

	$year = date('Y');

	$db->Execute("insert ignore into gridsquare_moderation_lock select gridsquare_id,{$USER->user_id} as user_id,now() as lock_obtained,'modding' as lock_type from gridimage as gi  left join gridimage_snippet gs using (gridimage_id) left join snippet s using (snippet_id) where (gi.imageclass = 'christmas day $year' OR s.title = 'midday christmas $year') and moderation_status = 'pending' and submitted > date_sub(now(),interval 1 day) group by gridsquare_id");

	$sql_where .= " AND (lock_type = 'modding')";
}

#############################
#lock the table so nothing can happen in between! (leave others as READ so they dont get totally locked)

$db->Execute("LOCK TABLES
gridsquare_moderation_lock WRITE,
gridsquare_moderation_lock l WRITE,
moderation_log WRITE,
gridsquare READ,
gridsquare gs READ,
gridimage gi READ LOCAL,
user READ LOCAL,
gridprefix READ,
user v READ LOCAL,
user m READ LOCAL,
user_stat us READ LOCAL,
tag_public READ LOCAL");


$sql = "select gi.*,group_concat(if(prefix='',tag,concat(prefix,':',tag)) separator '?') as tags,grid_reference,user.realname,imagecount,coalesce(images,0) as images $sql_columns
from
	gridimage as gi
	inner join gridsquare as gs
		using(gridsquare_id)
	$sql_from
	left join gridsquare_moderation_lock as l
		on(gi.gridsquare_id=l.gridsquare_id and lock_obtained > date_sub(NOW(),INTERVAL 1 HOUR) )
	inner join user
		on(gi.user_id=user.user_id)
	left join user_stat us
		on(gi.user_id=us.user_id)
	left join tag_public t
		on(t.gridimage_id = gi.gridimage_id)
where
	$sql_where
	$sql_where2
	and submitted < date_sub(now(),interval 1 hour)
group by gridimage_id
order by
	$sql_order
limit $limit";
//implied: and user_id != {$USER->user_id}
// -> because squares with users images are locked

if (!empty($_GET['debug'])) {
	print $sql;
}


#############################
# fetch the list of images...

$images=new ImageList();
$images->_setDB($db);
$c = $images->_getImagesBySql($sql);


$realname = array();
foreach ($images->images as $i => $image) {
	$token=new Token;
	$fix6fig = 0;
	if ($image->use6fig && ($image->natgrlen > 6 || $image->viewpoint_grlen > 6)) {
		$fix6fig = 1;
		$images->images[$i]->use6fig = 0;
	}
	$token->setValue("g", $images->images[$i]->getSubjectGridref(true));
	if ($image->viewpoint_eastings) {
		//note $image DOESNT work non php4, must use $images->images[$i]
		//move the photographer into the center to match the same done for the subject
		$correction = ($images->images[$i]->viewpoint_grlen > 4)?0:500;
		$images->images[$i]->distance = sprintf("%0.2f",
			sqrt(pow($images->images[$i]->grid_square->nateastings-$images->images[$i]->viewpoint_eastings-$correction,2)+pow($images->images[$i]->grid_square->natnorthings-$images->images[$i]->viewpoint_northings-$correction,2))/1000);

		if (intval($images->images[$i]->grid_square->nateastings/1000) != intval($images->images[$i]->viewpoint_eastings/1000)
			|| intval($images->images[$i]->grid_square->natnorthings/1000) != intval($images->images[$i]->viewpoint_northings/1000))
			$images->images[$i]->different_square_true = true;

		if ($images->images[$i]->different_square_true && $images->images[$i]->subject_gridref_precision==1000)
			$images->images[$i]->distance -= 0.5;

		if ($images->images[$i]->different_square_true && $images->images[$i]->distance > 0.1)
			$images->images[$i]->different_square = true;

		$token->setValue("p", $images->images[$i]->getPhotographerGridref(true));
	}
	if (isset($image->view_direction) && strlen($image->view_direction) && $image->view_direction != -1) {
		$token->setValue("v", $image->view_direction);
	}
	$images->images[$i]->reopenmaptoken = $token->getToken();
	if ($fix6fig) {
		$images->images[$i]->subject_gridref = '';//kill the cache so will be done again with use6fig;
		$images->images[$i]->photographer_gridref = '';
		$images->images[$i]->use6fig = 1;
	}

	$db->Execute("REPLACE INTO gridsquare_moderation_lock SET user_id = {$USER->user_id}, gridsquare_id = {$image->gridsquare_id}");

	$fullpath=$images->images[$i]->_getFullpath();
	if ($fullpath!="/photos/error.jpg") {
		list($width, $height, $type, $attr)=getimagesize($_SERVER['DOCUMENT_ROOT'].$fullpath);
		if ($width > 0 && max($width,$height) < 600)
			$images->images[$i]->sizestr = $attr;
	}
	//if (!empty($image->tags))
	//	$images->images[$i]->tags = explode("?",$image->tags);
}

#############################

$db->Execute("UNLOCK TABLES");

#############################

$images->assignSmarty($smarty, 'unmoderated');

//what style should we use?
$style = $USER->getStyle();
$smarty->assign('maincontentclass', 'content_photo'.$style);

    $smarty->register_function("votestars", "smarty_function_votestars");

$smarty->assign('second',!empty($_SESSION['second']));
$_SESSION['second'] = true;

$smarty->display('admin_moderation.tpl',$style);




function smarty_function_votestars($params) {
    global $CONF;
    static $last;

    $type = $params['type'];
    $id = $params['id'];
    $names = array('','Hmm','Below average','So So','Good','Excellent');
    foreach (range(1,5) as $i) {
        print "<a href=\"javascript:void(record_vote('$type',$id,$i));\" title=\"{$names[$i]}\"><img src=\"{$CONF['STATIC_HOST']}/img/star-light.png\" width=\"14\" height=\"14\" alt=\"$i\" onmouseover=\"star_hover($id,$i,5)\" onmouseout=\"star_out($id,5)\" name=\"star$i$id\"/></a>"; 
    }
    $last = $type;
}

