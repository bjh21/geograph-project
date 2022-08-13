<?php
/**
 * $Project: GeoGraph $
 * $Id: recreate_maps.php 2996 2007-01-20 21:39:07Z barry $
 *
 * GeoGraph geographic photo archive project
 * This file copyright (C) 2005 Barry Hunter (geo@barryhunter.co.uk)
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

############################################

//these are the arguments we expect
$param=array(
	'host'=>'',
	'rows'=>0,
	's' => 'MyISAM',
	'd' => 'InnoDB',
	'type' => '',
	'q' => '',
);

chdir(__DIR__);
require "./_scripts.inc.php";

############################################

$host = $CONF['db_connect'];
if ($param['host']) {
    $host = $param['host'];
}
print(date('H:i:s')."\tUsing server: $host\n");
$DSN = str_replace($CONF['db_connect'],$host,$DSN);

//uses $GLOBALS['DSN']
$db = GeographDatabaseConnection(false);
$ADODB_FETCH_MODE = ADODB_FETCH_ASSOC;

print $DSN."\n";

print "database: ".$db->getOne("SELECT DATABASE()")."\n";


print_r($db->getAll("SHOW MASTER STATUS"));

############################################

$where = array();
$where[] = "table_schema = DATABASE()";
//$where[] = "type+0 <=4";
if (!empty($param['rows']))
	$where[] = "TABLE_ROWS > ".$param['rows'];
if (!empty($param['s']))
        $where[] = "ENGINE = ".$db->Quote($param['s']);
else
	$where[] = "ENGINE != ".$db->Quote($param['d']);
if (!empty($param['type']))
	$where[] = "type LIKE ".$db->Quote($param['type']);

if (!empty($param['q']))
	$where[] = "table_name LIKE ".$db->Quote($param['q']);

if ($param['d'] == 'InnoDB')
	$where[] = "toidb_seconds IS NULL";

############################################

$where = implode(" AND ",$where);
$rows = $db->getAll("select table_name,title,type,backup,`sensitive`,ENGINE,TABLE_ROWS,DATA_LENGTH,INDEX_LENGTH,UPDATE_TIME
 from information_schema.tables left join _tables using (table_name)
 where $where order by type+0,table_name");

############################################

$r = '';
foreach ($rows as $row) {
	if ($row['table_name'] == 'gridimage_queue'
		 || strpos($row['table_name'],'checksum') !== FALSE
		 || preg_match('/^(test|tmp)/',$row['table_name'])
		 || preg_match('/_(old|backup|back|merge|test|tmp|tmp2)$/',$row['table_name'])) {
		print "SKIPPING {$row['table_name']}\n\n";
		continue;
	}

	restart:
	print str_repeat('#',80)."\n";
	print implode("\t",$row)."\n";

	$sql = "select TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX,COLUMN_NAME,INDEX_TYPE,CARDINALITY,DATA_TYPE 
	from information_schema.STATISTICS inner join information_schema.columns using (TABLE_SCHEMA,TABLE_NAME,COLUMN_NAME) 
	where TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ".$db->Quote($row['table_name']);
	$data = $db->getAll($sql);
	$primary = 0;
	if ($data) foreach ($data as $index) {
		if ($index['INDEX_NAME'] == 'PRIMARY')
			$primary++;
		if ($index['INDEX_NAME'] == 'PRIMARY' && $index['SEQ_IN_INDEX'] > 1)
			print "Compound Primary key ({$index['COLUMN_NAME']})\n";
		if ($index['INDEX_NAME'] == 'PRIMARY' && strpos($index['DATA_TYPE'],'int') === FALSE)
			print "Non-Int Primary key ({$index['COLUMN_NAME']} : {$index['DATA_TYPE']})\n";

		if ($index['INDEX_TYPE'] == 'SPATIAL')
			print "Has Spatial Index ({$index['COLUMN_NAME']})\n";
		elseif ($index['INDEX_TYPE'] == 'FULLTEXT')
			print "Has FullText Index ({$index['COLUMN_NAME']})\n";

		//todo
		//PHP Fatal error:  mysqli error: [1118: Row size too large (> 8126). Changing some columns to TEXT or BLOB may help. In current row format, BLOB prefix of 0 bytes is stored inline.] in EX
		//sum up lengs of varchar?
	}
	if (empty($data))
		print "No Keys at all!\n";
	elseif (empty($primary))
		print "No Primary Key!\n";



	$sql = "ALTER TABLE `{$row['table_name']}` ENGINE={$param['d']}";


	print "$sql;\n";
	if ($r != 'a')
		$r = readline('Execute (c/l/d/y)? ');
	if ($r == 'c') {
		$c = $db->getRow("SHOW CREATE TABLE `{$row['table_name']}`");
		print array_pop($c).";\n";
		goto restart;

	} elseif ($r == 'l') {
		$r = $db->getRow("SHOW TABLE STATUS FROM `geograph_live` LIKE '{$row['table_name']}'");
		print_r($r);
		goto restart;

	} elseif ($r == 'd') {
		$sql = "UPDATE _tables SET type = 'derivied' WHERE table_name = ".$db->Quote($row['table_name']);
		$db->Execute($sql);
		$sql = "UPDATE geograph_live._tables SET type = 'derivied' WHERE table_name = ".$db->Quote($row['table_name']);
		$db->Execute($sql);

	} elseif ($r == 'y' || $r == 'a') {

		$start = microtime(true);
		$db->Execute($sql);
		$end = microtime(true);
		print date('r').sprintf(', took %.3f seconds, %d affected rows.',$end-$start,$db->Affected_Rows())."\n\n";

		if ($param['d'] == 'InnoDB') {
			$seconds = round($end-$start);
			$sql = "UPDATE _tables SET toidb_seconds = $seconds WHERE table_name = ".$db->Quote($row['table_name']);
			$db->Execute($sql);
		}
	}
}

