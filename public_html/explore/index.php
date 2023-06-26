<?php
/**
 * $Project: GeoGraph $
 * $Id: index.php 6354 2010-02-04 00:54:08Z geograph $
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

customGZipHandlerStart();
customExpiresHeader(360,false,true);



$smarty = new GeographPage;

//regenerate?
if (!$smarty->is_cached('explore.tpl'))
{
	$db = GeographDatabaseConnection(true);

	$countylist = array();
	$prev_fetch_mode = $db->SetFetchMode(ADODB_FETCH_NUM);
	$recordSet = $db->Execute("SELECT reference_index,county_id,name FROM loc_counties WHERE n > 0");
	while (!$recordSet->EOF)
	{
		$countylist[$CONF['references'][$recordSet->fields[0]]][$recordSet->fields[1]] = $recordSet->fields[2];
		$recordSet->MoveNext();
	}
	$recordSet->Close();
	$db->SetFetchMode($prev_fetch_mode);
	$smarty->assign_by_ref('countylist', $countylist);

	$topicsraw = $db->GetAssoc("select gp.topic_id,concat(topic_title,' [',count(*),']') as title,forum_name from gridimage_post gp
		inner join geobb_topics using (topic_id)
		inner join geobb_forums using (forum_id)
		group by gp.topic_id
		having count(*) > 4
		order by geobb_topics.forum_id desc,topic_title");

	$topics=array("1"=>"Any Topic");

	$options = array();
	$last = false;
	foreach ($topicsraw as $topic_id => $row) {
		if ($last != $row['forum_name'] && $last) {
			$topics[$last] = $options;
			$options = array();
		}
		$last = $row['forum_name'];

		$options[$topic_id] = $row['title'];
	}
	$topics[$last] = $options;

	$smarty->assign_by_ref('topiclist',$topics);

	if ($CONF['template'] == 'ireland') {
	        $ri = 2;
	} else {
	        $ri = 1; //this is delibeate, sarch engines etc, index only GB images on .org.uk, uses .ie for all ireland photos. (.org.uk will redirect!)
	}
	$squares = $db->getCol("select grid_reference from browse_cluster c inner join gridsquare g using (gridsquare_id) where reference_index = $ri and c.upd_timestamp is not null order by c.upd_timestamp desc limit 100");
	$smarty->assign_by_ref('squares',$squares);
}


$smarty->display('explore.tpl');


