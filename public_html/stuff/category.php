<?php
/**
 * $Project: GeoGraph $
 * $Id: conversion.php 5502 2009-05-13 14:18:23Z barry $
 * 
 * GeoGraph geographic photo archive project
 * This file copyright (C) 2005 BArry Hunter (geo@barryhunter.co.uk)
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

init_session();


$smarty = new GeographPage;

$template='stuff_category.tpl';
$cacheid='';

if (!empty($_GET['type']) && preg_match('/^(\w+)$/',$_GET['type'])) {
	
	$smarty->assign('type',$_GET['type']);
	
	$cacheid = $_GET['type'];
}

$types = array('dropdown' => 'Dropdown','autocomplete' => 'Auto Complete Text Box','canonical' => 'Canonical Dropdown','canonicalplus' => 'Canonical Dropdown + Optional Detail','canonicalmore' => 'Canonical Dropdown (full unmoderated list)');
$smarty->assign_by_ref('types',$types);

$smarty->display($template, $cacheid);
