<?php
/**
 * $Project: GeoGraph $
 * $Id: faq.php 15 2005-02-16 12:23:35Z lordelph $
 * 
 * GeoGraph geographic photo archive project
 * This file copyright (C) 2006 Barry Hunter (geo@barryhunter.co.uk)
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

$pg = empty($_GET['page'])?1:intval($_GET['page']);
$pgsize = 10;

$template = 'gallery_gallery.tpl';

$topic_id = intval($_GET['id']);


$cacheid = "$topic_id.$pg";

$db = GeographDatabaseConnection(false);

$page = $db->getRow("
select t.topic_id,topic_title,topic_poster,topic_poster_name,topic_time,post_time,posts_count,t.forum_id
	from geobb_topics t
	inner join geobb_posts on (post_id = topic_last_post_id)
	where t.topic_id = $topic_id");

if (count($page)) {

	//when this page was modified
	$mtime = strtotime($page['post_time']);

	$page['url'] = trim(strtolower(preg_replace('/[^\w]+/','_',html_entity_decode(preg_replace('/&#\d+;?/','_',$page['topic_title'])))),'_').'_'.$page['topic_id'];


	if (!isset($_GET['dontcount']) && @strpos($_SERVER['HTTP_REFERER'],$page['url']) === FALSE && appearsToBePerson()) {
		$db->Execute("UPDATE LOW_PRIORITY geobb_topics SET topic_views=topic_views+1 WHERE topic_id = $topic_id");
	}

	//can't use IF_MODIFIED_SINCE for logged in users as has no concept as uniqueness
	customCacheControl($mtime,$cacheid,($USER->user_id == 0));


	if (empty($pgsize)) {$pgsize = 10;}
	if (!$pg or $pg < 1) {$pg = 1;}

	$pagelimit = ($pg -1)* $pgsize;

} else {

	header("HTTP/1.0 404 Not Found");
	header("Status: 404 Not Found");
	$template = 'static_404.tpl';
}

function smarty_function_gallerytext($input) {
	global $imageCredits,$smarty,$CONF;

	$pattern=array(); $replacement=array();

	$pattern[]='/\[image id=([a-z]*:\d+)\]/e';
	$replacement[]="smarty_function_gridimage(array(id => '\$1',extra => '{description}'))";

	$pattern[]='/\[image id=([a-z]*:\d+) text=([^\]]+)\]/e';
	$replacement[]="smarty_function_gridimage(array(id => '\$1',extra => '\$2'))";

	$output=preg_replace($pattern, $replacement, $input, 5);

	return $output;
}

$smarty->register_modifier("gallerytext", "smarty_function_gallerytext");

if (!$smarty->is_cached($template, $cacheid))
{
	if (count($page)) {
		$CONF['global_thumb_limit'] *= 2;
		$CONF['post_thumb_limit'] *= 2;

		$smarty->assign($page);

		if (empty($list)) {
			$prev_fetch_mode = $ADODB_FETCH_MODE;
			$ADODB_FETCH_MODE = ADODB_FETCH_ASSOC;
			$list = $db->getAll("
			select post_id,poster_id,poster_name,post_text,post_time
			from geobb_posts
			where topic_id = $topic_id
			order by post_id
			limit $pagelimit,$pgsize");
		}

		$smarty->assign_by_ref('list', $list);

		if ($page['posts_count'] > $pgsize) {
			$numberOfPages = ceil($page['posts_count']/$pgsize);

			$smarty->assign('pagesString', pagesString($pg,$numberOfPages,"?id=$topic_id&page="));
		}

	}
} else {
	$smarty->assign('topic_id', $topic_id);
	$smarty->assign('forum_id', $page['forum_id']);
}



$smarty->display($template, $cacheid);
