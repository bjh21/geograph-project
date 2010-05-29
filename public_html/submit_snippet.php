<?php
/**
 * $Project: GeoGraph $
 * $Id$
 * 
 * GeoGraph geographic photo archive project
 * This file copyright (C) 2009 Barry Hunter (geo@barryhunter.co.uk)
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
$template='submit_snippet.tpl';	

$USER->mustHavePerm("basic");

$gid = 0;

if (!empty($_GET['upload_id'])) {

	$gid = crc32($_GET['upload_id'])+4294967296;
	$gid += $USER->user_id * 4294967296;

	$smarty->assign('upload_id',$_GET['upload_id']);
	$smarty->assign('gridimage_id',$gid);
} elseif (!empty($_REQUEST['gridimage_id'])) {

	$gid = intval($_REQUEST['gridimage_id']);
	
	$image=new GridImage();
	$ok = $image->loadFromId($gid);
		
	if (!$ok) {
		die("invalid image");
	} elseif ($image->user_id != $USER->user_id && !$USER->hasPerm('moderator')) {
		die("unable to access this image");
	}
	
	$smarty->assign('gridimage_id',$gid);
}

$db = GeographDatabaseConnection(false);


if (!empty($_POST['create']) && (!empty($_POST['title']) || !empty($_POST['comment'])) ) {

	$updates = array();
	$updates['user_id'] = $USER->user_id;
	$updates['title'] = $_POST['title'];
	$updates['comment'] =  $_POST['comment'];
	
	$square=new GridSquare;
	if (!empty($_POST['nogr'])) {
		$updates['nateastings'] = 0;
		$point = "'POINT(0 0)'";
		
	} elseif ((!empty($_POST['grid_reference']) && $square->setByFullGridRef($_POST['grid_reference'],true)) || $square->setByFullGridRef($_GET['gr'],true) ) {
		
		require_once('geograph/conversions.class.php');
		$conv = new Conversions;
		list($lat,$long) = $conv->gridsquare_to_wgs84($square);

		if (!empty($_POST['grid_reference'])) {
			//we store these so can recreate the original GR - but only if specifically entered
			$updates['nateastings'] = $square->nateastings;
			$updates['natnorthings'] = $square->natnorthings;
			$updates['natgrlen'] = "".$square->natgrlen;//have to be careful its a enum but holding numberic - so needs string
		}
		$updates['reference_index'] = $square->reference_index;
		
		//for the sphinx index
		$updates['grid_reference'] = $square->grid_reference;

		$updates['wgs84_lat'] = $lat;
		$updates['wgs84_long'] = $long;
		
		//for mysql indexing (where sphinx not available) 
		$point = "'POINT({$square->nateastings} {$square->natnorthings})'";
	} else {
		$point = "'POINT(0 0)'";
	}
	
	$db->Execute('INSERT INTO snippet SET created=NOW(),point_en=GeomFromText('.$point.'),`'.implode('` = ?,`',array_keys($updates)).'` = ?',array_values($updates));
	
	if ($gid) {
		$updates = array();
		$updates['user_id'] = $USER->user_id;
		$updates['snippet_id'] = $db->Insert_ID();
		$updates['gridimage_id'] = $gid;

		$db->Execute('INSERT INTO gridimage_snippet SET `'.implode('` = ?,`',array_keys($updates)).'` = ?',array_values($updates));
	}
	if ($gid < 4294967296) {
		//clear any caches involving this photo
		$ab=floor($gid/10000);
		$smarty->clear_cache(null, "img$ab|{$gid}");
		
		$smarty->clear_cache("snippet.tpl", $updates['snippet_id']);
		
		$memcache->name_delete('sd', $gid);
	}
	
} elseif ($gid && !empty($_POST['remove'])) {
	
	$criteria = array();
	$criteria['gridimage_id'] = $gid;
	
	foreach ($_POST['remove'] as $id => $text) {
		
		$criteria['snippet_id'] = $id;
		
		$db->Execute('DELETE FROM gridimage_snippet WHERE `'.implode('` = ? AND `',array_keys($criteria)).'` = ?',array_values($criteria));
	}

	if ($gid < 4294967296) {
		//clear any caches involving this photo
		$ab=floor($gid/10000);
		$smarty->clear_cache(null, "img$ab|{$gid}");
		
		$smarty->clear_cache("snippet.tpl", $criteria['snippet_id']);
		
		$memcache->name_delete('sd', $gid);
	}

} elseif ($gid && !empty($_POST['add'])) {
	
	$updates = array();
	$updates['gridimage_id'] = $gid;
	$updates['user_id'] = $USER->user_id;
	
	foreach ($_POST['add'] as $id => $text) {
		
		$updates['snippet_id'] = $id;
		
		$db->Execute('INSERT IGNORE INTO gridimage_snippet SET `'.implode('` = ?, `',array_keys($updates)).'` = ?',array_values($updates));
	}
	
	if ($gid < 4294967296) {
		//clear any caches involving this photo
		$ab=floor($gid/10000);
		$smarty->clear_cache(null, "img$ab|{$gid}");
		
		$smarty->clear_cache("snippet.tpl", $updates['snippet_id']);
		
		$memcache->name_delete('sd', $gid);
	}
	
}



if ($gid) {
	$used = $db->getAll("SELECT * FROM gridimage_snippet INNER JOIN snippet USING (snippet_id) WHERE gridimage_id = $gid ORDER BY gridimage_snippet.created");

	$smarty->assign_by_ref('used',$used);
}
if (empty($_GET['gr']) && !empty($_GET['gr2'])) {
	$_GET['gr'] = $_GET['gr2'];
}

if (!empty($_REQUEST['gr']) || !empty($_REQUEST['q']) || !empty($_REQUEST['tab'])) {
	$square=new GridSquare;
	
	$grid_given=false;
	if (!empty($_REQUEST['gr'])) {
		if ($_REQUEST['gr'] == '-' || $_REQUEST['gr'] == 'none') {
			$_REQUEST['gr'] = '-';
			$smarty->assign('gr',$_REQUEST['gr']);
		} elseif ($grid_ok=$square->setByFullGridRef($_REQUEST['gr'],true)) {
			$grid_given = true;
		
			$smarty->assign('gr',$_REQUEST['gr']);

			if ($square->natgrlen > 4) {
				$smarty->assign('centisquare',1);
			}

		} else {
			$grid_given = true;
			print "invalid GR!";
		}
	}
	$fields = '';

	if ($grid_given && $grid_ok) {
		$fields = ",if(natnorthings > 0,(nateastings-{$square->nateastings})*(nateastings-{$square->nateastings})+(natnorthings-{$square->natnorthings})*(natnorthings-{$square->natnorthings}),0) as distance";
	}

	if (!empty($_REQUEST['tab']) && $_REQUEST['tab'] == 'recent') {  
		
		$results = $db->getAll($sql="SELECT s.* $fields FROM snippet s INNER JOIN gridimage_snippet gs USING (snippet_id) WHERE gs.user_id = {$USER->user_id} AND gridimage_id != $gid GROUP BY s.snippet_id ORDER BY gs.created DESC LIMIT 50"); 
		
		
		$smarty->assign('tab',$_REQUEST['tab']);
		
	} else {
		$where = array();
		$orderby = "ORDER BY s.snippet_id";
	
		if (!empty($_REQUEST['tab']) && $_REQUEST['tab'] == 'suggestions') {  
		
			$pg = 1;
			
			$q=preg_replace("/[^\w ]+/",' ',$_REQUEST['corpus']);
			$q=trim(preg_replace('/\b(or|and|the|geograph|amp|quot|pound|a|about|above|according|across|actually|adj|after|afterwards|again|against|all|almost|alone|along|already|also|although|always|among|amongst|an|another|any|anyhow|anyone|anything|anywhere|are|arent|around|as|at|b|be|became|because|become|becomes|becoming|been|before|beforehand|begin|beginning|behind|being|below|beside|besides|between|beyond|billion|both|but|by|c|can|cant|cannot|caption|co|co.|could|couldnt|d|did|didnt|do|does|doesnt|dont|down|during|e|each|eg|e.g.|eight|eighty|either|else|elsewhere|end|ending|enough|etc|etc.|even|ever|every|everyone|everything|everywhere|except|f|few|fifty|first|five|for|former|formerly|forty|found|four|from|further|g|h|had|has|hasnt|have|havent|he|hed|hell|hes|hence|her|here|heres|hereafter|hereby|herein|hereupon|hers|herself|him|himself|his|how|however|hundred|i|id|ill|im|ive|ie|if|in|inc|inc.|indeed|instead|into|is|isnt|it|its|its|itself|j|k|l|last|later|latter|latterly|least|less|let|lets|like|likely|ltd|m|made|make|makes|many|maybe|me|meantime|meanwhile|might|million|miss|more|moreover|most|mostly|mr|mrs|much|must|my|myself|n|namely|neither|never|nevertheless|next|nine|ninety|no|nobody|none|nonetheless|noone|nor|not|nothing|now|nowhere|o|of|off|often|on|once|one|ones|only|onto|other|others|otherwise|our|ours|ourselves|out|over|overall|own|p|per|perhaps|q|r|rather|recent|recently|s|same|seem|seemed|seeming|seems|seven|seventy|several|she|shed|shell|shes|should|shouldnt|since|six|sixty|so|some|somehow|someone|something|sometime|sometimes|somewhere|still|stop|such|t|taking|ten|than|that|thatll|thats|thatve|their|them|themselves|then|thence|there|thered|therell|therere|theres|thereve|thereafter|thereby|therefore|therein|thereupon|these|they|theyd|theyll|theyre|theyve|thirty|this|those|though|thousand|three|through|throughout|thru|thus|to|together|too|toward|towards|trillion|twenty|two|u|under|unless|unlike|unlikely|until|up|upon|us|used|using|v|very|via|w|was|wasnt|we|wed|well|were|weve|well|were|werent|what|whatll|whats|whatve|whatever|when|whence|whenever|where|wheres|whereafter|whereas|whereby|wherein|whereupon|wherever|whether|which|while|whither|who|whod|wholl|whos|whoever|whole|whom|whomever|whose|why|will|with|within|without|wont|would|wouldnt|x|y|yes|yet|you|youd|youll|youre|youve|your|yours|yourself|yourselves|z)\b/','',$q));
			
			if ($grid_given && $grid_ok) {
				$q .= ' '.$square->grid_reference.' '.$square->gridsquare;
			}
			
			if (strlen($q) > 40) {
				$q = '"'.$q.'"/4';
			} else {
				$q = '~'.$q;
			}
			
			$sphinx = new sphinxwrapper($q);
			$sphinx->pageSize = $pgsize = 40;

			$filters = array();
			if (!empty($_REQUEST['onlymine'])) {
				$filters['user_id'] = array($USER->user_id);
				$smarty->assign("onlymine",1);
			}
			if (!empty($_REQUEST['gr']) && $_REQUEST['gr'] == '-') {
				$filters['grid_reference'] = "none";
			}
			if (!empty($filters)) {
				$sphinx->addFilters($filters);
			}

			$ids = $sphinx->returnIds($pg,'snippet');

			if (!empty($ids) && count($ids)) {
				$id_list = implode(',',$ids);
				$where[] = "s.snippet_id IN($id_list)";
				$orderby = "ORDER BY FIELD(s.snippet_id,$id_list)";
			} else {
				$where[] = '0';
			}			
			$smarty->assign('tab',$_REQUEST['tab']);
			
		} elseif (!empty($_REQUEST['q']) && is_numeric($_REQUEST['q'])) {  

			$ids = $db->getCol("SELECT snippet_id FROM gridimage_snippet WHERE gridimage_id = ".intval($_REQUEST['q']));

			$ids[] = intval($_REQUEST['q']); //incase it's a snippet ID

			$where[] = "s.snippet_id IN (".implode(',',$ids).")";

			$_POST['radius'] = 1000; //it ignored anyway. 

		} elseif ($CONF['sphinx_host'] && !empty($_REQUEST['q'])) {  //todo - for the moment we only use sphinx for full text searches- because of the indexing delay 

			require_once('geograph/conversions.class.php');
			$conv = new Conversions;

			if (!empty($_REQUEST['page'])) {
				$pg = intval($_REQUEST['page']);
			} else {
				$pg = 1;
			}

			$q=trim($_REQUEST['q']);
			$q = preg_replace('/\b(description):/','comment:',$q);
			$q = preg_replace('/\b(name):/','realname:',$q);
			$q = preg_replace('/\b(gr):/','grid_reference:',$q);

			$sphinx = new sphinxwrapper($q);
			$sphinx->pageSize = $pgsize = 25;

			if (preg_match('/\bp(age|)(\d+)\s*$/',$q,$m)) {
				$pg = intval($m[2]);
				$sphinx->q = preg_replace('/\bp(age|)\d+\s*$/','',$sphinx->q);
			}

			$smarty->assign('q', $sphinx->qclean);
			if ($q) {
				$title = "Matching word search [ ".htmlentities($sphinx->qclean)." ]";
			}

			if (!empty($_REQUEST['gr']) && $_REQUEST['gr'] != '-' && (empty($_REQUEST['radius']) || $_REQUEST['radius'] <= 20) ) {
				$data = array();
				$data['x'] = $square->x;
				$data['y'] = $square->y;
				if ($square->natgrlen > 4) {
					list($data['lat'],$data['long']) = $conv->gridsquare_to_wgs84($square);
				}
				$data['d'] = !empty($_REQUEST['radius'])?floatval($_REQUEST['radius']):1;
				$data['sort'] = "@geodist ASC, @relevance DESC, @id DESC";

				$sphinx->setSort($data['sort']);
				$sphinx->setSpatial($data);
			}

			$filters = array();
			if (!empty($_REQUEST['onlymine'])) {
				$filters['user_id'] = array($USER->user_id);
				$smarty->assign("onlymine",1);
			}
			if (!empty($_REQUEST['gr']) && $_REQUEST['gr'] == '-') {
				$filters['grid_reference'] = "none";
			}
			if (!empty($filters)) {
				$sphinx->addFilters($filters);
			}

			$ids = $sphinx->returnIds($pg,'snippet');

			$smarty->assign("query_info",$sphinx->query_info);

			if (!empty($ids) && count($ids)) {
				$id_list = implode(',',$ids);
				$where[] = "s.snippet_id IN($id_list)";
				$orderby = "ORDER BY FIELD(s.snippet_id,$id_list)";
			} else {
				$where[] = '0';
			}
		} else {
			if (!empty($_REQUEST['gr']) && (empty($_REQUEST['radius']) || $_REQUEST['radius'] <= 20) ) {
				$radius = !empty($_REQUEST['radius'])?intval($_REQUEST['radius']*1000):1000;

				$left=$square->nateastings-$radius;
				$right=$square->nateastings+$radius;
				$top=$square->natnorthings-$radius;
				$bottom=$square->natnorthings+$radius;

				$rectangle = "'POLYGON(($left $bottom,$right $bottom,$right $top,$left $top,$left $bottom))'";

				$where[] = "CONTAINS(
						GeomFromText($rectangle),
						point_en)";
			}

			if (!empty($_REQUEST['onlymine'])) {
				$where[] = "s.user_id = {$USER->user_id}";
				$smarty->assign("onlymine",1);
			}

			if (!empty($_REQUEST['q'])) {
				$q=mysql_real_escape_string(trim($_REQUEST['q']));

				$where[] = "(title LIKE '%$q%' OR comment LIKE '%$q%')";
				$smarty->assign('q',trim($_POST['q']));
			}

			if (count($where) == 0) {
				$where[] = "0";
				$smarty->assign('empty',1);
			}

			$where[] = "enabled = 1"; 
		}

		$smarty->assign_by_ref('radius',$_POST['radius']);

		$where[] = 'ge.gridimage_id IS NULL';
		$where= implode(' AND ',$where);

		$results = $db->getAll($sql="SELECT s.*,realname,COUNT(gs.snippet_id) AS images,SUM(gs.user_id = {$USER->user_id}) AS yours $fields FROM snippet s LEFT JOIN user u USING (user_id) LEFT JOIN gridimage_snippet gs ON (s.snippet_id = gs.snippet_id AND gs.gridimage_id < 4294967296) LEFT JOIN gridimage_snippet ge ON (s.snippet_id = ge.snippet_id AND ge.gridimage_id = $gid) WHERE $where GROUP BY s.snippet_id $orderby LIMIT 200"); 
		#print $sql;
	}
	
	if ($fields) {
		foreach ($results as $id => $row) {
			if ($row['distance'] > 0)
				$results[$id]['distance'] = round(sqrt($row['distance'])/1000)+0.01;
		}
	}
	
	$smarty->assign_by_ref('grid_reference',$square->grid_reference);
	$smarty->assign_by_ref('results',$results);
} 

if (!empty($CONF['sphinx_host'])) {
	$smarty->assign('sphinx',1);
}
if (!empty($_GET['create'])) {
	$smarty->assign('create',1);
}






$smarty->display($template);

?>