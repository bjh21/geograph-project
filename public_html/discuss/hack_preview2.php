<?php
if(isset($_POST['prevForm']) and trim($_POST['postText'])!=''){
require($pathToFiles.'bb_func_txt.php');

$logged_admin=($user_id==1?1:0);
$disbbcode=(isset($_POST['disbbcode']) and $_POST['disbbcode']==1?1:0);
$topicTitle2=stripslashes(textFilter($_POST['topicTitle'],$topic_max_length,$post_word_maxlength,0,1,0,0));
$postText2=stripslashes(textFilter($_POST['postText'],$post_text_maxlength,$post_word_maxlength,1,$disbbcode,1,$logged_admin));

if (empty($CONF['disable_discuss_thumbs']) && preg_match_all('/\[\[(\[?)([a-z]+:)?(\w{0,3} ?\d+ ?\d*)(\]?)\]\]/',$postText2,$g_matches)) {
	$thumb_count = 0;

	require_once('geograph/gridimage.class.php');
	require_once('geograph/gridsquare.class.php');
	$g_image=new GridImage;

	$ids = array();
	foreach ($g_matches[3] as $g_i => $g_id) {
		if (is_numeric($g_id)) {
			$ids[] = $g_id;
		}
	}
	if (count($ids) > 0) {
		$db = $g_image->_getDB(true);
		$prev_fetch_mode = $ADODB_FETCH_MODE;
		$ADODB_FETCH_MODE = ADODB_FETCH_ASSOC;
		$data = $db->CacheGetAssoc(3600,"SELECT gridimage_id,moderation_status,title,grid_reference,user_id,realname,credit_realname FROM gridimage_search WHERE gridimage_id IN (".implode(',',$ids).") LIMIT {$CONF['post_thumb_limit']}");
		$ADODB_FETCH_MODE = $prev_fetch_mode;
	}

	foreach ($g_matches[3] as $g_i => $g_id) {
		$server = $_SERVER['HTTP_HOST'];
		$prefix = $g_matches[2][$g_i];

		if (is_numeric($g_id)) {
			if ($global_thumb_count > $CONF['global_thumb_limit'] || $thumb_count > $CONF['post_thumb_limit']) {
				$postText2 = preg_replace("/\[?\[\[$prefix$g_id\]\]\]?/","[[<a href=\"http://{$server}/photo/$g_id\">$prefix$g_id</a>]]",$postText2);
			} else {
				if ($prefix) {
					$ok = $g_image->loadFromServer($prefix, $g_id);
					if ($ok)
						$server = $g_image->ext_server;
				} elseif (isset($data[$g_id])) {
					$data[$g_id]['gridimage_id'] = $g_id;
					$g_image->fastInit($data[$g_id]);
					$ok = 1;
				} else {
					$ok = $g_image->loadFromId($g_id);
				}
				if ($ok && $g_image->moderation_status == 'rejected' && (!isset($userRanks[$cc]) || $userRanks[$cc] == 'Member'))
					$ok = false;
				if ($ok) {
					if ($g_matches[1][$g_i]) {
						$g_img = $g_image->getThumbnail(120,120,false,true);
						#$g_img = preg_replace('/alt="(.*?)"/','alt="'.$g_image->grid_reference.' : \1 by '.$g_image->realname.'"',$g_img);
						$g_title=$g_image->grid_reference.' : '.htmlentities($g_image->title).' by '.$g_image->realname;
						$postText2 = str_replace("[[[$prefix$g_id]]]","<a href=\"http://{$server}/photo/$g_id\" target=\"_blank\" title=\"$g_title\">$g_img</a>",$postText2);
					} else {
						$postText2 = preg_replace("/(?<!\[)\[\[$prefix$g_id\]\]/","{<a href=\"http://{$server}/photo/$g_id\" target=\"_blank\">{$g_image->grid_reference} : {$g_image->title}</a>}",$postText2);
					}
				}
				$global_thumb_count++;
			}
			$thumb_count++;
		} else {
			$postText2 = str_replace("[[$prefix$g_id]]","<a href=\"http://{$server}/gridref/".str_replace(' ','+',$g_id)."\" target=\"_blank\">$g_id</a>",$postText2);
		}
	}
}

if (empty($CONF['disable_discuss_thumbs'])) {
	$postText2 = preg_replace('/\[image id=(\d+)\]/e',"smarty_function_gridimage(array(id => '\$1',extra => '{description}'))",$postText2,5);
	$postText2 = preg_replace('/\[image id=(\d+) text=([^\]]+)\]/e',"smarty_function_gridimage(array(id => '\$1',extra => '\$2'))",$postText2,5);
}

##$postText2 = preg_replace('/\[([\w :-]+)\]([^>]*)(<(?!\/a>)|$)/e',"replace_tags('$1').'$2$3'",$postText2);



echo ParseTpl(makeUp('hack_preview2'));
exit;
}
elseif(isset($_POST['prevForm']) and trim($_POST['postText'])==''){
echo '';
exit;
}

?>
