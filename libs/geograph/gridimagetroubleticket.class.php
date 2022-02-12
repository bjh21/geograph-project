<?php
/**
 * $Project: GeoGraph $
 * $Id: gridimagetroubleticket.class.php 8706 2018-02-03 18:28:52Z barry $
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
* Provides the GridImageTroubleTicket class
*
* @package Geograph
* @author Paul Dixon <paul@elphin.com>
* @version $Revision: 8706 $
*/

/**

moderated changes system

the principle here is the a picture owner can make immediate changes
a moderator can make immediate changes, and anyone else can make
moderated changes. In addition, a moderator can "approve" moderated changes

owner: moderated: gridref (and picture in future) unmoderated: everything else
moderator: unmoderated: everything
other user: moderated: everything

Every unmoderated edits can be tracked with this system as a changelog feature


create table gridimage_ticket
(
	gridimage_ticket_id int not null auto_increment,

	gridimage_id int,
	suggested datetime,
	user_id int,
	moderator_id int,

	updated datetime,
	status enum('pending', 'open', 'closed'),
	type enum('normal', 'minor') default 'normal',

	notes text,

	primary key(gridimage_ticket_id),
	index(gridimage_id)
);

create table gridimage_ticket_item
(
	gridimage_ticket_item_id int not null auto_increment,

	gridimage_ticket_id int not null,

	approver_id int,

	field varchar(64),
	oldvalue text,
	newvalue text,

	status enum('pending', 'immediate', 'approved', 'rejected'),

	primary key(gridimage_ticket_item_id),
	index(gridimage_ticket_id)
);

create table gridimage_ticket_comment
(
	gridimage_ticket_comment_id int not null auto_increment,
	gridimage_ticket_id int not null,
	user_id int,
	comment text,
	added  datetime,
	primary key(gridimage_ticket_comment_id),
	index(gridimage_ticket_id)
);


*/

class GridImageTroubleTicket
{
	/**
	* internal db handle
	*/
	var $db;

	/**
	* ticket id
	*/
	var $gridimage_ticket_id;

	/**
	* associated image
	*/
	var $gridimage_id;

	/**
	* type
	*/
	var $type;

	/**
	* notify
	*/
	var $notify;

	/**
	* suggested time
	*/
	var $suggested;

	/**
	* suggesting user
	*/
	var $user_id;

	/**
	* handling moderator
	*/
	var $moderator_id;

	/**
	* ticket update time
	*/
	var $updated;


	/**
	* status
	*/
	var $status;

	/**
	* is this ticket public
	*/
	var $public;

	/**
	* moderator notes
	*/
	var $notes;

	/**
	* commit count - number of changes yet to be commit
	*/
	var $commit_count;

	/**
	* array of gridimage_ticket_item records yet to be written
	*/
	var $changes=array();

	/**
	* array of comment records, including realname
	*/
	var $comments=array();

	/**
	* Constructor, call load a tick if given an ID, otherwise prepares a new ticket
	* (without committng anything to the database)
	* @access public
	*/
	function GridImageTroubleTicket($id=null)
	{
		if (!is_null($id))
		{
			$this->loadFromId($id);
		}
		else
		{
			$this->_newTicket();
		}
	}

	/**
	* Clears the instance in preparation for a new ticket
	* @access private
	*/
	function _newTicket()
	{
	 	$this->_clear();
	 	$this->gridimage_ticket_id=0;
	 	$this->gridimage_id=0;
	 	$this->suggested=strftime("%Y-%m-%d %H:%M:%S", time());
	 	$this->user_id=0;
	 	$this->updated=$this->suggested;
	 	$this->status="pending";
	 	$this->notes="";
	 	$this->type='normal';
	 	$this->notify='suggestor';
	}

	/**
	* set user id of suggesting user
	* @access public
	*/
	function setSuggester($user_id,$realname = '')
	{
		$this->user_id=intval($user_id);
		if (!empty($realname)) {
			$this->suggester_name = $realname;
		}
	}

	/**
	* set user id of moderating user
	* @access public
	*/
	function setModerator($user_id)
	{
		$this->moderator_id=intval($user_id);
	}

	/**
	* set image
	* @access public
	*/
	function setImage($gridimage_id)
	{
		$this->gridimage_id=intval($gridimage_id);
	}

	/**
	* set ticket type
	* @access public
	*/
	function setType($type)
	{
		$this->type=$type;
	}

	/**
	* set notify setting
	* @access public
	*/
	function setNotify($notify)
	{
		$this->notify=$notify;
	}

	/**
	* set public setting
	* @access public
	*/
	function setPublic($public)
	{
		$this->public=$public;
	}

	/**
	* set moderator notes
	* @access public
	*/
	function setNotes($notes)
	{
		$this->notes=$notes;
	}

	/**
	* set moderator notes
	* @access public
	*/
	function setDefer($when = 'NOW()') {
		$db=&$this->_getDB();
		$sql="update gridimage_ticket set updated='{$this->updated}', deferred=$when where gridimage_ticket_id={$this->gridimage_ticket_id}";
		$db->Execute($sql);
	}

	/**
	* set back to a pending ticket
	* @access public
	*/

	function removeModerator() {
		$db=&$this->_getDB();
		//update ticket status
		$sql="update gridimage_ticket set moderator_id=0 where gridimage_ticket_id={$this->gridimage_ticket_id}";
		$db->Execute($sql);
		$this->moderator_id = 0;
	}

	/**
	* Updates a given field of the image, holding it for moderation if necessary
	* A series of calls to this function should be followed up with a call
	* to commit(), which persist the ticket and any unmoderated changes
	* @access public
	*/
	function updateField($fieldname, $oldvalue, $newvalue, $moderated)
	{
		$ok=true;

		//no change?
		if ($oldvalue==$newvalue)
			return $ok;

		if (!$moderated)
		{
			//make the changes right away...
			$img=$this->_getImage();

			if ($fieldname=="grid_reference")
			{
				$err="";
				$ok=$img->reassignGridsquare($newvalue, $err);
				if ($ok)
				{
					$this->commit_count++;
				}
				else
				{
					die("Sorry, wasn't expecting reassignGridsquare to fail ($err) please contact us for assistance");
				}
			}
			elseif ($fieldname=="photographer_gridref")
			{
				//need to parse value for nat coords
				$sq=new GridSquare;
				if ($sq->setByFullGridRef($newvalue,true))
				{
					$img->viewpoint_eastings=$sq->nateastings;
					$img->viewpoint_northings=$sq->natnorthings;
					$img->viewpoint_grlen=$sq->natgrlen;
					$this->commit_count++;
				} elseif(empty($newvalue)) {
					// we are setting to 'blank'
					$img->viewpoint_eastings = 0;
					$img->viewpoint_northings = 0;
					$img->viewpoint_grlen=0;
					$this->commit_count++;
				}
			}
			else
			{
				$img->$fieldname=$newvalue;

				//we'll do this commit later
				$this->commit_count++;
			}

			$status="immediate";
			$approver_id=$this->user_id;
		}
		else
		{
			$status="pending";
			$approver_id=0;
		}

		//have we already got a change record?
		$found=false;
		foreach($this->changes as $c)
		{
			if ($c['field']==$fieldname)
				$found=true;
		}

		if (!$found)
		{
			//create a change record
			$change=array(
				"field"=>$fieldname,
				"oldvalue"=>$oldvalue,
				"newvalue"=>$newvalue,
				"status"=>$status,
				"approver_id"=>$approver_id
			);

			$this->changes[]=$change;
		}
	}

	/**
	* Updates the ticket and commits any necessary changes
	* @access public
	*/
	function commit($ticket_status=null)
	{
		$db=&$this->_getDB();

		//commit changes to image
		if ($this->commit_count)
		{
			$img=$this->_getImage();
			$img->commitChanges();
			$this->commit_count=0;
		}

		//write a trouble ticket
		$this->updated=strftime("%Y-%m-%d %H:%M:%S", time());

		$newticket=$this->gridimage_ticket_id==0;
		if ($newticket)
		{
			//new ticket
			$sql=sprintf("insert into gridimage_ticket(gridimage_id, suggested, updated,user_id, moderator_id, status, type, public, notes) ".
				"values(%d, '%s', '%s', %d, %d, '%s', '%s', '%s', %s)",
				$this->gridimage_id,
				$this->suggested,
				$this->updated,
				$this->user_id,
				$this->moderator_id ?? 0,
				"pending",
				$this->type,
				$this->public,
				$db->Quote($this->notes));
			$db->Execute($sql);
			$this->gridimage_ticket_id=$db->Insert_ID();
		}
		else
		{
			//update ticket
			$sql="update gridimage_ticket set updated='{$this->updated}', moderator_id='{$this->moderator_id}' where gridimage_ticket_id={$this->gridimage_ticket_id}";
			$db->Execute($sql);
		}

		//write the change records
		$statuscount=array("pending"=>0, "immediate"=>0);
		if (count($this->changes))
			foreach($this->changes as $change)
			{
				if (!empty($change['gridimage_ticket_item_id']))
				{
					//we're updating
					$sql=sprintf("update gridimage_ticket_item set status='%s', approver_id='%d' where gridimage_ticket_item_id=%d",
						$change["status"],
						$change["approver_id"],
						$change['gridimage_ticket_item_id']);


				}
				else
				{
					$sql=sprintf("insert into gridimage_ticket_item(gridimage_ticket_id, field, oldvalue, newvalue, status, approver_id) ".
						"values(%d, '%s', %s, %s, '%s', '%d')",
						$this->gridimage_ticket_id,
						$change["field"],
						$db->Quote($change["oldvalue"]),
						$db->Quote($change["newvalue"]),
						$change["status"],
						$change["approver_id"]);
				}

				$db->Execute($sql);

				$statuscount[$change["status"]]++;
			}

		//should we close the ticket?
		if (is_null($ticket_status))
		{
			$ticket_status="pending";
			if (($statuscount["pending"]==0) && ($statuscount["immediate"]>0))
			{
				//there are no pending changes, and some immediate ones were made, which
				//most likely means the comment can be ignored and we can close the ticket...
				$ticket_status="closed";
			}
		}

		//update ticket status
		$sql="update gridimage_ticket set status='$ticket_status' where gridimage_ticket_id={$this->gridimage_ticket_id}";
		$db->Execute($sql);
		$this->status=$ticket_status;

		//if ticket is open and a new ticket, we should alert moderators
		if ($newticket)
		{
			$img=$this->_getImage();

			$this->loadItems();
			$changes = '';
			foreach($this->changes as $idx=>$item)
			{
				if ($item['oldvalue']!=$item['newvalue'])
				{
					if ($item['newvalue'] != $item['oldvalue'] && !is_numeric($item['newvalue']) && !is_numeric($item['oldvalue'])) {
						require_once "3rdparty/simplediff.inc.php";

						list($item['oldhtml'], $item['newhtml']) = htmlCharDiff($item['oldvalue'], $item['newvalue']); //automatically calls htmlentities2
					} else {
						$item['oldhtml'] = htmlentities2($item['oldvalue']);
						$item['newhtml'] = htmlentities2($item['newvalue']);
					}

					if ($item['field'] == 'comment') {
						$changes.=" {$item['field']} changed from <br>".
							str_repeat('~',70)."<br>{$item['oldhtml']}<br>".str_repeat('~',70)."<br>".
							" {$item['field']} changed to ".
							str_repeat('~',70)."<br>{$item['newhtml']}<br>".str_repeat('~',70)."<br>";
					} else {
						$changes.=" {$item['field']} changed from \"{$item['oldhtml']}\" to \"{$item['newhtml']}\"<br>";
					}
				}
			}

			if ($this->status=="pending" || $this->status=="open")
			{
				$ttype = ($this->type == 'minor')?' Minor':'';

				if ($this->status=="pending")  //A open ticker can only be created by moderators so no need to notify
				{
					//email alert to moderators
					$msg =& $this->_buildEmail("A new$ttype change request has been submitted.<br><br>".htmlentities2($this->notes));
					$this->_sendModeratorMail($msg);
				}

				//if suggester isn't the owner of the image, alert the owner too
				if ($this->user_id != $img->user_id)
				{
					if ($this->public == 'no') {
						$comment = "A visitor to the site";
					} else {
						$comment = htmlentities2($this->suggester_name);
					}

					$comment .= " has suggested$ttype changes to this photo submission. ".
						"The changes will be reviewed by site moderators, who may need to contact you ".
						"if further information is required. If you wish, you can review and comment on these ".
						"changes by following the links in this message. ";
					if (!empty($changes))
					{
						$comment.="<br><br><br>The following changes have been suggested:<br><br>";
						$comment.=$changes;
					}
					if (!empty($this->notes)) {
						$comment.="<br><br>Comment: ".htmlentities2($this->notes);
					}

					$owner=new GeographUser($img->user_id);
					$msg =& $this->_buildEmail($comment, $owner->realname, true);
					if ( ($owner->ticket_option == 'all') ||
							( ($this->type=='normal') && $owner->ticket_option == 'major')
						)
						$this->_sendMail($owner->email, $msg); //notifing contributor of new ticket
				}
			}
			elseif ($this->status=="closed")
			{
				//a new ticket has been closed - if the ticket wasn't from the owner,
				//then we should alert them to changes that have been made
				if ($this->user_id != $img->user_id)
				{
					if ($this->type == 'minor') {
						$comment="A site moderator has made minor modifications to this photo submission. ".
							"You can review these changes by following the links in this message. ";
					} else {
						$comment="A site moderator has modified this photo submission. ".
							"You can review these changes by following the links in this message. ";
					}
					if (!empty($changes))
					{
						$comment.="<br><br><br>The following changes have been made:<br><br>";
						$comment.=$changes;
					}
					if (strlen($this->notes))
						$comment.="<br><br>Moderator Comment: ".htmlentities2($this->notes);

					$owner=new GeographUser($img->user_id);
					if ( ($owner->ticket_option == 'all') ||
							( ($this->type=='normal') && $owner->ticket_option == 'major')
						) {
						$msg =& $this->_buildEmail($comment, $owner->realname, true);
						$this->_sendMail($owner->email, $msg); //notifying contributor of automatically applied change
					}
				}
			}
		}

		//return ticket status
		return $this->status;
	}

	/**
	* update updated timestamp
	* @access private
	*/
	function _touch()
	{
		//we're updating
		$db=&$this->_getDB();
		$db->Execute("update gridimage_ticket set updated=now() where gridimage_ticket_id={$this->gridimage_ticket_id}");
	}

	/**
	* Add a comment to the ticket
	* @access private
	*/
	function _addComment($user_id, $comment)
	{
		if (!$this->isValid())
			die("_addComment - bad ticket");

		$db=&$this->_getDB();

		//add comment to db
		$sql=sprintf("insert into gridimage_ticket_comment(gridimage_ticket_id, user_id, comment, added) ".
			"values(%d, %d, %s, now())",
			$this->gridimage_ticket_id,
			$user_id,
			$db->Quote($comment));
		$db->Execute($sql);

		$this->_touch();
	}

	/**
	* returns an array containing email body and subject
	* Note $comment should be already HTML encoded!
	* @access private
	*/
	function & _buildEmail($comment, $realname = '', $for_owner = false)
	{
		$msg=array();
		global $CONF;

		$image= $this->_getImage();

		$ttype = ($this->type == 'minor')?' Minor':'';
		$msg['subject']="[Geograph]$ttype Suggestion for {$image->grid_reference} {$image->title} [#{$this->gridimage_ticket_id}]";

		$msg['html']="To respond to this message, please visit<br>";
                $msg['html'].="<a href=\"{$CONF['SELF_HOST']}/editimage.php?id={$this->gridimage_id}\">{$CONF['SELF_HOST']}/editimage.php?id={$this->gridimage_id}</a><br>"; //show full text, so strip_tag still works!
       	        $msg['html'].="Please, do NOT reply by email<br>";
		$msg['html'].="---------------------------------------<br><br>";

		if (!empty($realname)) {
			$msg['html'].="Dear ".htmlentities2($realname).",<br>";
			$msg['html'].="	This is a message about the following photo:<br>";
			$msg['html'].="	{$image->grid_reference} ".htmlentities2($image->title)."<br>";
		} else {
			$msg['html'].="Re: {$image->grid_reference} ".htmlentities2($image->title)."<br>";
		}

		$msg['html'].="---------------------------------------<br><br>";
		$msg['html'].=$comment."<br>"; //we assume its ALREADY html safe!
		$msg['html'].="---------------------------------------<br><br>";

		$msg['html'].="To respond to this message, please visit<br>";
                $msg['html'].="<a href=\"{$CONF['SELF_HOST']}/editimage.php?id={$this->gridimage_id}\">{$CONF['SELF_HOST']}/editimage.php?id={$this->gridimage_id}</a><br>";
		$msg['html'].="Please, do NOT reply by email";

		if ($for_owner) {
			$msg['html'].="<br><br>Note, can change preferences for notification emails, see option on profile:<br>";
			$msg['html'].="<a href=\"{$CONF['SELF_HOST']}/profile.php?edit=1#prefs\">{$CONF['SELF_HOST']}/profile.php?edit=1#prefs</a><br>";
		}

		//create the plain text version directly from html
		$msg['body'] = strip_tags(str_replace('<br>',"\n", $msg['html']));

		return $msg;
	}

	/**
	* Companion to _buildEmail - sends a message with appropriate headers + return address
	* @access private
	*/
	function _sendMail($to, &$msg)
	{
		if (empty($to)) {
			return;
		}
		mail_wrapper($to, $msg['subject'], $msg['body'],
				"From: Geograph - Reply Using Link <noreply@geograph.org.uk>",
				'', false, @$msg['html']);
	}

	/**
	* Sends a message to handling moderator, or all moderators if not yet handled
	* @access private
	*/
	function _sendModeratorMail(&$msg)
	{
		global $CONF;

		//if moderator_id is set, just send there, otherwise
		//we send to all users with moderator status
		$mods=array();
		if (!empty($this->moderator_id))
		{
			$mod=new GeographUser($this->moderator_id);
			$mods[]=$mod->email;
		}
		else
		{
			if (isset($CONF['tickets_notify_all'])) {

				//no moderator has handled this ticket yet, so lets forward to message to all of them
				$db=&$this->_getDB(true);
				$mods=$db->GetCol("select email from user where FIND_IN_SET('ticketmod',rights)>0;");
			} else {
				//lets not send the email!
				return;
			}
		}

		$this->_sendMail(implode(',',$mods), $msg); //sending message to all mods
	}

	/**
	 * add a moderator comment to existing ticket
	 * moderator comment is added to ticket and emailed to photo owner
	 * @access public
	 */
	function addModeratorComment($user_id, $comment, $claim = true)
	{
		$db=&$this->_getDB();
		if (!$this->isValid())
			die("addModeratorComment - bad ticket");

		$comment=trim($comment);
		if (strlen($comment)==0)
			return;

		//add database comment
		$this->_addComment($user_id, $comment);

		if ($claim || empty($this->moderator_id)) {
			//associate this moderator with the ticket
			$this->moderator_id=$user_id;
			$sets = ", moderator_id={$user_id}";
		} else {
			$sets = '';
		}
		$db->Execute("update gridimage_ticket set status='open', notify = '{$this->notify}' $sets where gridimage_ticket_id={$this->gridimage_ticket_id}");

		$comment = nl2br(htmlentities2($comment)); //we now going to use in email, which is now HTML

		$moderator=new GeographUser($user_id);
		$comment.="<br><br>".htmlentities2($moderator->realname)."<br>Geograph Moderator<br>";

		//email comment to owner
		$image= $this->_getImage();
		$owner=new GeographUser($image->user_id);

		if ($owner->ticket_option != 'off') { // the message IS send in case of 'none'!
			$msg =& $this->_buildEmail($comment, $owner->realname, true);
			$this->_sendMail($owner->email, $msg); //sending contributor notification of moderator comment
		}

		if ($this->notify == 'suggestor' && $image->user_id != $this->user_id) {
			//email comment to suggestor
			$suggestor=new GeographUser($this->user_id);

			$msg =& $this->_buildEmail($comment,$suggestor->realname);
			$this->_sendMail($suggestor->email, $msg); //sending suggestor notification of moderator comment
		}
	}

	/**
	 * add a owner comment to existing ticket
	 * owner comment is added to ticket and emailed to photo moderators
	 * @access public
	 */
	function addOwnerComment($user_id, $comment)
	{
		$db=&$this->_getDB();
		if (!$this->isValid())
			die("addOwnerComment - bad ticket");

		$comment=trim($comment);
		if (strlen($comment)==0)
			return;

		$this->_addComment($user_id, $comment);

		$comment = nl2br(htmlentities2($comment)); //we now going to use in email, which is now HTML

		$owner=new GeographUser($user_id);
		$comment.="<br><br>".htmlentities2($owner->realname)."<br>Photo owner<br>";

		//email comment to moderators
		$msg =& $this->_buildEmail($comment);
		$this->_sendModeratorMail($msg);

		$db->Execute("update gridimage_ticket set notify = '{$this->notify}' where gridimage_ticket_id={$this->gridimage_ticket_id}");

		$image= $this->_getImage();

		if ($this->notify == 'suggestor' && $image->user_id != $this->user_id && $this->user_id != $this->moderator_id) {
			//email comment to suggestor
			$suggestor=new GeographUser($this->user_id);

			$msg =& $this->_buildEmail($comment,$suggestor->realname);
			$this->_sendMail($suggestor->email, $msg); //sending suggestor a contributor comment
		}
	}

	/**
	 * add a suggestor comment to existing ticket
	 * owner comment is added to ticket and emailed to photo moderators
	 * @access public
	 */
	function addSuggesterComment($user_id, $comment)
	{
		if (!$this->isValid())
			die("addSuggestorComment - bad ticket");

		$comment=trim($comment);
		if (strlen($comment)==0)
			return;

		$this->_addComment($user_id, $comment);

		$comment = nl2br(htmlentities2($comment)); //we now going to use in email, which is now HTML

		if ($this->public != 'no') {
			$suggestor=new GeographUser($user_id);
			$comment.="<br><br>".htmlentities2($suggestor->realname)."<br>Suggestor<br>";
		}

		//email comment to moderators
		$msg =& $this->_buildEmail($comment);
		$this->_sendModeratorMail($msg);

		$image= $this->_getImage();

		$owner=new GeographUser($image->user_id);

		if ( ($owner->ticket_option == 'all') ||
			( ($this->type=='normal') && $owner->ticket_option == 'major')
			) {
			$msg =& $this->_buildEmail($comment, $owner->realname, true);
			$this->_sendMail($owner->email, $msg); //sending contributor comment from suggestor
		}
	}

	/**
	 * ticket is closed, the comment is sent to owner and suggester
	 * aChanges should map item ids to boolean acceptance flag
	 * @access public
	 */
	function closeTicket($user_id,$comment, $aChanges=null)
	{
		$db=&$this->_getDB();
		if (!$this->isValid())
			die("closeTicket - bad ticket");

		//add comment to ticket
		$comment=trim($comment);
		$dbcomment=$comment;
		if (strlen($dbcomment)) {
			if (!preg_match("/[\.\!]\s*$/",$dbcomment))
				$dbcomment.=".";
			$dbcomment.="\n ";
		}
		$dbcomment.="Suggestion is now closed";

		$this->_addComment($user_id, $dbcomment);

		$image= $this->_getImage();

		//apply and summarise changes
		$changes="";
		$this->commit_count=0;
		$this->loadItems();
		foreach($this->changes as $idx=>$item)
		{
			if ($aChanges[$item['gridimage_ticket_item_id']])
			{
				//apply this change
				$this->changes[$idx]['status']='approved';
				$this->changes[$idx]['approver_id']=$user_id;

				//updateField does the hard work
				$this->updateField($item['field'], $item['oldvalue'], $item['newvalue'], false);

				if ($item['oldvalue']!=$item['newvalue'])
				{
					if ($item['field'] == 'comment') {
						$changes.="{$item['field']} changed from <br>".
							str_repeat('~',70)."<br>".nl2br($item['oldhtml'],false)."<br>".str_repeat('~',70)."<br>".
							"{$item['field']} changed to <br>".
							str_repeat('~',70)."<br>".nl2br($item['newhtml'],false)."<br>".str_repeat('~',70)."<br>";
					} else {
						$changes.="{$item['field']} changed from \"{$item['oldhtml']}\" to \"{$item['newhtml']}\"<br>";
					}
				}
			}
			elseif ($this->changes[$idx]['status'] != 'immediate')
			{
				//if it hasn't already been aplied reject it
				$this->changes[$idx]['status']='rejected';
				$this->changes[$idx]['approver_id']=$user_id;
			}
		}
		$this->commit('closed');

		//message to suggester (if not the owner and not the moderator closing the ticket!)
		if ($this->user_id != $image->user_id && $this->moderator_id != $this->user_id )
		{
			$suggester_msg="Many thanks for your feedback on this photo, we've now closed this issue.";
			if (!empty($changes))
			{
				$suggester_msg.=" The following changes were made:<br><br>";
				$suggester_msg.=$changes; //assume its already html safe!
			}
			if (!empty($dbcomment) && $dbcomment != "Suggestion is now closed")
			{
				$suggester_msg.="<br> Moderators Comment:<br><br>";
				$suggester_msg.=htmlentities2($dbcomment);
			}

			$suggester=new GeographUser($this->user_id);

			$msg =& $this->_buildEmail($suggester_msg,$suggester->realname);
			$this->_sendMail($suggester->email, $msg);    //notifying suggestor that ticket closed
		}

		//message to owner
		$owner_msg="We've now closed this issue. ";
		if (!empty($changes))
		{
			$owner_msg.=" The following changes were made:<br><br>";
			$owner_msg.=$changes;
		}
		if (!empty($dbcomment) && $dbcomment != "Suggestion is now closed")
		{
			$owner_msg.="<br> Moderators Comment:<br><br>";
			$owner_msg.=htmlentities2($dbcomment);
		}
		$owner=new GeographUser($image->user_id);
		if ($owner->ticket_option != 'off') { // the message IS send in case of 'none'!
			$msg =& $this->_buildEmail($owner_msg, $owner->realname, true);
			$this->_sendMail($owner->email, $msg);  //notifying contributor that ticket closed
		}

		$db->Execute("DELETE FROM gridimage_moderation_lock WHERE user_id = {$this->moderator_id} AND gridimage_id = {$this->gridimage_id}");
	}

	/**
	 * get stored gridimage object, creating if necessary
	 * @access private
	 */
	function &_getImage()
	{
		if (empty($this->gridimage) || !is_object($this->gridimage))
		{
			$this->gridimage=new GridImage();
			$this->gridimage->loadFromId($this->gridimage_id);
		}
		return $this->gridimage;
	}

	/**
	 * get stored db object, creating if necessary
	 * @access private
	 */
	function &_getDB($allow_readonly = false)
	{
		//check we have a db object or if we need to 'upgrade' it
		if (!is_object($this->db) || ($this->db->readonly && !$allow_readonly) ) {
			$this->db=GeographDatabaseConnection($allow_readonly);
		}
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
	 * clear all member vars
	 * @access private
	 */
	function _clear()
	{
		$vars=get_object_vars($this);
		foreach($vars as $name=>$val)
		{
			if ($name!="db")
				unset($this->$name);
		}
		$this->commit_count=0;
		$this->changes=array();
	}

	/**
	* assign members from array containing required members
	* @access private
	*/
	function _initFromArray(&$arr)
	{
		foreach($arr as $name=>$value)
		{
			if (!is_numeric($name))
				$this->$name=$value;
		}
	}

	/**
	* return true if instance references a valid grid image tic
	* @access public
	*/
	function isValid()
	{
		return isset($this->gridimage_ticket_id) && ($this->gridimage_ticket_id>0);
	}

	/**
	* assign members from recordset containing required members
	* @access public
	*/
	function loadFromRecordset(&$rs)
	{
		$this->_clear();
		$this->_initFromArray($rs->fields);
		return $this->isValid();
	}

	/**
	* load all change items
	* @access public
	*/
	function loadItems()
	{
		$db=&$this->_getDB(5);
		if ($this->isValid())
		{
			$this->changes=$db->GetAll("select * from gridimage_ticket_item where gridimage_ticket_id={$this->gridimage_ticket_id}");

			if (count($this->changes)) {
				$token=new Token;
				foreach ($this->changes as $i => &$row) {
					if (!empty($row['newvalue']) && $row['newvalue'] != -1) {
						switch($row['field']) {
							case 'grid_reference':		$token->setValue("g", $row['newvalue']); break;
							case 'photographer_gridref':	$token->setValue("p", $row['newvalue']); break;
							case 'view_direction':		$token->setValue("v", $row['newvalue']); break;
						}
					}
					if ($row['newvalue'] != $row['oldvalue'] && !is_numeric($row['newvalue']) && !is_numeric($row['oldvalue'])) {
						require_once "3rdparty/simplediff.inc.php";

						list($row['oldhtml'], $row['newhtml']) = htmlCharDiff($row['oldvalue'], $row['newvalue']); //automatically calls htmlentities2
					} else {
						$row['oldhtml'] = htmlentities2($row['oldvalue']);
						$row['newhtml'] = htmlentities2($row['newvalue']);
					}
				}
				$count = count($token->data);
				if ($count > 0 && !($count == 1 && $token->hasValue('v')) ) {
					$this->reopenmaptoken = $token->getToken();
				}
			}
		}
	}

	/**
	* load all comments
	* @access public
	*/
	function loadComments()
	{
		$db=&$this->_getDB(5);
		if ($this->isValid())
		{
			$this->comments=$db->GetAll("select c.*,u.realname , ".
				"FIND_IN_SET('ticketmod',u.rights)>0 as moderator ".
				"from gridimage_ticket_comment as c inner join user as u using(user_id) ".
				"where gridimage_ticket_id={$this->gridimage_ticket_id} order by c.added");
			if ($n = count($this->comments))
				$this->lastcomment =& $this->comments[$n-1];
		}
	}

	/**
	* assign members from gridimage_ticket_id
	* @access public
	*/
	function loadFromId($gridimage_ticket_id)
	{
		$db=&$this->_getDB(5);

		$this->_clear();
		if (preg_match('/^\d+$/', $gridimage_ticket_id))
		{
			$merge = $db->getOne("SHOW TABLES LIKE 'gridimage_ticket_merge'")?'_merge':'';
			$row = $db->GetRow("select * from gridimage_ticket$merge where gridimage_ticket_id={$gridimage_ticket_id} limit 1");
			if (is_array($row))
			{
				$this->_initFromArray($row);
			}
		}
		return $this->isValid();
	}
}
