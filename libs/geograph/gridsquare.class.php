<?php
/**
 * $Project: GeoGraph $
 * $Id: gridsquare.class.php 8842 2018-09-17 17:54:55Z barry $
 * 
 * GeoGraph geographic photo archive project
 * http://geograph.sourceforge.net/
 *
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

/**
* Provides the GridSquare class
*
* @package Geograph
* @author Paul Dixon <paul@elphin.com>
* @version $Revision: 8842 $
*/

/**
* GridSquare class
* Provides an abstraction of a grid square, providing all the
* obvious functions you'd expect
*/
class GridSquare
{
	/**
	* internal database handle
	*/
	var $db=null;
	
	/**
	* gridsquare_id primary key
	*/
	var $gridsquare_id=0;

	/**
	* 4figure text grid reference for this square
	*/
	var $grid_reference='';

	/**
	* which grid does this location refer to
	*/
	var $reference_index=0;

	/**
	* internal grid position
	*/
	var $x=0;
	var $y=0;

	/**
	* how much land? (0-100%)
	*/
	var $percent_land=0;
	
	/**
	* how many images in this square
	*/
	var $imagecount=0;
	
	/**
	* exploded gridsquare element of $this->grid_reference
	*/
	var $gridsquare="";
	
	/**
	* exploded eastings element of $this->grid_reference
	*/
	var $eastings=0;
	
	/**
	* exploded northings element of $this->grid_reference
	*/
	var $northings=0;
	
	/**
	* national easting/northing (ie not internal)
	*/
	var $nateastings;
	var $natnorthings;
	var $natgrlen = 0;
	var $natspecified = false;
	
	/**
	* GridSquare instance of nearest square to this one with an image
	*/
	var $nearest=null;
	
	
	/**
	* nearest member will have this set to show distance of nearest square from this one
	*/
	var $distance=0;
	
	
	
	/**
	* Constructor
	*/
	function GridSquare()
	{
	}
	
	/**
	 * get stored db object, creating if necessary
	 * @access private
	 */
	function &_getDB($allow_readonly = false)
	{
		//check we have a db object or if we need to 'upgrade' it
		if (empty($this->db) || !is_object($this->db) || ($this->db->readonly && !$allow_readonly) ) {
			$this->db=GeographDatabaseConnection($allow_readonly);
		}
		if (!$this->db) die('Database connection failed');
		return $this->db;
	}

	/**
	 * set stored db object
	 * @access private
	 */
	function _setDB(&$db)
	{
		$this->db=$db;
	}
	
	/**
	* store error message
	*/
	function _error($msg)
	{
		$this->errormsg=$msg;
	}
	
	function assignDiscussionToSmarty(&$smarty) 
	{
		global $memcache;
		
		$mkey = $this->gridsquare_id;
		//fails quickly if not using memcached!
		$result = $memcache->name_get('gsd',$mkey);
		if ($result) {
			$smarty->assign_by_ref('discuss', $result['topics']);
			$smarty->assign('totalcomments', $result['totalcomments']);
			return;
		}
	
		$db=&$this->_getDB(true);

split_timer('gridsquare'); //starts the timer
		
		$sql='select t.topic_id,posts_count-1 as comments,CONCAT(\'Discussion on \',t.topic_title) as topic_title '.
			'from gridsquare_topic as gt '.
			'inner join geobb_topics as t using (topic_id)'.
			'where '.
			"gt.gridsquare_id = {$this->gridsquare_id} ".
			'order by t.topic_time desc';
		
		$topics=$db->GetAll($sql);
		if ($topics)
		{
			$news=array();

			$totalcomments = 0;
			foreach($topics as $idx=>$topic)
			{
				$firstpost=$db->GetRow("select post_text,poster_name,post_time,poster_id from geobb_posts where topic_id={$topic['topic_id']} order by post_time limit 1");
				$topics[$idx]['post_text']=GeographLinks(str_replace('<br>', '<br/>', $firstpost['post_text']));
				$topics[$idx]['realname']=$firstpost['poster_name'];
				$topics[$idx]['user_id']=$firstpost['poster_id'];
				$topics[$idx]['topic_time']=$firstpost['post_time'];
				$totalcomments += $topics[$idx]['comments'] + 1;
			}
			$smarty->assign_by_ref('discuss', $topics);
			$smarty->assign('totalcomments', $totalcomments);
			
			$result = array();
			$result['topics'] = $topics;
			$result['totalcomments'] = $totalcomments;
			
			//fails quickly if not using memcached!
			$memcache->name_set('gsd',$mkey,$result,$memcache->compress,$memcache->period_short);
		}

split_timer('gridsquare','assignDiscussionToSmarty',$mkey); //logs the wall time

	}
	
	
	/**
	* Conveience function to get six figure GridRef
	*/
	function get6FigGridRef()
	{
		return sprintf("%s%03d%03d", $this->gridsquare, $this->eastings*10 + 5, $this->northings*10 + 5);
	}

	/**
	* Conveience function to get national easting (not internal)
	*/
	function getNatEastings()
	{
		global $CONF,$memcache;
		
		if (!isset($this->nateastings)) {
			//fails quickly if not using memcached!
			$mkey = $this->gridsquare;
			$square = $memcache->name_get('pr',$mkey);
			if (!$square) {
				$db=&$this->_getDB(true);

				$square = $db->GetRow('select origin_x,origin_y from gridprefix where prefix='.$db->Quote($this->gridsquare).' limit 1');
				
				//fails quickly if not using memcached!
				$memcache->name_set('pr',$mkey,$square,$memcache->compress,$memcache->period_short);
			}
			
			//get the first gridprefix with the required reference_index
			//after ordering by x,y - you'll get the bottom
			//left gridprefix, and hence the origin
			
			$square['origin_x'] -= $CONF['origins'][$this->reference_index][0];
			$square['origin_y'] -= $CONF['origins'][$this->reference_index][1];
			
			$this->nateastings = sprintf("%05d",intval($square['origin_x']/100)*100000+ ($this->eastings * 1000 + 500));
			$this->natnorthings = sprintf("%05d",intval($square['origin_y']/100)*100000+ ($this->northings * 1000 +500));
			$this->natgrlen = 4;
		} 
		return $this->nateastings;
	}
	
	/**
	* Conveience function to get national northing (not internal)
	*/
	function getNatNorthings()
	{
		if (!isset($this->natnorthings)) {
			$this->getNatEastings();
		} 
		return $this->natnorthings;
	}
	
	/**
	* Get an array of valid grid prefixes
	*/
	function getGridPrefixes($ri = 0)
	{
		$andwhere = ($ri)?" and reference_index = $ri ":'';
		$db=&$this->_getDB(true);
		return $db->CacheGetAssoc(3600*24*7,"select prefix as name,prefix from gridprefix ".
			"where landcount>0 $andwhere".
			"order by reference_index,prefix");

	}
	
	/**
	* Get an array of valid kilometer indexes
	*/
	function getKMList()
	{
		$kmlist=array();
		for ($k=0; $k<100;$k++)
		{
			$kmlist[$k]=sprintf("%02d", $k);
		}
		return $kmlist;
	}
	
	/**
	* Store grid reference in session
	*/
	function rememberInSession()
	{
		if (strlen($this->grid_reference))
		{
			$_SESSION['gridref']=$this->grid_reference;
			$_SESSION['gridsquare']=$this->gridsquare;
			$_SESSION['eastings']=$this->eastings;
			$_SESSION['northings']= $this->northings;
			
		}
	}
	
	/**
	*
	*/
	function setByFullGridRef($gridreference,$setnatfor4fig = false,$allowzeropercent = false)
	{
		$matches=array();
		$isfour=false;
		
		if (preg_match("/\b([a-zA-Z]{1,2}) ?(\d{5})[ \.]?(\d{5})\b/",$gridreference,$matches)) {
			list ($prefix,$e,$n) = array($matches[1],$matches[2],$matches[3]);
			$this->natspecified = 1;
			$natgrlen = $this->natgrlen = 10;
		} else if (preg_match("/\b([a-zA-Z]{1,2}) ?(\d{4})[ \.]?(\d{4})\b/",$gridreference,$matches)) {
			list ($prefix,$e,$n) = array($matches[1],"$matches[2]0","$matches[3]0");
			$this->natspecified = 1;
			$natgrlen = $this->natgrlen = 8;
		} else if (preg_match("/\b([a-zA-Z]{1,2}) ?(\d{3})[ \.]*(\d{3})\b/",$gridreference,$matches)) {
			list ($prefix,$e,$n) = array($matches[1],"$matches[2]00","$matches[3]00");
			$this->natspecified = 1;
			$natgrlen = $this->natgrlen = 6;
		} else if (preg_match("/\b([a-zA-Z]{1,2}) ?(\d{2})[ \.]?(\d{2})\b/",$gridreference,$matches)) {
			list ($prefix,$e,$n) = array($matches[1],"$matches[2]000","$matches[3]000");
			$isfour = true;
			$natgrlen = $this->natgrlen = 4;
		} else if (preg_match("/\b([a-zA-Z]{1,2}) ?(\d{1})[ \.]*(\d{1})\b/",$gridreference,$matches)) {
			list ($prefix,$e,$n) = array($matches[1],"$matches[2]5000","$matches[3]5000");
			$natgrlen = $this->natgrlen = 2;
		} else if (preg_match("/\b([a-zA-Z]{1,2})\b/",$gridreference,$matches)) {
			list ($prefix,$e,$n) = array($matches[1],"50000","50000");
			$natgrlen = $this->natgrlen = 0;
		} 		
		if (!empty($prefix))
		{
			$gridref=sprintf("%s%02d%02d", strtoupper($prefix), intval($e/1000), intval($n/1000));
			$ok=$this->_setGridRef($gridref,$allowzeropercent);
			if ($ok && (!$isfour || $setnatfor4fig))
			{
				//we could be reassigning the square!
				unset($this->nateastings);
				
				//use this function to work out the major easting/northing (of the center of the square) then convert to our exact values
				$eastings=$this->getNatEastings();
				$northings=$this->getNatNorthings();
				
				$emajor = floor($eastings / 100000); //floor rounds down, rather tha using intval which routd to zero
				$nmajor = floor($northings / 100000);
	
				//$this->nateastings = $emajor.sprintf("%05d",$e);
				$this->nateastings = ($emajor*100000)+$e; //cope with negative! (for Rockall...)
				$this->natnorthings = $nmajor.sprintf("%05d",$n);
				$this->natgrlen = $natgrlen;
				$this->precision=pow(10,6-($natgrlen/2))/10;
			} else {
				$this->precision=1000;
			}
		} else {
			$ok=false;
			$this->_error(htmlentities($gridreference).' is not a valid grid reference');

		}
				
		return $ok;
	}
	
	/**
	* Stores the grid reference along with handy exploded elements 
	*/
	function _storeGridRef($gridref)
	{
		$this->grid_reference=$gridref;
		if (preg_match('/^([A-Z]{1,2})(\d\d)(\d\d)$/',$this->grid_reference, $matches))
		{
			$this->gridsquare=$matches[1];
			$this->eastings=$matches[2];
			$this->northings=$matches[3];
		}
		
	}
	
	
	/**
	* Just checks that a grid position is syntactically valid
	* No attempt is made to see if its a real grid position, just to ensure
	* that the input isn't anything nasty from the client side
	*/
	function validGridPos($gridsquare, $eastings, $northings)
	{
		$ok=true;
		$ok=$ok && preg_match('/^[A-Z]{1,2}$/',$gridsquare);
		$ok=$ok && preg_match('/^[0-9]{1,2}$/',$eastings);
		$ok=$ok && preg_match('/^[0-9]{1,2}$/',$northings);
		return $ok;
	}

	/**
	* set up and validate grid square selection using seperate reference components
	*/
	function setGridPos($gridsquare, $eastings, $northings,$allowzeropercent = false)
	{
		//assume the inputs are tainted..
		$ok=$this->validGridPos($gridsquare, $eastings, $northings);
		if ($ok)
		{
			$gridref=sprintf("%s%02d%02d", $gridsquare, $eastings, $northings);
			$ok=$this->_setGridRef($gridref,$allowzeropercent);
		}
		
		return $ok;
	}

	/**
	* Just checks that a grid position is syntactically valid
	* No attempt is made to see if its a real grid position, just to ensure
	* that the input isn't anything nasty from the client side
	*/
	function validGridRef($gridref, $figures=4)
	{
		return preg_match('/^[A-Z]{1,2}[0-9]{'.$figures.'}$/',$gridref);
	}


	/**
	* set up and validate grid square selection using grid reference
	*/
	function setGridRef($gridref)
	{
		$gridref = preg_replace('/[^\w]+/','',strtoupper($gridref)); #assume the worse and remove everything, also not everyone uses the shift key
		//assume the inputs are tainted..
		$ok=$this->validGridRef($gridref);
		if ($ok)
		{
			$ok=$this->_setGridRef($gridref);
		}
		else
		{
			//six figures?
			$matches=array();
			if (preg_match('/^([A-Z]{1,2})(\d\d)\d(\d\d)\d$/',$gridref,$matches))
			{
				$fixed=$matches[1].$matches[2].$matches[3];
				$this->_error('Please enter a 4 figure reference, i.e. '.$fixed.' instead of '.$gridref);
			}
			else
			{
				$this->_error(htmlentities($gridref).' is not a valid grid reference');
			}
		}
		
		return $ok;
	}
	
	/**
	* load square from database
	*/
	function loadFromId($gridsquare_id)
	{
		global $CONF;
		$db=&$this->_getDB(true);
		if ($CONF['template']=='archive') {
			//todo, if this works, could update this daily?
			$square = $db->GetRow('select * from gridsquare_copy where gridsquare_id='.intval($gridsquare_id).' limit 1');
		} else {
			$square = $db->GetRow('select * from gridsquare where gridsquare_id='.intval($gridsquare_id).' limit 1');
		}
		if (count($square))
		{
			//store cols as members
			foreach($square as $name=>$value)
			{
				if (!is_numeric($name))
					$this->$name=$value;
			}

			//ensure we get exploded reference members too
			$this->_storeGridRef($this->grid_reference);

			return true;
		}
		return false;
	}

	function loadMostRecentSubmission($user_id) {
		$db=&$this->_getDB(true);

		$gridref = $db->getOne($sql = "select grid_reference from gridimage inner join gridsquare using (gridsquare_id) where user_id = {$user_id} order by gridimage_id desc"); //limit 1 added automatically!

		if ($gridref) {
			 $this->_storeGridRef($gridref);
		}
		return $gridref;
	}

	/**
	* load square from internal coordinates
	*/
	function loadFromPosition($internalx, $internaly, $findnearest = false)
	{
		$ok=false;
		$db=&$this->_getDB(true);
		$square = $db->GetRow("select * from gridsquare where CONTAINS( GeomFromText('POINT($internalx $internaly)'),point_xy ) order by percent_land desc limit 1");
		if (count($square))
		{		
			$ok=true;
			
			//store cols as members
			foreach($square as $name=>$value)
			{
				if (!is_numeric($name))
					$this->$name=$value;
			}
			
			//ensure we get exploded reference members too
			$this->_storeGridRef($this->grid_reference);
			
			//square is good, how many pictures?
			if ($findnearest && $this->imagecount==0)
			{
				//find nearest square for 100km
				$this->findNearby($square['x'], $square['y'], 100);
			}
		} else {
			$this->_error("This location seems to be all at sea! Please contact us if you think this is in error");
		}
		return $ok;
	}

	/**
	* set up and validate grid square selection
	*/
	function _setGridRef($gridref,$allowzeropercent = false)
	{
		$ok=true;

		$db=&$this->_getDB(true);
		
		//store the reference 
		$this->_storeGridRef($gridref);
	
split_timer('gridsquare'); //starts the timer

		//check the square exists in database
		$count=0;
		$square = $db->GetRow('select * from gridsquare where grid_reference='.$db->Quote($gridref).' limit 1');	
		if (count($square))
		{		
			//store cols as members
			foreach($square as $name=>$value)
			{
				if (!is_numeric($name))
					$this->$name=$value;
						
			}
			
			if ($this->percent_land==0 && (!$allowzeropercent || $this->imagecount==0) )
			{
				$this->_error("$gridref seems to be all at sea! Please <a href=\"/mapfixer.php?gridref=$gridref\">contact us</a> if you think this is in error.");
				$ok=false;

			}
			
			//square is good, how many pictures?
			if ($this->imagecount==0)
			{
				//find nearest square for 100km
				$this->findNearby($square['x'], $square['y'], 100);
			}

		}
		else
		{
			$ok=false;
			
			//we don't have a square for given gridref, so first we
			//must figure out what the internal coords are for it
			
			$sql="select * from gridprefix where prefix='{$this->gridsquare}' limit 1";
			$prefix=$db->GetRow($sql);
			if (count($prefix))
			{
				$x=$prefix['origin_x'] + $this->eastings;
				$y=$prefix['origin_y'] + $this->northings;
			
				//what's the closes square with land? more than 5km away? disallow
				$ok=$this->findNearby($x,$y, 2, false);
			
				//check on the correct grid!;
				if ($ok && $this->nearest->reference_index != $prefix['reference_index'])
				{
					$ok = false;
				}
				
				unset($this->nearest);
				
				if ($ok)
				{
					$db=&$this->_getDB();
					
					//square is close to land, so we're letting it slide, but we
					//need to create the square - we give it a land_percent of -1
					//to indicate it needs review, and also to prevent it being
					//used in further findNearby calls
					$sql="insert into gridsquare(x,y,percent_land,grid_reference,reference_index,point_xy) 
						values($x,$y,-1,'$gridref',{$prefix['reference_index']},GeomFromText('POINT($x $y)') )";
					$db->Execute($sql);
					$gridsquare_id=$db->Insert_ID();
					
					//ensure we initialise ourselves properly
					$this->loadFromId($gridsquare_id);
				} else {
					//as we calculated it might as well return it in case useful...
					$this->x = $x;
					$this->y = $y;
				}
			
				//we know there are no images, so lets find some nearby squares...
				$this->findNearby($x, $y, 100);
			}
			
			
			if (!$ok)
				$this->_error("$gridref seems to be all at sea! Please contact us if you think this is in error");

		}

split_timer('gridsquare','_setGridRef'.(isset($gridsquare_id)?'-create':''),$gridref); //logs the wall time
		
		return $ok;
	}
	
	/**
	* find a nearby occupied square and store it in $this->nearby
	* returns true if an occupied square was found
	* if occupied is false, finds the nearest land square
	*/
	function findNearby($x, $y, $radius, $occupied=true)
	{
		global $memcache;
		
		//fails quickly if not using memcached!
		$mkey = "$x,$y,$radius,$occupied";
		$nearest = $memcache->name_get('gn',$mkey);
		if ($nearest) {
			$this->nearest = $nearest;
			return true;
		}
		
		$db=&$this->_getDB(true);

split_timer('gridsquare'); //starts the timer

		//to optimise the query, we scan a square centred on the
		//the required point
		$left=$x-$radius;
		$right=$x+$radius;
		$top=$y-$radius;
		$bottom=$y+$radius;

		if ($occupied)
			$ofilter=" and imagecount>0 ";
		else
			$ofilter=" and percent_land>0 ";
		
		$rectangle = "'POLYGON(($left $bottom,$right $bottom,$right $top,$left $top,$left $bottom))'";

		$sql="select *,
			power(x-$x,2)+power(y-$y,2) as distance
			from gridsquare where
			CONTAINS( 	
				GeomFromText($rectangle),
				point_xy)
			$ofilter
			order by distance asc limit 1";
		
		$square = $db->GetRow($sql);

		if (count($square) && ($distance = sqrt($square['distance'])) && ($distance <= $radius))
		{
			//round off distance
			$square['distance']=round($distance);
			
			//create new grid square and store members
			$this->nearest=new GridSquare;
			foreach($square as $name=>$value)
			{
				if (!is_numeric($name))
					$this->nearest->$name=$value;
			}

split_timer('gridsquare','findNearby',"$x,$y"); //logs the wall time
			
			//fails quickly if not using memcached!
			$memcache->name_set('gn',$mkey,$this->nearest,$memcache->compress,$memcache->period_med);
			
			return true;
		}
		else
		{
		
split_timer('gridsquare','findNearby-failed',"$x,$y"); //logs the wall time

			return false;
		}
	}
	
	function findNearestPlace($radius,$gazetteer = '') {
		#require_once('geograph/gazetteer.class.php');
		
		if (!isset($this->nateastings))
			$this->getNatEastings();
			
		$gaz = new Gazetteer();
		
		return $gaz->findBySquare($this,$radius,null,$gazetteer);	
	}
	

	function loadCollections() {
		
		global $CONF;
		
		$db=&$this->_getDB(30); 

split_timer('gridsquare'); //starts the timer

		//find articles
		$this->collections = $db->CacheGetAll(3600*6,"
			SELECT c.url,c.title,'Collection' AS `type`
			FROM content c
			WHERE c.gridsquare_id = {$this->gridsquare_id}
			AND source IN ('blog','article','gallery')
			ORDER BY content_id DESC");

		$this->collections = array_merge($this->collections,$db->CacheGetAll(3600*6,"
			SELECT CONCAT('/snippet/',snippet_id) AS url,if(s.title!='',s.title,'untitled') as title,'Shared Description' AS `type`
			FROM snippet s
			WHERE s.grid_reference = '{$this->grid_reference}'
			AND enabled = 1
			ORDER BY snippet_id DESC"));


		//todo -- other options
		# search grid_reference in content.text (using sphinx)
		# list content using an image in square (using gridimage_post and gridimage_content)
		# search grid_reference in image.text (using sphinx) - duplicating 'mentioning' ;)
		# gridimage_group_stat - automatic clusters


		if ($CONF['sphinx_host']) {
			# list snippet using an image in square (using gridimage_snippet or can use sphinx)
			# find nearby shared descriptions (using sphinx - combined with above)
			
			$sphinx = new sphinxwrapper();
			$sphinx->pageSize = $pgsize = 12;
			$pg = 1;

			$sphinx->prepareQuery($this->grid_reference);
			$sphinx->processQuery(); //if the query starts with a GR it expands it to search nearby squares

			$before = $sphinx->q;
			$sphinx->q = str_replace('@grid_reference ','@(grid_reference,image_square) ',$sphinx->q);

			$ids = $sphinx->returnIds($pg,'snippet');

			if (!empty($ids) && count($ids) > 0) {
				
				$id_list = implode(',',$ids);
				
				$this->collections = array_merge($this->collections,$db->CacheGetAll(3600*6,"
					SELECT CONCAT('/snippet/',snippet_id) AS url,if(s.title!='',s.title,'untitled') as title,'Related Description' AS `type`
					FROM snippet s
					WHERE s.snippet_id IN($id_list)
					AND s.grid_reference != '{$this->grid_reference}'
					AND enabled = 1
					ORDER BY FIELD(s.snippet_id,$id_list)"));
			}

			$sphinx->q = "$before @source -themed -snippet"; //use $before, so it doesnt include image_square (doesnt exist on content_stemmed)
			$ids = $sphinx->returnIds($pg,'content_stemmed');

			if (!empty($ids) && count($ids) > 0) {

				$id_list = implode(',',$ids);

				$this->collections = array_merge($this->collections,$db->CacheGetAll(3600*6,"
					SELECT c.url,c.title,'Collection' AS `type`
					FROM content c
					WHERE c.content_id IN($id_list)
					ORDER BY FIELD(c.content_id,$id_list)"));
			}

		}

		if ($this->collections_count = count($this->collections)) {
			foreach ( $this->collections as $collection) {
				if ($collection['title'] == $this->grid_reference) {
					$this->collection = $collection;
					break;
				}
			}
		}

split_timer('gridsquare','loadCollections'.$this->collections_count,"{$this->grid_reference}"); //logs the wall time

	}
	
	function &getImages($inc_all_user = false,$custom_where_sql = '',$order_and_limit = 'order by moderation_status+0 desc,seq_no')
	{
		global $memcache;
		
		//fails quickly if not using memcached!
		$mkey = md5("{$this->gridsquare_id}:$inc_all_user,$custom_where_sql,$order_and_limit");
		$images = $memcache->name_get('gi',$mkey);
		if ($images) {
			return $images;
		}
		
		$db=&$this->_getDB(true);

split_timer('gridsquare'); //starts the timer

		$images=array();
		if ($inc_all_user && ctype_digit($inc_all_user)) {
			$inc_all_user = "=$inc_all_user";
		}
		$gridimage_join = '';
                if (strpos($custom_where_sql,' label = ') !== FALSE) {
                        $gridimage_join .= " INNER JOIN gridimage_group gg USING (gridimage_id)";
                }

		$i=0;
		$recordSet = $db->Execute("select gi.*,gi.realname as credit_realname,if(gi.realname!='',gi.realname,user.realname) as realname ".
			"from gridimage gi ".
			"inner join user using(user_id) $gridimage_join ".
			"where gridsquare_id={$this->gridsquare_id} $custom_where_sql ".
			"and (moderation_status in ('accepted', 'geograph') ".
			($inc_all_user?"or user.user_id $inc_all_user":'').") ".
			$order_and_limit);
		while (!$recordSet->EOF) 
		{
			$images[$i]=new GridImage;
			$images[$i]->fastInit($recordSet->fields);
			if (!empty($images[$i]->imagetaken) && $images[$i]->imagetaken > '1000-00-00') {
				$images[$i]->year = substr($images[$i]->imagetaken,0,4);
			}
			$recordSet->MoveNext();
			$i++;
		}
		$recordSet->Close(); 

split_timer('gridsquare','getImages'.$i,"$inc_all_user,$custom_where_sql"); //logs the wall time

		
		//fails quickly if not using memcached!
		$memcache->name_set('gi',$mkey,$images,$memcache->compress,$memcache->period_short);
		
		return $images;
	}
	
	function &getImageCount($inc_all_user = false,$custom_where_sql = '')
	{
		$db=&$this->_getDB(true);
		
		$count = $db->getOne("select count(*) 
			from gridimage gi 
			where gridsquare_id={$this->gridsquare_id} $custom_where_sql 
			and (moderation_status in ('accepted', 'geograph') ".
			($inc_all_user?"or gi.user_id = $inc_all_user":'').") ");
		
		return $count;
	}
	
	/**
	* Updates the imagecount and has_geographs columns for a square - use this after making changes
	*/
	function updateCounts()
	{
		global $ADODB_FETCH_MODE;
		$db=&$this->_getDB();

		$prev_fetch_mode = $ADODB_FETCH_MODE;
		$ADODB_FETCH_MODE = ADODB_FETCH_ASSOC;

split_timer('gridsquare'); //starts the timer

		$updates = $db->getRow("
			SELECT
			  COUNT(*) AS `imagecount`,
			  IF(SUM(imagetaken > DATE(DATE_SUB(NOW(), INTERVAL 5 YEAR)) AND moderation_status='geograph')>0,1,0) AS has_recent,
			  COALESCE(MAX(ftf),0) AS max_ftf,
			  COALESCE(SUM(moderation_status = 'geograph' and imagetaken LIKE '1%'),0) AS premill,
			  GROUP_CONCAT(IF(ftf<=1,gridimage_id,NULL) ORDER BY ftf desc, seq_no LIMIT 1) AS first
			FROM gridimage
			WHERE gridsquare_id={$this->gridsquare_id} and moderation_status in ('accepted','geograph')");
		//  IF(SUM(moderation_status='geograph')>0,1,0) AS has_geographs,

		//see if we have any geographs (we had the has_geograph column first, added max_ftf for more detail later,
			//but didnt want to change the behaviour of existing column.
			//eg currenty have "sum(has_geographs) as geographs", but could convert to "sum(max_ftf>0) as geographs"
		$updates['has_geographs']=$updates['max_ftf']?1:0;

		$db->Execute('UPDATE gridsquare SET `'.implode('` = ?,`',array_keys($updates))."` = ? WHERE gridsquare_id={$this->gridsquare_id}",
			array_values($updates));

		$ADODB_FETCH_MODE = $prev_fetch_mode;

split_timer('gridsquare','updateCounts',"{$this->grid_reference},{$updates['imagecount']}"); //logs the wall time
	}
}


