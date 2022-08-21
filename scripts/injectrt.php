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


//these are the arguments we expect
$param=array(
	'host'=>false,

        'schema'=>false, //show the schema used to create a new sphinx index.
		'drop' => false,
	'data'=>true,

	'file'=>false, //read a sphinx.conf file and output similar index (overrides index, select, and $options)
	'table'=>false, //read a table, and automatically create a select statement

	'cluster' => 'manticore',
	'index' => 'gridimage', // : as part of clusrter
	'select' => "SELECT * FROM sphinx_view",
	//BE WEARY OF ADDING GROUP BY TO THIS QUERY, AS THE SCHEMA BELOW USING LIMIT 1 WILL STRUGGLE.

	'limit' => 1, //if sepcified, myst be under 1000!
);

$multis = array();
$options = array();

#########################################################

$cwdir = getcwd();

chdir(__DIR__);
require "./_scripts.inc.php";

//bodge for now! - could be rad from sample6.conf
if (strpos($param['select'], 'sphinx_view') && empty($param['file']) && empty($param['table'])) {
	//sql_attr_multi          = uint my_square from ranged-main-query; \
	//        select gridimage_id, u.user_id from gridimage_search inner join user_gridsquare u using (grid_reference) WHERE  WHERE gridimage_id>=$start AND gridimage_id<=$end order by gridimage_id
	$multis['my_square'] = 'select u.user_id from gridimage_search inner join user_gridsquare u using (grid_reference) WHERE gridimage_id = $id';

	$multis['content_ids'] = '
        (select content_id from gridimage_content WHERE gridimage_id = $id)
        UNION (select 1 as content_id from gallery_ids where baysian > 3 AND id = $id)
        UNION (select 2 as content_id from gridimage_daily where showday <= date(now()) and gridimage_id = $id)
        UNION (select content_id from content inner join gridimage_post gp on (foreign_id=topic_id) where source in (\'gallery\',\'themed\',\'gsd\') and  gp.gridimage_id = $id)
        UNION (select content_id from content inner join gridimage_snippet gs on (foreign_id=snippet_id) where source = \'snippet\' AND gs.gridimage_id = $id)';
}

############################################

$host = empty($CONF['db_read_connect'])?$CONF['db_connect']:$CONF['db_read_connect'];
if ($param['host']) {
    $host = $param['host'];
}
fwrite(STDERR,date('H:i:s')."\tUsing db server: $host\n");
$DSN = str_replace($CONF['db_connect'],$host,$DSN);

//uses $GLOBALS['DSN'] (which is already setup by global.inc) rather than $CONF['db_connect']
$db = GeographDatabaseConnection(false);
$ADODB_FETCH_MODE = ADODB_FETCH_ASSOC;

#########################################################

if (!empty($param['file'])) {
	chdir($cwdir);
	$text = file_get_contents($param['file']);

	//the config file allow 'escaped new lines' - remove them for easier parsing
	$text = str_replace("\\\n",' ',str_replace("\r",'',$text));

	//this is a very rudimentry file parser, it wont cope with everything!
	//and only one index per file for now!

	if (preg_match_all('/sql_query\s*=(.+?)$/m',$text,$m)) {
		foreach ($m[1] as $query) {
			if (strpos($query,'sleep(')) //this is to skip the master query!
				continue;
			$param['select'] = trim(preg_replace('/\s+/',' ',$query));
			break;
		}
	}

	//todo, if sql_query_range then may need to correct the dynamic variables. 

	//we DONT read the sql_attr* etc, just let them autodtect after running the query. 

	if (preg_match('/^\s*index\s+(\w+)/m',$text,$m) && $param['index'] == 'gridimage') { //just looking if still the 'default!
		$param['index'] = $m[1];
	}

	if (preg_match('/index\s+'.$param['index'].'\b(\s*:\s*\w+)?.*?\{(.+?)\}/s',$text,$m)) {
		foreach(explode("\n", $m[2]) as $line) {
			if (preg_match('/^\s*(\w+)\s*=\s*(.+)/',$line,$m) && !in_array($m[1],array('type','source','path','dict')))
				$options[$m[1]] = trim($m[2]);
		}
        }
}

#########################################################

if (!empty($param['table'])) {
	$columns = $db->getAssoc("DESCRIBE {$param['table']}");
	$cols = $sheets = array();
	foreach ($columns as $name => $row) {
		if ($row['Type'] == 'point') //its just a index for other cols
			continue;
		if (strpos($name,'_soundex') !== FALSE) //these are just 'index' cols as well
			continue;
		if (preg_match('/(lat|long)_(deg|min)/',$name) || $name == 'gmt') //the os gaz has lat/long in weird split format
			continue;
		if ($name == 'e_date' || $name == 'update_co') //no point indexing the date!
			continue;
fwrite(STDERR,"$name ".substr($row['Type'],0,40)."\n");

		if (preg_match('/sheet_\d/',$name)) {
			$sheets[] = $name;
		} elseif (strpos($row['Type'],'int(') !== FALSE) {
			if ($row['Key'] == 'PRI')
				array_unshift($cols,"{$name} AS id"); //make sure it first in index, even if not first in table
			else
				$cols[] = $name; //probably want it as attribute!
		} elseif (preg_match('/(\w+)_utf/',$name,$m)) {
			if (($idx = array_search($m[1],$cols)) !== FALSE)
				$cols[$idx] = "$name as {$m[1]}"; //replace it!
			else
				$cols[] = "$name as {$m[1]}";
		} elseif (preg_match('/char\((\d+)/',$row['Type'],$m) || $row['Type'] == 'text' || preg_match('/enum\(/',$row['Type'],$m)) {
			$cols[] = $name;
		} else
			fwrite(STDERR,"$name {$row['Type']} unknown\n");
	}
	if (!empty($sheets)) {
		$cols[] = "concat_ws(',',nullif(".implode(",0),nullif(",$sheets).",0)) as sheet_ids";
	}

	$cols = implode(", ",$cols);
	$param['select'] = "SELECT $cols FROM {$param['table']}";

	fwrite(STDERR,"Generated: {$param['select']};\n");

	if ($param['index'] == 'gridimage') //just looking if still the 'default!
		$param['index'] = $param['table']; //create index of same name
}

#########################################################

if (!empty($param['schema'])) {

	$sql = $param['select'];

	$result = $db->Execute("$sql LIMIT 1") or die($db->ErrorMsg());

	if (!empty($param['drop'])) {
		if (!empty($param['cluster']))
			print "ALTER CLUSTER manticore DROP {$param['index']};\n";
		print "DROP TABLE IF EXISTS {$param['index']};\n";
	}

	print "CREATE TABLE {$param['index']} ("; $sep = "\n";

	$row =& $result->fields; //just so can do quick iset on an assotitive array
	$fields = $result->fieldCount();
	for ($i=1; $i < $fields; $i++) { //skips the first column - id is automatic bigint!
		$r = $result->FetchField($i);
		$name  = $r->name;
			//the numberic is mysqli - todo, maybe swithc to MetaType?

//if ($name == 'snippets')
//	print_r($r);

		$enc = mb_detect_encoding($row[$name], 'UTF-8, ISO-8859-15, ASCII');
		if ($enc == 'ISO-8859-15')
			fwrite(STDERR,"\n$name encoding = $enc\n");


		$type = "text"; //defaults to 'indexed stored'
		switch ($r->type) {
			case 'string': case 253:
			case 'blob':   case 252:
			case 'mediumblob': case 250:
			case 'longblob': case 251:
				if ($name == 'comment' || $name == 'words' || $name == 'url' || $name=='tags') {
					//leave a simple field - dont want attribute
					$type = 'text'; //defaults to 'indexed stored'
				} elseif (preg_match('/s$/',$name) && array_key_exists(preg_replace('/s$/','_ids',$name),$row)) { //isset doesnt find columns will null in them!
					$type = 'text'; //defaults to 'indexed stored'
				} elseif (preg_match('/_ids$/',$name)) {
					//print "sql_attr_multi		= uint $name from field\n";
					$type = 'multi';
				} elseif (preg_match('/_json$/',$name)) {
					$type = 'json';
				} elseif ($name == 'user' || $name == 'larger') {
					$type = 'text indexed'; //no point storing, we have proper atttibute to get the value (these are just 'fake' keywords)
				} elseif ($name == 'hash') {
					$type = 'string'; //only need to sort+retrive, no indexing
				} else {
					//most fields want a string atttribute too
					//print "sql_field_string	= $name\n";
					$type = 'string attribute indexed'; //todo some might not be indexed!
				}
				break;
			case 'binary':  case 254:
				if (preg_match('/grlen$/',$name)) {
                        	        $type = 'bit(3)'; //stored as enum, so come out as strings, but really an interger
					//todo - They are saved in 32-bit chunks, so in order to save space they should be grouped at the end of attributes definitions
				} elseif ($name == 'source' && array_key_exists('asource',$row)) { //this is from the content table
					$type = 'text indexed'; //no point storing, we have proper atttibute to get the value
				} elseif (empty($r->binary)) {
					//appears to be enum, comes as binary type, but not actully binary!
					$type = 'string attribute indexed';
				} else {
					$type = "TODO-CHECK";
				}
				break;
			case 'smallint': case 2:
					case 1: //percent_land is currently coming out as bool! but us really 0-100 and even -1
				if ($r->unsigned && $name != 'east' && $name != 'north' && $name != 'y' && $name != 'x') //we need signed int for coordinates - to do the maths correctly!
					$type = 'bit(24)';
				else
					$type = 'bigint'; //only signed type manticore has!
				break;
			case 'mediumint': case 9:
				if ($r->unsigned && $name != 'east' && $name != 'north' && $name != 'y' && $name != 'x') //we need signed int for coordinates - to do the maths correctly!
					$type = 'bit(16)';
                                else
                                        $type = 'bigint'; //only signed type manticore has!
				break;
			case 'int':   case 3:
				if ($name == 'submitted') {
					//print "sql_field_timestamp	= $name\n";
				} else {
					//todo - set bits based on $len
					//print "sql_attr_uint		= $name\n";
				}
				$type = 'integer'; //todo, bigint if unsigned!
				break;
			case 'bigint': case 8:
				if (in_array($name,array('viewsquare','submitted','asource','updated','created')) || $r->unsigned || strpos($name,'timestamp') !== FALSE)
					$type = 'integer'; //actully fine in this case
				else
					$type = 'bigint';
				break;
			case 'real': case 4:
				case 5: //double - sphinx doesnt have!
				//print "sql_attr_float		= $name\n";
				$type = "float";
				break;
			case 'decimal': case 246:
				if ($r->decimals == 0)
					$type = 'integer';
				else
					$type = 'float';
				break;
		}
		if ($type)
			print "$sep\t`$name` $type"; //  #{$row[$name]}";
		$sep = ",\n";
	}
	foreach($multis as $name => $query)
		print "$sep\t`$name` multi";

	print ")";
	if (!empty($options)) {
		foreach ($options as $key => $value) {
			print " $key=".$db->Quote($value);
		}
	}
	print ";\n";
	if (!empty($param['cluster']))
		print "ALTER CLUSTER {$param['cluster']} ADD {$param['index']};\n";
}

#########################################################

if (!empty($param['data'])) {
	if (!empty($param['cluster']))
		$param['index'] = "{$param['cluster']}:{$param['index']}";

	$lastid = 0;
	while(true) {
		//todo, this looping is designd for selecting FROM manticore, not really needed for selecting from DB!

		if (!empty($param['limit'])) { // && $param['limit']<=1000) { //TODO: if there is a limit, but over 1000, will have to just keep looping until get the right number!
			$postfix = " LIMIT ".$param['limit'];
			$limit = max(1000,$param['limit']);
		} else {
			$id = "id";
			if (preg_match('/SELECT( SQL_NO_CACHE)? (\w+\.)?(\w+) AS id/i',$param['select'],$m))
				$id = $m[3];

			//otherwise loop though 1000 rows at a time...
			if (preg_match('/\bWHERE\b/i',$param['select'])) {
				$postfix = ($lastid)?" AND $id > $lastid":'';
			} else {
				$postfix = ($lastid)?" WHERE $id > $lastid":'';
			}
			$postfix .= " ORDER BY $id ASC LIMIT 1000"; //todo, autodetect what max_matches is set to!!
			$limit = 1000;
		}

		$result = mysqli_query($db->_connectionID,$param['select'].$postfix) or die("unable to run {$param['select']}$postfix;\n".mysqli_error($db->_connectionID)."\n\n");

	        if (!mysqli_num_rows($result)) //todo, use show meta instead?
        	        break;

		fwrite(STDERR,"-- dumping ".mysqli_num_rows($result)." rows from $lastid\n\n");

		$names=array();
		$types=array();
		$fields=mysqli_fetch_fields($result);

		foreach ($fields as $key => $obj) {
			$names[] = $obj->name;
			switch($obj->type) {
		                case MYSQLI_TYPE_INT24 :
        		        case MYSQLI_TYPE_LONG :
                		case MYSQLI_TYPE_LONGLONG :
		                case MYSQLI_TYPE_SHORT :
        		        case MYSQLI_TYPE_TINY :
					$types[] = 'int'; break;
				case MYSQLI_TYPE_FLOAT :
				case MYSQLI_TYPE_DOUBLE :
				case MYSQLI_TYPE_DECIMAL :
					$types[] = 'real'; break;
				default:
					if (preg_match('/_ids$/',$obj->name)) { //dont have a better way at the moment!
						$types[] = 'mva'; break;
					}
					$types[] = 'other'; break; //we dont actully care about the exact type, other than knowing numeric
			}
		}
		foreach($multis as $name => $query)
			$names[] = $name;

		//print "INSERT INTO {$param['index']} VALUES (";
		print "REPLACE INTO {$param['index']} (".implode(",",$names).") VALUES\n";
		$line = "";
		while($row = mysqli_fetch_row($result)) {
			print "$line(";
			$sep = '';

			//todo, would be to transform the data to utf8! (as done in latin1plus_encoded index)
			foreach($row as $idx => $value) {
				if ($types[$idx] == 'mva') //mva's need special treatment if importing into index
					$value = "(".mysqli_real_escape_string($db->_connectionID,$value).")";
				elseif (is_null($value))
					$value = "''"; //doesnt support null!
				elseif ($types[$idx] != 'int' && $types[$idx] != 'real') { //Don't just use 'is_numeric', as inserting a number into string attribute, silently fails!
					$enc = mb_detect_encoding($value, 'UTF-8, ISO-8859-15, ASCII');
					if ($enc == 'ISO-8859-15') {//dont just blindly convert, as while MOST columns in database are latin1, not quite all!
						$value = latin1_to_utf8($value);
						fwrite(STDERR,"converted $name\n");
					}

					$value = "'".mysqli_real_escape_string($db->_connectionID,$value)."'";
				}
				print "$sep$value";
				$sep = ',';
			}
			foreach($multis as $name => $query) {
				$query = str_replace('$id',$row[0],$query);
				$ids = $db->getCol($query);
				$value = "(".implode(',',$ids).")";
				print "$sep$value";
			}
			print ")";
			$line = ",\n";
			$lastid = $row[0];
		}
		print ";\n";

	        if (mysqli_num_rows($result) < $limit) //can exit as got all rows!
        	        break;
	}

        fwrite(STDERR,date('H:i:s ')."ALL DONE\n");
	exit();
}

#########################################################



fwrite(STDERR,date('H:i:s ')."DONE!\n");

