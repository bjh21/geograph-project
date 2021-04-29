<?php
/**
 * $Project: GeoGraph $
 * $Id: geotrip_submit.php 9094 2020-03-24 10:31:56Z barry $
 * 
 * GeoGraph geographic photo archive project
 * This file copyright (C) 2011 Rudi Winter (http://www.geograph.org.uk/profile/2520)
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
 

if ($_SERVER['SERVER_ADDR']=='127.0.0.1') {
	require_once('./geograph_snub.inc.php');
} else {
	require_once('geograph/global.inc.php');
}
init_session();

$smarty = new GeographPage;

//you must be logged in to request changes
$USER->mustHavePerm("basic");

include('./geotrip_func.php');


$db = GeographDatabaseConnection(false);





$smarty->assign('page_title', 'Geo-Trip creation :: Geo-Trips');


$smarty->display('_std_begin.tpl','trip_submit');
print '<link rel="stylesheet" type="text/css" href="/geotrips/geotrips.css" />';

print "<h2><a href=\"./\">Geo-Trips</a> :: Submission Form</h2>";


    if (!isset($_POST['submit2'])) {
?>
      <div class="panel maxi">
        <form name="trip" method="post" action="<?php echo $_SERVER['PHP_SELF'];?>" enctype="multipart/form-data">
          <hr style="color:#992233">
          <p>
            <b>Location</b> <span class="hlt">(required)</span><br />
            <input type="text" name="loc" size="72" maxlength="123"/><br />
            (e.g. <em>Cadair Idris</em>)
          </p>
          <ul>
            <li>A general geographical label to represent the <i>whole trip</i>. Maybe a summit, or town, or something wider area like a national park, but can also be a list of names</li>
          </ul>
          <hr style="color:#992233">
          <p>
            <b>Starting point</b> <span class="hlt">(required)</span><br />
            <input type="text" name="start" size="72" maxlength="123"/><br />
            (e.g. <em>Dolgellau</em>)
          </p>
          <hr style="color:#992233">
          <p>
            <b>Type of trip</b> <span class="hlt">(required)</span><br />
            <select name="type">
              <option value="" selected="selected">choose one</option>
              <option value="walk">walk</option>
              <option value="bike">cycle ride</option>
              <option value="road">road trip</option>
              <option value="rail">train journey</option>
              <option value="boat">boat trip</option>
              <option value="bus">scheduled public transport</option>
            </select>
          </p>
          <ul>
            <li>Choose whichever option matches closest the nature of your trip.</li>
          </ul>
          <hr style="color:#992233">
          <p>
            <b>Geograph search id</b> <span class="hlt">(required)</span><br />
            <input type="text" name="search" size="72" /><br />
            (e.g. <em>12345678</em> or <em><? echo $CONF['SELF_HOST']; ?>/search.php?i=12345678</em>)
          </p>
          <ul>
            <li>
The search defines which images will be shown on the map on your trip page.
            </li>
            <li>
The search id is the number at the end of the web address (URL) of the page that shows the search
results.  You can either copy and paste the whole address line or just the id number.
            </li>
            <li>
The <a href="/search.php?form=advanced">search</a> should include only
images <b>taken by yourself on the same day</b>, in date-submitted order (assuming you've submitted
in the order they were taken).
            </li>
            <li>
Here is a list of <a target="_blank" href="/stuff/latestdays.php">
your ten most recent days out</a> (opens in a new window),
with pre-defined searches.  Click one of the links in the list, and paste the URL of the
search results page that you get.
            </li>
            <li>
To show on the map, images must have at least a six-figure <em>camera</em> position, and either
a view direction must be set or subject and camera position must be different.
            </li>
            <li>
By agreement with the Hamsters' Trade Union (HTU), search results are limited to the first 250
images.
            </li>
            <li>
You can create the search before your images have been moderated, but they won't show on the map
until they go live on Geograph.
            </li>
          </ul>
          <hr style="color:#992233">
          <p>
            <b>Title</b> (optional)<br />
            <input type="text" name="title" size="72" maxlength="123"/><br />
            (e.g. <em>The Foxes' Path up Cadair Idris</em>)
          </p>
          <ul>
            <li>
By default the title will be <em>Location</em> from <em>starting point</em>.  Choose a different
title if you'd like something more inspired.
            </li>
          </ul> 
          <hr style="color:#992233">
          <p>
            <b>Upload a GPS track</b> in GPX format (optional)<br />
            <input type="file" name="gpxfile" size="40">
          </p>
          <ul>
            <li>
If you <b>have a GPS unit</b>, set it to record your track and download the track to your computer
in GPX format.
            </li>
            <li>
Please remember to clear your GPS receiver's memory before starting your
trip and stop recording data at the end - the track should only show the trip you're
describing, not your drive home as well...
            </li>
            <li>
Note that uploading a track will reveal your precise whereabouts during your trip - only
upload if you're comfortable with that.  However, all time stamps will be discarded.
            </li>
            <li>
If you can remove spurious data points, please do so.  Don't worry if you can't.<br />
Programs that may be useful for this are <i>e.g.</i> <a href="http:www.gpsu.co.uk">GPS Utility</a>
(for Microsoft Windows) or <a href="http://activityworkshop.net/software/prune/download.html">
GPS Prune</a> (for Linux).
            </li>
            <li>
If you <b>don't have a GPS unit</b>, you can still upload a track by creating a GPX file on
<a href="http://wtp2.appspot.com/wheresthepath.htm">Wheresthepath</a>.  Click the route
button bottom left, draw a route on the map or satellite image, then export the gpx to
file using the import/export button (bottom centre), delivering your very own GPX file
to your computer.
            </li>
          </ul>
          <hr style="color:#992233">
          <p>
            <b>Geograph image id</b> (optional)<br />
            <input type="text" name="img" size="72" /><br />
            (e.g. <em>1234567</em> or <em><? echo $CONF['SELF_HOST']; ?>/photo/1234567</em>)
          </p>
          <ul>
            <li>
Choose your favourite picture from the trip, which will be used in the index and on the
overview map.  If left blank, the first image from the search will be used.  All the other
images shown on the trip page will be determined from the search specified above.
            </li>
            <li>
The image id is the number at the end of the web address (URL) of the page that shows the full
image.  You can either copy and paste the whole address line or just the id number.
            </li>
          </ul>
          <hr style="color:#992233">
          <p>
            <b>Description</b> (optional)<br />
            <textarea rows="8" cols="80" name="descr"></textarea>
          </p>
          <ul>
            <li>
Please describe briefly what is special about this trip - things to see and learn, why
you particularly enjoyed it (or why not!), any difficulties encountered etc.
            </li>
            <li>
The descriptions of the individual images will be shown in the map pop-ups, so there is
no need to repeat them here.
            </li>
          </ul>
          <hr style="color:#992233">
          <p>
            <b>Continuation from previous trip</b> (optional)<br />
            <input type="text" name="contfrom" size="72" /><br />
            (e.g. <em>123</em> or <em><? echo $CONF['SELF_HOST']; ?>/geotrips/geotrip_show.php?trip=123</em> or <em><? echo $CONF['SELF_HOST']; ?>/geotrips/123</em>)
          </p>
          <ul>
            <li>
If this trip is a continuation of one you've uploaded earlier, specify its Geo-Trips id or full
URL here.  This will create links in both directions.
            </li>
          </ul>
          <div style="text-align:center;background-color:green">
            <input type="submit" name="submit2" value="Ok" style="margin:10px" />
          </div>
        </form>
      </div>
<?php
    } else {
      // sanity check
      if ($_POST['loc']&&$_POST['start']&&$_POST['type']&&$_POST['search']&&$_POST['search']!=$_POST['img']) {
        // fetch Geograph data

	require_once('geograph/searchcriteria.class.php');
	require_once('geograph/searchengine.class.php');

	$bits = explode(' ',trim(preg_replace('/[^\d]+/',' ',$_POST['search'])));
	if (count($bits) == 1) {
		$search = $bits[0];

	} elseif (!empty($bits)) {
                $engine = new SearchEngineBuilder('#');
                $engine->db = $db;
		$data = array();
		$data['description'] = "List via GeoTrips at ".strftime("%A, %e %B, %Y. %H:%M");
		$data['user_id'] = $USER->user_id;
                $search = $engine->buildMarkedList($bits, $data, false);
        }

        $engine = new SearchEngine();
        $engine->db = $db;
        $engine->SearchEngine($search);

	$geograph = array();

	$engine->criteria->resultsperpage = 250; // FIXME really?
	$recordSet = $engine->ReturnRecordset(0, true);
	while (!$recordSet->EOF) {
		$image = $recordSet->fields;
		if (    $image['nateastings']
		    &&  $image['viewpoint_eastings']
		    #&&  $image['realname'] == $USER->realname
		    &&  $image['user_id'] == $USER->user_id
		    &&  $image['viewpoint_grlen'] > 4
		    &&  $image['natgrlen'] > 4
		    && (   $image['view_direction'] != -1
		        || $image['viewpoint_eastings']  != $image['nateastings']
		        || $image['viewpoint_northings'] != $image['natnorthings'])
		) {
			$geograph[] = $image;
			if ($geograph[0]['imagetaken'] != $image['imagetaken']) { // taken on the same day
				$geograph = array();
				break;
			}
		}
		$recordSet->MoveNext();
	}
	$recordSet->Close();
        if (count($geograph)<3) {   // we need three different images for the thumbnails at the top
?>
          <div class="panel maxi">
            <h3 class="hlt">Upload failed</h3>
            <p>
It seems your search contained no suitable images.  Images need to:-
            </p>
            <ul>
              <li>be taken by yourself</li>
              <li>be taken during the same trip (<i>i.e.</i> on the same day)</li>
              <li>have a six-figure or better camera position</li>
              <li>have either a view direction, or subject and camera position must be different</li>
            </ul>
            <p>
and there need to be at least three images matching these criteria in your search.  Only the first 250 images per trip will be shown.
            </p>
          </div>
<?php
          die('No suitable images in search.');
        }
        // extract coordinates from GPX file
        $trk='';
        if (file_exists($_FILES['gpxfile']['tmp_name'])) {
          $trkpt = array();
          $gpxf=fopen($_FILES['gpxfile']['tmp_name'],'r');
          $xml_data=fread($gpxf,filesize($_FILES['gpxfile']['tmp_name']));
          fclose($gpxf);
          $xml_parser=xml_parser_create();
          xml_set_element_handler($xml_parser,'xml_startTag',null);
          xml_parse($xml_parser,$xml_data);
          xml_parser_free($xml_parser);
          foreach ($trkpt as $point) {
            if (isset($point['LAT'])) {
              $bng=wgs2bng($point['LAT'],$point['LON']);
              $ee[]=$bng[0];
              $nn[]=$bng[1];
              $trk=$trk.$bng[0].' '.$bng[1].' ';
            }
          }
        } else {
          $len=count($geograph);
          for ($i=0;$i<$len;$i++) {
            $ee[$i]=$geograph[$i]['viewpoint_eastings'];
            $nn[$i]=$geograph[$i]['viewpoint_northings'];
          }
        }
        $ee=array_filter($ee);  // remove zero eastings/northings (camera position missing)
        $nn=array_filter($nn);
        $bbox=min($ee).' '.min($nn).' '.max($ee).' '.max($nn);
        // database update
        $img = 0;
        if (isset($_POST['img'])) { // FIXME check if valid image and taken by this user (taken on that day etc.?)
          $img=explode('/',$_POST['img']);
          $img=intval($img[sizeof($img)-1]);
        }
        if (!$img) {
          $img=$geograph[0]['gridimage_id'];
        }
        if (!empty($_POST['contfrom']) && preg_match('/(\d+)\s*$/',$_POST['contfrom'],$m)) {
          $contfrom=intval($m[1]);
        } else $contfrom=0;
        
        $query='insert into geotrips values(null,';
        $query=$query.intval($USER->user_id).',';
        $query=$query.$db->Quote($USER->realname).",";
        $query=$query.$db->Quote($_POST['type']).",";
        $query=$query.$db->Quote($_POST['loc']).",";
        $query=$query.$db->Quote($_POST['start']).",";
        $query=$query.$db->Quote($_POST['title']).",";
        $query=$query.$db->Quote($geograph[0]['imagetaken']).",";
        $query=$query.$db->Quote($bbox).",";
        $query=$query.$db->Quote($trk).",";
        $query=$query.$search.",";
        $query=$query.$img.",";
        $query=$query.$db->Quote($_POST['descr']).",";
        $query=$query."UNIX_TIMESTAMP(NOW()),";
        $query=$query.$contfrom.',null,0,now(),0)';
        
        $db->Execute($query);
        $newid=$db->Insert_ID();
   
	if (empty($newid)) {
		print "There was an error creating your trip. Please contact us";

					ob_start();
					print "Message: ".$db->ErrorMsg()."\n\n";
					print "Query: $query\n\n";
                                        print_r($_POST);
                                        $con = ob_get_clean();
                                        debug_message('[Geograph Geotrip] Creation Error',$con);



	} else {
        // success
?>
        <div class="panel maxi">
          <h3>Thanks for adding your trip.</h3>
          <p>
If all has gone well, your <a href="/geotrips/<?php print($newid); ?>">new trip</a>
should show on the <a href="./">map</a> now.  Please
<a href="/usermsg.php?to=2520">let me know</a> if anything doesn't
work as expected.
          </p>
        </div>
        <div class="panel maxi">
          <h3>Add a blog post for your Geo-trip</h3>
          <p>
Two for the price of one!  If you'd like to add a post to the
<a href="/blog/">Geograph blog</a> to highlight your trip, please
press the submit button below.  This will take you to the blog edit page prefilled with the
information you've submitted to Geo-trips, so you can tweak the blog post before it goes live.
          </p>
<?php
          if ($_POST['title']) $title=$_POST['title'];
          else $title=$_POST['loc'].' from '.$_POST['start'];
          $gr=bbox2gr($bbox);
          $tags='Geo-trip, '.whichtype($_POST['type']);
          $pub=explode('-',date('d-m-Y-H-i-s'));
          $descr=$_POST['descr'].'  You can see this trip plotted on a map on the Geo-trips page '.$CONF['SELF_HOST'].'/geotrips/'.$newid.' .';
          $imgid=explode('/',$_POST['img']);
          $imgid=intval($imgid[sizeof($imgid)-1]);
?>
          <form name="blog" method="post" action="/blog/edit.php">
            <input type="hidden" name="id" value="new" />
            <input type="hidden" name="initial" value="true" />
            <input type="hidden" name="title" value="<?php print(htmlentities2($title)); ?>" />
            <input type="hidden" name="publishedDay" value="<?php print($pub[0]); ?>" />
            <input type="hidden" name="publishedMonth" value="<?php print($pub[1]); ?>" />
            <input type="hidden" name="publishedYear" value="<?php print($pub[2]); ?>" />
            <input type="hidden" name="publishedHour" value="<?php print($pub[3]); ?>" />
            <input type="hidden" name="publishedMinute" value="<?php print($pub[4]); ?>" />
            <input type="hidden" name="publishedSecond" value="<?php print($pub[5]); ?>" />
            <input type="hidden" name="grid_reference" value="<?php print($gr); ?>" />
            <input type="hidden" name="gridimage_id" value="<?php print($imgid); ?>" />
            <input type="hidden" name="content" value="<?php print(htmlentities2($descr)); ?>" />
            <input type="hidden" name="tags" value="<?php print($tags); ?>" />
            <div style="text-align:center;background-color:green">
              <input type="submit" name="submit" value="Submit to Geograph blog" style="margin:10px" />
            </div>
          </form>
        </div>
<?php
	}
      } else {
?>
        <div class="panel maxi">
          <p>
Location, starting point, type of trip and a Geograph search id are required to plot your
trip on the map.  Please fill in at least these fields.
          </p>
          <p>
Press Back button, or <a href="geotrip_submit.php">Try again.</a>
          </p>
        </div>
<?php
      }
    }


$smarty->display('_std_end.tpl');

