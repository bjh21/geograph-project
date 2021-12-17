<?php
/**
 * $Project: GeoGraph $
 * $Id: security.inc.php 8848 2018-09-18 17:13:40Z barry $
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
* Provides routines useful in preventing security issues
*
* Many of these routines are for checking data before it is placed in
* the database or transmitted back to a user, in order to prevent
* cross site scripting and similar attacks
*
* @package Geograph
* @author Paul Dixon <paul@elphin.com>
* @version $Revision: 8848 $
*/


function rate_limiting($slug, $per_minute = 5) {
	global $USER, $memcache;

	if (empty($memcache) || empty($memcache->redis)) //for now only do if have redis, although we could possibly do this with memcache directly
		return;

	if (!empty($USER) && $USER->user_id)
		$per_minute *= 2;

	//todo, this should probably use IP! and/or session/user_id
	$mkey = 'rate:'.$memcache->prefix.md5($slug.'.'.$_SERVER['HTTP_USER_AGENT']).':'.date('i');

	$counter = $memcache->redis->incr($mkey);
	$memcache->redis->expire($mkey, 59); //always need to expire!

	if ($counter > $per_minute) {
		//todo
		//header(439 too many requests)
		//if (user_id|session) show_capacha?

		//for now just log it!
		global $db;

		if (empty($db) || !empty($db->readonly))
			$db = GeographDatabaseConnection(false);

		$ins = "INSERT INTO rate_limiting_log SET
		mkey = ".$db->Quote($mkey).",
	        slug = ".$db->Quote($slug).",
	        counter = ".intval($counter).",
	        php_self = ".$db->Quote($_SERVER['REQUEST_URI']).",
		request_time = ".intval($_SERVER['REQUEST_TIME']).",
	        ipaddr = INET6_ATON('".getRemoteIP()."'),
	        referer = ".$db->Quote($_SERVER['HTTP_REFERER']).",
	        useragent = ".$db->Quote($_SERVER['HTTP_USER_AGENT']).",
	        session = ".$db->Quote(session_id())."

		ON DUPLICATE KEY UPDATE counter = ".intval($counter); //so we only keep one log line per minute!
ini_set('display_errors',1);
		$db->Execute($ins);
	}
}




function inEmptyRequestInt($key,$def = 0) {
	return (!empty($_REQUEST[$key]))?intval($_REQUEST[$key]):$def;
}

function inSetRequestInt($key,$def = 0) {
	return (isset($_REQUEST[$key]))?intval($_REQUEST[$key]):$def;
}


/**
* basic email address check
*/
function isValidEmailAddress($email) 
{
	return preg_match('/^([a-zA-Z0-9])+([a-zA-Z0-9\._\-\+])*@([a-zA-Z0-9_-])+([a-zA-Z0-9\._-]+)+$/' , $email)?true:false; 
}

/**
* basic name check
*/
function isValidRealName($name) 
{
	return preg_match('/^[a-zA-Z0-9\-\s\']+$/' , $name)?true:false; 
}

/**
* web url check
*/
function isValidURL($url) 
{
	return preg_match('{^http(s?)\:\/\/[a-zA-Z0-9\-\._]+(\.[a-zA-Z0-9\-\._]+){1,}(\/?)([a-zA-Z0-9\~\-\.\?\,=\'\/\\\+&%\$#_]*)?$}' , $url)?true:false; 
}

/**
 * Heuristic spam check intended for email message checking
 *
 */
function isSpam($msg)
{
	//some spam features url and entity encoding to hide
	//the real content from filters. Bugger off!
	$msg=html_entity_decode(urldecode($msg));
	$msg=strtolower($msg);

	//no legitimate use for html or bbedit tags
	if (strstr($msg, '[url')!==false)
		return true;
	if (strstr($msg, 'href=')!==false)
		return true;

	//how many times does http appear?
	$matches=array();
	preg_match_all("{http}", $msg, $matches);
	$count=count($matches[0]);

	preg_match_all("{https?://www.geograph.org.uk}", $msg, $matches);
	$legit=count($matches[0]);

	//we'll let you off for using geograph links...
	$count-=$legit;

	if ($count>3)
		return true;

	return false;
}

/**
 * Return IP address of user
 * 
 */
function getRemoteIP()
{
	//get IP address of user
	if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
	{
		$ips=explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
		$ip=trim($ips[0]);
	}
	else
	{
		$ip=$_SERVER['REMOTE_ADDR'];
	}
//    [HTTP_X_FORWARDED_FOR] => 2a05:d018:fcc:be01:35e0:4306:928a:97ca

	if (!preg_match('/^[a-f\d]+([\.:][a-f\d]*)+$/i',$ip))
	 //we often use getRemoteIP to insert directly into database. because from HTTP_X_FORWARDED_FOR there is a chance is spoofed, and vulnerable to SQL injection (although should be ok if REALLY behind cache, as OUR proxy will set it safely.
		return 0;
	return $ip;
}


/**
 * 
 * 
 */
function isLocalIPAddress()
{
	global $CONF;
	
	if ($_SERVER['REMOTE_ADDR'] == $_SERVER['SERVER_ADDR'])
	{
		//is from self
		return true;
	} 
	elseif (!empty($CONF['server_ip']) && strpos($_SERVER['REMOTE_ADDR'],$CONF['server_ip']) === 0 && strpos(getRemoteIP(),$CONF['server_ip']) === 0) 
	{
		//its our server calling direct/our gateway is forwarding for our server
		return true;
	} 
	return false;
}

function appearsToBePerson() {
	global $CONF;
	if (empty($_SERVER['HTTP_USER_AGENT']))
		return false;
	if ( (stripos($_SERVER['HTTP_USER_AGENT'], 'http')===FALSE) &&
	    (stripos($_SERVER['HTTP_USER_AGENT'], 'bot')===FALSE) &&
	    (strpos($_SERVER['HTTP_USER_AGENT'], 'Preview')===FALSE) &&
            (stripos($_SERVER['HTTP_USER_AGENT'], 'Magnus')===FALSE) &&
            (strpos($_SERVER['HTTP_USER_AGENT'], 'curl')===FALSE) &&
            (strpos($_SERVER['HTTP_USER_AGENT'], 'The Knowledge AI')===FALSE) &&
	    empty($_SERVER['HTTP_X_PURPOSE']) && empty($_SERVER['HTTP_PURPOSE']) && empty($_SERVER['HTTP_X_MOZ']) &&  //'prefetch' and 'preview' requests
	    $CONF['template']!='archive')
		return true;

	return false;
}


