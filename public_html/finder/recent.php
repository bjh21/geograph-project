<?php
/**
 * $Project: GeoGraph $
 * $Id: contributors.php 6407 2010-03-03 20:44:37Z barry $
 * 
 * GeoGraph geographic photo archive project
 * This file copyright (C) 2008 Barry Hunter (geo@barryhunter.co.uk)
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
$template = 'finder_recent.tpl';

pageMustBeHTTPS();

$extra = array();

$src = 'loading="lazy" src'; //experimenting with moving to it permentanty!

if (true) {
	if (!empty($_GET['q'])) {
		$q=trim($_GET['q']);
	} else {
		$q = '';
	}

	$sphinx = new sphinxwrapper($q);
	if (!empty($sphinx->q))
		$extra[] = "q=".urlencode($sphinx->q);

	//gets a cleaned up verion of the query (suitable for filename etc)
	$cacheid = $sphinx->q;

	$sphinx->pageSize = $pgsize = 100;

	$pg = (!empty($_GET['page']))?intval(str_replace('/','',$_GET['page'])):0;
	if (empty($pg) || $pg < 1) {$pg = 1;}

	$cacheid .=".".$pg; //.$src;

	if (isset($_REQUEST['inner'])) {
		$cacheid .= '.iframe';
		$smarty->assign('inner',1);
		$extra[] = "inner";
	}

        if (!empty($_REQUEST['status']) && preg_match('/^\w+$/',$_REQUEST['status'])) {
                $cacheid .= '.'.$_REQUEST['status'];
                $smarty->assign('status',$_REQUEST['status']);
                $extra[] = "status=".$_REQUEST['status'];

		$sphinx->q .= " @status {$_REQUEST['status']}";
        }
	$cacheid = md5($cacheid);

	if (!$smarty->is_cached($template, $cacheid)) {

		$sphinx->processQuery();

		$client = $sphinx->_getClient();

		$db = GeographDatabaseConnection(true);

		$filter = 124913;
		if ($filter) {
			$rr = $db->getRow("SELECT gridimage_id FROM gridimage_search WHERE user_id != $filter ORDER BY gridimage_id DESC LIMIT 1099,1");
			$min = $rr['gridimage_id']; // GetOne annoyingly blindy adds LIMIT 1 to end, even if already a LIMIT :( - getRow does NOT!

			//$max = $db->getOne("SELECT MAX(gridimage_id) FROM gridimage_search WHERE user_id != 124913"); DOESNT USE INDEX!
			$max = $db->getOne("SELECT gridimage_id FROM gridimage_search WHERE user_id != $filter ORDER BY gridimage_id DESC"); //LIMIT 1
		} else {
			$max = $db->getOne("SELECT MAX(gridimage_id) FROM gridimage_search");
			$min = $max-1100;
		}
		$client->SetIDRange($min,$max+10);

			$bits = array();
			$bits[] = "uniqueserial(atakenyear)";
			$bits[] = "uniqueserial(takendays)";
			$bits[] = "uniqueserial(ahectad)";
			$bits[] = "uniqueserial(classcrc)";
			$bits[] = "uniqueserial(scenti)";
			$bits[] = "uniqueserial(uint(agridsquare))";
			if (!preg_match('/user_id/',$q)) {
				$bits[] = "uniqueserial(auser_id)";
			}
			$client->setSelect(implode('+',$bits)." as myint");
			$sphinx->sort = "myint ASC,sequence ASC";

		if ($filter) {
			$client->SetFilter('auser_id', array($filter), true);
		}

		$ids = $sphinx->returnIds($pg,'_images');

		if (!empty($ids)) {
			$where = "gridimage_id IN(".join(",",$ids).")";


			$limit = $pgsize;

			$prev_fetch_mode = $ADODB_FETCH_MODE;
			$ADODB_FETCH_MODE = ADODB_FETCH_ASSOC;
			$rows = $db->getAssoc("
			select gridimage_id,realname,user_id,title,grid_reference,imagetaken,reference_index
			from gridimage_search
			where $where
			limit $limit");

			$results = array();
			foreach ($ids as $c => $id) {
				$row = $rows[$id];
				$row['gridimage_id'] = $id;
				$row['canonical_domain'] = $CONF['canonical_domain'][$row['reference_index']];
				$gridimage = new GridImage;
                                $gridimage->fastInit($row);
				$results[] = $gridimage;
			}

			$smarty->assign_by_ref('results', $results);
			$smarty->assign("query_info",$sphinx->query_info);

			if ($sphinx->numberOfPages > 1) {
				$smarty->assign('pagesString', pagesString($pg,$sphinx->numberOfPages,$_SERVER['PHP_SELF']."?".implode('&amp;',$extra)."&amp;page=") );
				$smarty->assign("offset",(($pg -1)* $sphinx->pageSize)+1);
			}
			$ADODB_FETCH_MODE = $prev_fetch_mode;
		}
	}

	if (!empty($sphinx->qclean))
		$smarty->assign("q",$sphinx->qclean);
	$smarty->assign("src",$src);
        $smarty->assign("yesterday",date('Y-m-d',time()-3600*24));
}


$smarty->display($template,$cacheid);

