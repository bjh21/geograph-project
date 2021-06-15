<?php
/**
 * $Project: GeoGraph $
 * $Id: process_events.php 5211 2009-01-24 20:44:18Z barry $
 * 
 * GeoGraph geographic photo archive project
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

require_once('geograph/global.inc.php');

set_time_limit(5000);

//we dont check IP of called, instead using token (with shared secret) for validation!


$token=new Token;
$token->magic = $CONF['company_magic'];

if (!empty($_GET['t']) && $token->parse($_GET['t']) && $token->hasValue('id') && $token->getValue('v') == $CONF['company_name']) {

	$user_id = $token->getValue('id');

	$db = NewADOConnection($GLOBALS['DSN']);

	if ($_GET['action'] == 'add') {
		$sql = "UPDATE user SET rights = CONCAT(rights,',member') WHERE user_id = ".intval($user_id);
	} else {
		$sql = "UPDATE user SET rights = REPLACE(rights,'member','') WHERE user_id = ".intval($user_id);
	}
	$db->Execute($sql);
	print $db->Affected_Rows();
	exit;
}

die("huh?");



