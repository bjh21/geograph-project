<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */

/**
 * Replace nocache-tags by results of the corresponding non-cacheable
 * functions and return it
 *
 * @param string $compiled_tpl
 * @param string $cached_source
 * @return string
 */

function smarty_core_process_compiled_include($params, &$smarty)
{
    $_cache_including = $smarty->_cache_including;
    $smarty->_cache_including = true;

    $_return = $params['results'];

    foreach ($smarty->_cache_info['cache_serials'] as $_include_file_path=>$_cache_serial) {
        $smarty->_include($_include_file_path, true);
    }

    foreach ($smarty->_cache_serials as $_include_file_path=>$_cache_serial) {
        $_return = preg_replace_callback('!(\{nocache\:('.$_cache_serial.')#(\d+)\})!s',
                                         array(&$smarty, '_process_compiled_include_callback'),
                                         $_return);
    }
    
    
    ###############
    # catch any still left and hide them. but notify the developer of the problem
    $count = 0;
    $_return = preg_replace('!(\{nocache\:(\w+)#(\d+)\})!sU','',$_return,-1,$count);

    if ($count > 0 && function_exists('apc_store') && !apc_fetch('nocache_warning'.$_include_file_path)) {

	$cache_id = $GLOBALS['cacheid'];
	$compile_id = $smarty->compile_id;
	$tpl_file = $GLOBALS['template'];
	$folder = $GLOBALS['CONF']['template'];

        $_auto_id = $smarty->_get_auto_id($cache_id,$compile_id);
        $cache_file = substr($smarty->_get_auto_filename(".",$tpl_file,$_auto_id),2);

	ob_start();
	print "cache_id: $cache_id\n";
	print "compile_id: $compile_id\n";
	print "tpl_file: $tpl_file\n";
	print "cache_file: $cache_file\n";
	print "folder: $folder\n";
	print_r($params);
	print_r(get_included_files());
	$con = ob_get_clean();
	debug_message('[Geograph Error] nocache: '.$_SERVER['SCRIPT_NAME'],$con);

    	apc_store('nocache_warning'.$_include_file_path,1,500);
    }
    ###############
    
    
    $smarty->_cache_including = $_cache_including;
    return $_return;
}

?>
