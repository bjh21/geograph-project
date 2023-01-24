<?

//these are the arguments we expect
$param=array('execute'=>0,'count'=>0,'square'=>'NS5965', 'limit'=>1000, 'query'=>'', 'debug'=>false,'sleep'=>0, 'fix'=>false);

chdir(__DIR__);
require "./_scripts.inc.php";

############################################

$db = GeographDatabaseConnection(false);

if (!empty($DSN_READ)) {
	$db_read = GeographDatabaseConnection(true);
} else {
	$db_read = $db;
}
$ADODB_FETCH_MODE = ADODB_FETCH_ASSOC;

	require '3rdparty/Carrot2.class.php';
	$carrot = Carrot2::createDefault();

if (posix_isatty(STDOUT) && !$param['debug'])
	$param['debug']=1;

############################################

if (!empty($param['fix'])) {
	//$where = " label regexp '&\\\\w+' "; //regex, inside sql, inside php, hence 4 slashes!
	$where = " label regexp binary '&[a-z]{2,}' ";

	$squares = $db_read->getAssoc("SELECT grid_reference,gridsquare_id FROM gridimage_group_stat INNER JOIN gridsquare USING (grid_reference) WHERE $where LIMIT {$param['count']}");

} elseif (!empty($param['count'])) {
	$squares = $db_read->getAssoc("SELECT grid_reference,gridsquare_id FROM gridsquare WHERE imagecount BETWEEN 5 AND {$param['limit']} AND last_grouped < last_timestamp LIMIT {$param['count']}");

} elseif (!empty($param['square'])) {
	$squares = array(
		$param['square'] => $db_read->getOne("SELECT gridsquare_id FROM gridsquare WHERE grid_reference = ".$db->Quote($param['square']))
	);
}

if (empty($squares)) {
	if ($param['debug'])
		print "No squares to process\n";
	exit(1); //status 1 means the next command in chain doesnt execute!
}
############################################

		function cmp(&$a, &$b) {
		    return strcmp($a->label,$b->label);
		}

foreach ($squares as $square => $gridsquare_id) {
	if (empty($gridsquare_id)) {
		print "unknown id for $square\n";
		exit(1);
	}

	######################
	// fetch text

	$recordSet = $db_read->Execute("SELECT gridimage_id,title,comment FROM gridimage_search WHERE grid_reference = '{$square}' LIMIT {$param['limit']}");
	$lookup = array();
	$titles = array();
	while (!$recordSet->EOF) {
		$row =& $recordSet->fields;

		$lookup[] = $row['gridimage_id'];
		if (substr_count($row['title'],' ') > 0) //skip single words for now (we remove the last word!)
			@$titles[trim($row['title'],' .')][] = $row['gridimage_id'];

		$carrot->addDocument(
			$row['gridimage_id'],
			latin1_to_utf8($row['title']),
                        strip_tags(str_replace('<br>',' ',latin1_to_utf8($row['comment'])))
		);
		$recordSet->MoveNext();
	}
	$recordSet->Close();

	######################
	// do the carrot processing

	$c = $carrot->clusterQuery($param['query'],$param['debug']==='2');
	if (empty($c)) {
		die("no results for $square (dieing without processing any more squares)\n");
	}


	usort($c, "cmp");

	if (!$param['execute']) {
		foreach ($c as $cluster) {
			$count = count($cluster->document_ids);
			print "{$cluster->label}   x{$cluster->score}    ($count docs)\n";
		}
		//print_r($c);
		foreach ($titles as $title => $ids)
			if (count($ids) > 1)
				print "Title: $title  (".count($ids)." docs)\n";
		exit;
	}

	if ($param['debug'])
		print "found ".count($c)." clusters for $square\n";

	######################
	// store the carrot2 data

	$db->Execute("delete gridimage_group.* from gridimage inner join gridimage_group using (gridimage_id) where gridsquare_id = $gridsquare_id and source in ('carrot2','title')");
	if ($param['debug'])
		print "clear1\n";

	foreach ($c as $cluster) {
		if ($param['debug']) {
			$count = count($cluster->document_ids);
			printf("%5d. %s ",$count,$cluster->label);
		}
		//we always filter these out, so might as well not bother even saving!
		// where label not in ('(other)','Other Topics')
		if ($cluster->label == 'Other Topics' || $cluster->label == '(Other)')
			continue;

		$values = array();
		foreach ($cluster->document_ids as $sort_order => $document_id) {
                        $updates = array();

                        $updates['gridimage_id'] = $lookup[$document_id];
                        $updates['label'] = $cluster->label;
                        $updates['score'] = floatval($cluster->score);
                        $updates['sort_order'] = $sort_order;
                        $updates['source'] = 'carrot2';

                        //$db->Execute('INSERT INTO gridimage_group SET `'.implode('` = ?,`',array_keys($updates)).'` = ?',array_values($updates));
			//print ".";
			$updates['label'] = $db->Quote($updates['label']);
			$updates['source'] = $db->Quote($updates['source']);
			$values[] = "(".implode(',',$updates).")";
		}
		$sql = "INSERT INTO gridimage_group (`".implode('`,`',array_keys($updates))."`) VALUES ".implode(',',$values);
		$db->Execute($sql);
		if ($param['debug'])
			print ".. ".$db->Affected_Rows()." affected\n";
	}

	#############################
	// do custom title prefix custering

	$group_by_id = array();
	$group_by_stem = array();
	foreach ($titles as $title => $ids) {
	        $words = explode(' ',trim($title,'. '));
	        array_pop($words); //remove the LAST word!
	        $stem = preg_replace('/[^\w]+$/','',implode(' ',$words));  //the replace removes commas etc from end of words (so 'The Black Horse, Nuthurst', necomes 'The Black Horse')

		foreach ($titles as $title2 => $ids2) {
			//if ($title != $title2)
			//	print "$title != $title2 && strpos($title2,$stem) == ".strpos($title2,$stem)."\n";
			if ($title != $title2 && strpos($title2,$stem) === 0) {
				foreach ($ids as $id)	@$group_by_id[$id][$stem]=1;
				foreach ($ids2 as $id)	@$group_by_id[$id][$stem]=1;

				foreach ($ids as $id)	@$group_by_stem[$stem][$id]=1; //need to store ides to deduplciate
				foreach ($ids2 as $id)	@$group_by_stem[$stem][$id]=1;
			}
        	}
	}
	//print_r2($group_by_id);
	//print_r2($group_by_stem);
	$values = array();
	foreach ($group_by_id as $id => $stems) {
		$longest = null;
		$length = 0;
		foreach ($stems as $stem => $dummy)
			if (strlen($stem) > $length && count($group_by_stem[$stem]) > 1) { //only interested in stems with multiple anyway!
				$longest = $stem;
				$length = strlen($stem);
			}
		if ($param['debug'])
			print "$id, $longest (".count($group_by_stem[$stem])." docs)\n";
		if ($longest) {
			if ($longest == 'The' || $longest == 'Looking') //may need to blacklist more, but this one to start!
				continue;
                        $updates = array();

                        $updates['gridimage_id'] = $id;
                        $updates['label'] = $longest." #";
                        $updates['source'] = 'title';

			$updates['label'] = $db->Quote($updates['label']);
			$updates['source'] = $db->Quote($updates['source']);
			$values[] = "(".implode(',',$updates).")";
		}
	}

	if (!empty($values)) {
		$sql = "INSERT INTO gridimage_group (`".implode('`,`',array_keys($updates))."`) VALUES ".implode(',',$values);
		$db->Execute($sql);
		if ($param['debug'])
			print ".. ".$db->Affected_Rows()." affected\n";
	}

	######################
	//update the stats table

	$db->Execute("delete from gridimage_group_stat where grid_reference = '{$square}'");
	if ($param['debug'])
		print "clear2";

	//copied almost as is from RebuildGridimageGroupStat.class.php (just changed the where clause!)
        $sql = "
                select null as gridimage_group_stat_id, grid_reference, label
                        , count(*) as images, count(distinct user_id) as users
                        , count(distinct imagetaken) as days, count(distinct year(imagetaken)) as years, count(distinct substring(imagetaken,1,3)) as decades
                        , min(submitted) as created, max(submitted) as updated, gridimage_id
                        , SUBSTRING_INDEX(SUBSTRING_INDEX(GROUP_CONCAT(submitted ORDER BY submitted),',',2),',',-1) AS `second`
                        , avg(wgs84_lat) as wgs84_lat, avg(wgs84_long) as wgs84_long
                from gridimage_group inner join gridimage_search using (gridimage_id)
                where label not in ('(other)','Other Topics') and grid_reference = '{$square}'
                group by grid_reference, label having images > 1 order by null";
		//TODO, mariadb, supports LIMIT in group_concat! so GROUP_CONCAT(submitted ORDER BY submitted LIMIT 1,1) AS `second` should work!!

	$db->Execute("INSERT INTO gridimage_group_stat $sql");
	if ($param['debug'])
		print " grouped\n";

	######################

	$db->Execute("UPDATE gridsquare SET last_grouped = NOW(),last_timestamp=last_timestamp WHERE gridsquare_id = $gridsquare_id");

	######################

	print "done $square.\n";

	if (!empty($_SERVER['BASE_DIR']) && file_exists($_SERVER['BASE_DIR'].'/shutdown-sential'))
        	break;

	if ($param['sleep'])
		sleep($param['sleep']);
	$carrot->clearDocuments();
}




function print_r2($var) {
	print str_repeat('#',80)."\n";
		$trace = debug_backtrace();
		print ' In ' . $trace[0]['file'] .' on line ' . $trace[0]['line']."\n";

		if (empty($var))
			var_dump($var);
		else
			print_r($var);
	print str_repeat('~',80)."\n";
}
