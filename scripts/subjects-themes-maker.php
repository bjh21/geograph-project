<?

//these are the arguments we expect
$param=array('execute'=>0);

chdir(__DIR__);
require "./_scripts.inc.php";

############################################

$db = GeographDatabaseConnection(false);
$ADODB_FETCH_MODE = ADODB_FETCH_ASSOC;

   $sph = NewADOConnection($CONF['sphinxql_dsn']) or die("unable to connect to sphinx. ".mysql_error());

	require '3rdparty/Carrot2.class.php';
	$carrot = Carrot2::createDefault();

############################################

	$limit = 100;

	$recordSet = $db->Execute("SELECT subject,maincontext FROM subjects");
	$lookup = array();
	while (!$recordSet->EOF) {
		$row =& $recordSet->fields;

		$subject = $row['subject'];
		$extract = $row['maincontext'];

		$q = $sph->Quote("@subjects $subject");

print "$q\n";

		$ids = $sph->getAssoc("SELECT groupby() as tag_id,count(*) as ids FROM sample8 where match($q) group by context_ids order by ids desc");

		if (!empty($ids))
			$extract = implode(", ",$db->getCol($sql = "SELECT tag FROM tag WHERE tag_id IN (".implode(",",array_keys($ids)).")"));

		$lookup[] = $subject;
		$carrot->addDocument(
			(string)$subject,
			(string)utf8_encode(htmlentities($subject)),
			(string)utf8_encode(htmlentities($extract))
		);
		$recordSet->MoveNext();
	}
	$recordSet->Close();

	$c = $carrot->clusterQuery();
		function cmp(&$a, &$b) {
		    return strcmp($a->label,$b->label);
		}
		usort($c, "cmp");

	if (!$param['execute']) {
		foreach ($c as $cluster) {
			$count = count($cluster->document_ids);
			print "{$cluster->label}   x{$cluster->score}    ($count docs)\n";
		}
		//print_r($c);
		exit;
	}


exit;

	foreach ($c as $cluster) {
		$count = count($cluster->document_ids);
		printf("%5d. %s\n",$count,$cluster->label);

		$l2 = mysql_real_escape_string($cluster->label);
		foreach ($cluster->document_ids as $sort_order => $document_id) {
			$content_id = $lookup[$document_id];
			$sql = "INSERT INTO content_group SET content_id = $content_id, label = '$l2',score = {$cluster->score},sort_order=$sort_order,source='carrot2'";
			//print "$sql;\n";
		}
	}

	print "done.\n";
