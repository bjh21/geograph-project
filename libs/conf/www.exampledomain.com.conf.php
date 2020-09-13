<?php

die("this site is only a demo test site");

setlocale(LC_ALL,'C'); //to match online servers...

//domain specific configuration file
$CONF=array();

###################################
# optimization setup

//see http://domain/admin/curtail.php - set to a positive number to enable
$CONF['curtail_level']=0;

###################################
# host setup

//servers ip BEGIN with (the server that fires cron jobs etc)
$CONF['server_ip'] = '127.0.0.';

//the protocol to use for resource urls. if you use SSL, change this https://
$CONF['PROTOCOL'] = "http://";
if (!empty($_SERVER['HTTPS']) || ( !empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https'))
	$CONF['PROTOCOL'] = "https://";

//set to X to enabling striping servering over a range of domains eg http://s[0,1,2,3].geograph.org.uk/photos/....
$CONF['enable_cluster'] = 2;
$CONF['STATIC_HOST'] = $CONF['PROTOCOL']."s0.geograph.mobile";

//hostname to use for thumbnails if cluster is disabled (used to be used for full images, but now use $CONF['STATIC_HOST'])
$CONF['CONTENT_HOST'] = $CONF['PROTOCOL'].$_SERVER['HTTP_HOST'];

//this *can* be different to your main hostname if want dedicated host for cookieless tile.php requests
$CONF['TILE_HOST'] = $CONF['PROTOCOL'].$_SERVER['HTTP_HOST'];

//this can be different to your main hostname if want to seperate out the hosting of the Google Earth Superlayer.
$CONF['KML_HOST'] = "http://".$_SERVER['HTTP_HOST'];

$CONF['SELF_HOST'] = $CONF['PROTOCOL'].$_SERVER['HTTP_HOST']; //general replacement for HTTP_HOST so can include the protocol

###################################
# database configuration

$CONF['db_driver']='mysql';
$CONF['db_connect']='localhost';
$CONF['db_user']='geograph';
$CONF['db_pwd']='banjo';
$CONF['db_db']='geograph';
$CONF['db_persist']=''; #'?persist';

//optional second database, used for sessions and gazetteer tables (need to contain a copy)
#$CONF['db_driver2']='mysql';
#$CONF['db_connect2']='second.server';
#$CONF['db_user2']='geograph';
#$CONF['db_pwd2']='banjo';
#$CONF['db_db2']='geograph';
#$CONF['db_persist2']=''; #'?persist';

//optional slave database (with `db_db` as the master)
#$CONF['db_read_driver']='mysql';
#$CONF['db_read_connect']='slave.server';
#$CONF['db_read_user']='geograph_read';
#$CONF['db_read_pwd']='banjo';
#$CONF['db_read_db']='geograph';
#$CONF['db_read_persist']=''; #'?persist';

//this is the database where temporally tables are created, normally left as main database, but in replication need a seperate database. 
//the geograph AND geograph_read user should have full access to this database. whereas the geograph_read only needs SELECT priv on `geograph` db. 
$CONF['db_tempdb']=$CONF['db_db'];

//only enable debugging on development domains - this pulls in the
//adodb-errorhandler.inc.php file which causes db errors to output using
//the php error handler
$CONF['adodb_debugging']=1;

//path to adodb cache dir
$CONF['adodb_cache_dir']=$_SERVER['DOCUMENT_ROOT'].'/../adodbcache/';

###################################
# optional memcache

//enable memcache use for the application - should function fine (but slower) without memcache. 
#$CONF['memcache'] = array(
#	'app' => array(
#		'host1' => '127.0.0.1',
#		'port1' => 11211,
#		#'host2' => 'localhost',
#		#'port2' => 11212,
#		'p' => 'l' ##if running multiple sites with one memcache instance, this should be different for each
#		),
#	);

//uncomment to enable adodb caching (adodb_cache_dir is ignored) 
#$CONF['memcache']['adodb'] =& $CONF['memcache']['app'];

//uncomment to enable putting smarty templates in memcache (NOTE: on a shared cluster the compiled/ directorys need to be shared between all) 
#$CONF['memcache']['smarty'] =& $CONF['memcache']['app'];

//not yet functional/fully tested
#$CONF['memcache']['sessions'] =& $CONF['memcache']['app'];

###################################
# optional sphinx setup

//sphinx is not required but highly recommended
#$CONF['sphinx_host'] = "localhost";
#$CONF['sphinx_port'] = 3312;
#$CONF['sphinx_portql'] = 9306;
#$CONF['sphinx_prefix'] = ""; //prefix for index names, if only one instance of geograph probably leave blank (will need to add to indexes in sphinx.conf manually)

# can also provide $CONF['sphinxql_dsn'] directly, but not recommended. (will be built from sphinx_host and sphinx_portql)

###################################
# Site setup

//choose UI template
$CONF['template']='basic';

//enable forums? (set to false to hide the forum on this domain)
$CONF['forums']=true;

###################################
# smarty setup

//turn compile check off on stable site for a small boost
$CONF['smarty_compile_check']=1;

//only enable debugging on development domains
$CONF['smarty_debugging']=1;

//disable caching for everyday development
$CONF['smarty_caching']=1;

###################################
# admin details

//email address to send site messages to
$CONF['contact_email']='someone@somewhere.com,other@elsewhere.com';

###################################
# folder setup

//path to temp folder for photo uploads - on cluster setups should be a shared folder.
$CONF['photo_upload_dir'] = '/tmp';

###################################
# secret tokens

//secret string used for registration confirmation hash
$CONF['register_confirmation_secret']='CHANGETHIS';

//secret string used for hashing photo filenames
$CONF['photo_hashing_secret']='CHANGETHISTOO';

//secret used for securing map tokens
$CONF['token_secret']='CHANGETHIS';

###################################
# imagemagick

//to enable the use of ImageMagick for resize operations, enter path 
//where mogrify etc can be found (highly recommended, faster than the PHP GD based routines)
//set to null or empty string to use php-based routines.
$CONF['imagemagick_path'] = '/usr/bin/';

//font used in map tile generation
$CONF['imagemagick_font'] = '/usr/share/fonts/truetype/freefont/FreeSans.ttf';

###################################

//you get minibb admin privilege by using a geograph admin login - these
//settings are no longer used, but you can initialise them "just in case"
$CONF['minibb_admin_user']='admin';
$CONF['minibb_admin_pwd']='CHANGETHIS';
$CONF['minibb_admin_email']='root@wherever';

###################################

//during high load can optionally disable thumbs display in the forum pages
$CONF['disable_discuss_thumbs'] = false;

//limits on numbers of thumbnails per page, and 'single item'
$CONF['global_thumb_limit'] = 300;
$CONF['post_thumb_limit'] = 200;

###################################
# mapping setup

//mapping services to use for the rather maps 
$CONF['raster_service']='';
//valid values (comma seperated list):
// 'vob' - VisionOfBritain Historical Maps - Permission MUST be sought from the visionofbritain.org.uk webmaster before enableing this feature!
// 'OS50k' - OSGB 50k Mapping - Licence Required (see next)
// 'Grid' - display a grid on Google Map 
// 'Google' - Use Google Mapping (api key required below)

$CONF['google_maps_api_key'] = 'XXXXXXX'; //old V2 Key, some scrips may use it!
$CONF['google_maps_api3_key'] = 'XXXXXXX'; //new v3 key - browser based
$CONF['google_maps_api3_server'] = 'XXXXXXX'; //new v3 key - for API use

$CONF['os_licence'] = 'XXXXXXXX';

//paths to where map data is stored (should be OUTSIDE of the web root)
$CONF['rastermap'] = array(
	'OS50k' => array(
			'path'=>'c:/home/geograph/rastermaps/OS-50k/',
			'epoch'=>'latest/'
			),
	'OS250k' => array(
			'path'=>'c:/home/geograph/rastermaps/OS-250k/',
			'epoch'=>'latest/'
			)	
);

//Username/Passowrd for the metacarta webservices api
//http://developers.metacarta.com/register/
#$CONF['metacarta_auth'] = 'user@domain.com:password';
$CONF['metacarta_auth'] = '';

//does the map draw the more demanding placenames
$CONF['enable_newmap'] = 1;

//use the smaller towns database for the 'near...' lines rather than placenames
$CONF['use_gazetteer'] = 'towns'; //OS250/OS/hist/towns/default
//NOTE: for GB, OS, OS250 and hist are (c)'ed datasets and are not available under the GPL licence

###################################
# country info

//the countries referenced in the reference index 
$CONF['references'] = array(1 => 'Great Britain',2 => 'Ireland');

//including the 'non filted version'
$CONF['references_all'] = array(0=>'British Isles')+$CONF['references'];

//false origins for the internal grid
$CONF['origins'] = array(1 => array(206,0),2 => array(10,149));

//match what intergrated mapping uses!
$CONF['intergrated_layers'] = array(0 => 'FTT000000000B000FT', 1 => 'FTFB000000000000FT', 2 => 'FFT000000000000BFT');
$CONF['intergrated_zoom'] = array(0 => 13, 1 => 6, 2 => 13);
$CONF['intergrated_zoom_centi'] = array(0 => 15, 1 => 8, 2 => 15);

###################################
# search setup

//the radius for simple searches in km, set high to begin with but set low once number of submissions
$CONF['default_search_distance'] = 10;

//for ri 2 we might want a different number
$CONF['default_search_distance_2'] = 30;

//radius to count number of single image squares
$CONF['search_prompt_radius'] = 4;

//if you have capacity problems true to false, to skip checking count on page 1 of results. 
$CONF['search_count_first_page'] = true; //true/false

###################################

//to use the flickr search will need to obtain a flicker api key
//    http://flickr.com/services/api/misc.api_keys.html
$CONF['flickr_api_key'] = '';

//to use the picnik service for upload will need to obtain a api key
//   http://www.picnik.com/keys/request
$CONF['picnik_api_key'] = '';

//method to use for picnik, see 
//http://www.picnik.com/info/api
$CONF['picnik_method'] = 'inabox'; //'inabox'|'redirect'

###################################

//domain from which pictures can be pulled on demand
//only for use on development systems to allow 'real' pictures to be
//copied to your local system on demand. Simply give the domain name
//of the target system.
//COMMENT THIS LINE OUT ON LIVE SYSTEMS!
#$CONF['fetch_on_demand'] = 'www.geograph.org.uk';

###################################

//script timing logging options (comment out when not required)
//to log to separate file (in docroot/../logs)
#$CONF['log_script_timing'] = 'file';		
//log to apache logfile (use %{php_timing}n in the LogFormat)
#$CONF['log_script_timing'] = 'apache';	

#$CONF['log_script_folder'] = '/var/logs/geograph';	


###################################

//Optionally, send a backup to a amazon S3 bucket. 

$CONF['awsAccessKey'] = '';
$CONF['awsSecretKey'] = '';
$CONF['awsS3Bucket'] = 'photos.exampledomain.com'; //By using a real domain, could enable serving images from the bucket, if the need arrose



$CONF['carrot2_dcs_url'] = "http://localhost:8081/dcs/rest"; //the specific rest API endpoint

$CONF['timetravel_url'] = "http://localhost:1208"; //just the hostname (and optional port) without trailing slash. We add the full API path

$CONF['carrot2_dcs_url'] = "http://localhost:8081/dcs/rest"; //the specific rest API endpoint

$CONF['timetravel_url'] = "http://localhost:1208"; //just the hostname (and optional port) without trailing slash. We add the full API path
