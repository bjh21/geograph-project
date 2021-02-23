<?

//these are the arguments we expect
$param=array('explain'=>false,'tables'=>'');

chdir(__DIR__);
require "./_scripts.inc.php";

############################################

	$db = GeographDatabaseConnection(false);
	$ADODB_FETCH_MODE = ADODB_FETCH_ASSOC;

############################################


$color = "\033[31m";
$white ="\033[0m";

if (!empty($param['tables'])) {
	$where = "table_name LIKE '".implode("%' OR table_name LIKE '",explode(',',$param['tables']))."%'";
	$tables = $db->getAll("SELECT * FROM material_view WHERE $where ORDER BY table_name");
} else {
	$tables = $db->getAll("SELECT * FROM material_view WHERE description != 'obsolete' ORDER BY table_name");
}

foreach ($tables as $row) {
	print sprintf('%40s',"$color{$row['table_name']}$white");

	$status = $db->getRow("SHOW TABLE STATUS LIKE '{$row['table_name']}'");
	print "  Update = {$status['Update_time']}";

        if (!empty($status['Update_time']) ) { // && strtotime($status['Update_time']) > (time() - 60*60*12) && $status['Comment'] != 're$

                        $seconds = time() - strtotime($status['Update_time']);
                        $hours = ceil($seconds/60/60);
                        $hours++; //just to be safe

		print " ... (interval $hours hour)";
	}

	print "\n";
}

$sql = "SELECT i.table_name,i.column_name
 FROM information_schema.columns i
 inner join material_view m using (table_name)
 left join material_view_column c using (table_name,column_name)
 where table_schema = DATABASE() and c.column_name is null";

dump_table($sql,"undefined columns...");


function dump_table($sql,$title = '') {
        global $db;
        if (is_string($sql)) {
                $sql = $db->getAll($sql);
        }
        if (empty($sql))
                return;
	print "$title\n";
        print implode("\t",array_keys($sql[0]))."\n";
        foreach ($sql as $row)
                print implode("\t",$row)."\n";
        print "\n";
}




