<?php
/*******************************************************************************
*  Title: Help Desk Software HESK
*  Version: 2.6.2 from 18th March 2015
*  Author: Klemen Stirn
*  Website: http://www.hesk.com
********************************************************************************
*  COPYRIGHT AND TRADEMARK NOTICE
*  Copyright 2005-2015 Klemen Stirn. All Rights Reserved.
*  HESK is a registered trademark of Klemen Stirn.

*  The HESK may be used and modified free of charge by anyone
*  AS LONG AS COPYRIGHT NOTICES AND ALL THE COMMENTS REMAIN INTACT.
*  By using this code you agree to indemnify Klemen Stirn from any
*  liability that might arise from it's use.

*  Selling the code for this program, in part or full, without prior
*  written consent is expressly forbidden.

*  Using this code, in part or full, to create derivate work,
*  new scripts or products is expressly forbidden. Obtain permission
*  before redistributing this software over the Internet or in
*  any other medium. In all cases copyright and header must remain intact.
*  This Copyright is in full effect in any country that has International
*  Trade Agreements with the United States of America or
*  with the European Union.

*  Removing any of the copyright notices without purchasing a license
*  is expressly forbidden. To remove HESK copyright notice you must purchase
*  a license for this script. For more information on how to obtain
*  a license please visit the page below:
*  https://www.hesk.com/buy.php
*******************************************************************************/

define('IN_SCRIPT',1);
define('HESK_PATH','../');

/* Get all the required files and functions */
require(HESK_PATH . 'hesk_settings.inc.php');
require(HESK_PATH . 'modsForHesk_settings.inc.php');
require(HESK_PATH . 'inc/common.inc.php');
require(HESK_PATH . 'inc/admin_functions.inc.php');
hesk_load_database_functions();

hesk_session_start();
hesk_dbConnect();
hesk_isLoggedIn();

/* Check permissions for this feature */
hesk_checkPermission('can_view_tickets');
$can_del_notes		 = hesk_checkPermission('can_del_notes',0);
$can_reply			 = hesk_checkPermission('can_reply_tickets',0);
$can_delete			 = hesk_checkPermission('can_del_tickets',0);
$can_edit			 = hesk_checkPermission('can_edit_tickets',0);
$can_archive		 = hesk_checkPermission('can_add_archive',0);
$can_assign_self	 = hesk_checkPermission('can_assign_self',0);
$can_view_unassigned = hesk_checkPermission('can_view_unassigned',0);
$can_change_cat		 = hesk_checkPermission('can_change_cat',0);
$can_ban_emails		 = hesk_checkPermission('can_ban_emails', 0);
$can_unban_emails	 = hesk_checkPermission('can_unban_emails', 0);
$can_ban_ips		 = hesk_checkPermission('can_ban_ips', 0);
$can_unban_ips		 = hesk_checkPermission('can_unban_ips', 0);

// Get ticket ID
$trackingID = hesk_cleanID() or print_form();

$_SERVER['PHP_SELF'] = 'admin_ticket.php?track='.$trackingID.'&Refresh='.mt_rand(10000,99999);

/* We will need timer function */
define('TIMER',1);

/* Get ticket info */
$res = hesk_dbQuery("SELECT `t1`.* , `t2`.name AS `repliername` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` AS `t1` LEFT JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."users` AS `t2` ON `t1`.`replierid` = `t2`.`id` WHERE `trackid`='".hesk_dbEscape($trackingID)."' LIMIT 1");

/* Ticket found? */
if (hesk_dbNumRows($res) != 1)
{
	/* Ticket not found, perhaps it was merged with another ticket? */
	$res = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` WHERE `merged` LIKE '%#".hesk_dbEscape($trackingID)."#%' LIMIT 1");

	if (hesk_dbNumRows($res) == 1)
	{
    	/* OK, found in a merged ticket. Get info */
     	$ticket = hesk_dbFetchAssoc($res);
        hesk_process_messages( sprintf($hesklang['tme'], $trackingID, $ticket['trackid']) ,'NOREDIRECT','NOTICE');
        $trackingID = $ticket['trackid'];
	}
    else
    {
    	/* Nothing found, error out */
	    hesk_process_messages($hesklang['ticket_not_found'],'NOREDIRECT');
	    print_form();
    }
}
else
{
	/* We have a match, get ticket info */
	$ticket = hesk_dbFetchAssoc($res);
}

/* Permission to view this ticket? */
if ($ticket['owner'] && $ticket['owner'] != $_SESSION['id'] && ! hesk_checkPermission('can_view_ass_others',0))
{
	hesk_error($hesklang['ycvtao']);
}

if (!$ticket['owner'] && ! $can_view_unassigned)
{
	hesk_error($hesklang['ycovtay']);
}

/* Set last replier name */
if ($ticket['lastreplier'])
{
	if (empty($ticket['repliername']))
	{
		$ticket['repliername'] = $hesklang['staff'];
	}
}
else
{
	$ticket['repliername'] = $ticket['name'];
}

/* Get category name and ID */
$result = hesk_dbQuery("SELECT `id`, `name` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` WHERE `id`='".intval($ticket['category'])."' LIMIT 1");

/* If this category has been deleted use the default category with ID 1 */
if (hesk_dbNumRows($result) != 1)
{
    $result = hesk_dbQuery("SELECT `id`, `name` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` WHERE `id`='1' LIMIT 1");
}

$category = hesk_dbFetchAssoc($result);

/* Is this user allowed to view tickets inside this category? */
hesk_okCategory($category['id']);

/* Delete post action */
if (isset($_GET['delete_post']) && $can_delete && hesk_token_check())
{
	$n = intval( hesk_GET('delete_post') );
    if ($n)
    {
		/* Get last reply ID, we'll need it later */
		$res = hesk_dbQuery("SELECT `id` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."replies` WHERE `replyto`='".intval($ticket['id'])."' ORDER BY `id` DESC LIMIT 1");
        $last_reply_id = hesk_dbResult($res,0,0);

        // Was this post submitted by staff and does it have any attachments?
        $res = hesk_dbQuery("SELECT `dt`, `staffid`, `attachments` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."replies` WHERE `id`='".intval($n)."' AND `replyto`='".intval($ticket['id'])."' LIMIT 1");
        $reply = hesk_dbFetchAssoc($res);

        // If the reply was by a staff member update the appropriate columns
        if ( $reply['staffid'] )
        {
            // Is this the only staff reply? Delete "firstreply" and "firstreplyby" columns
            if ($ticket['staffreplies'] <= 1)
            {
                $staffreplies_sql = ' , `firstreply`=NULL, `firstreplyby`=NULL, `staffreplies`=0 ';
            }
            // Are we deleting the first staff reply? Update "firstreply" and "firstreplyby" columns
            elseif ($reply['dt'] == $ticket['firstreply'] && $reply['staffid'] == $ticket['firstreplyby'])
            {
                // Get the new first reply info
                $res = hesk_dbQuery("SELECT `dt`, `staffid` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."replies` WHERE `replyto`='".intval($ticket['id'])."' AND `id`!='".intval($n)."' AND `staffid`!=0 ORDER BY `id` ASC LIMIT 1");

                // Did we find the new first reply?
                if ( hesk_dbNumRows($res) )
                {
                    $firstreply = hesk_dbFetchAssoc($res);
                    $staffreplies_sql = " , `firstreply`='".hesk_dbEscape($firstreply['dt'])."', `firstreplyby`='".hesk_dbEscape($firstreply['staffid'])."', `staffreplies`=`staffreplies`-1 ";
                }
                // The count must have been wrong, update it
                else
                {
                    $staffreplies_sql = ' , `firstreply`=NULL, `firstreplyby`=NULL, `staffreplies`=0 ';
                }
            }
            // OK, this is not the first and not the only staff reply, just reduce number
            else
            {
                $staffreplies_sql = ' , `staffreplies`=`staffreplies`-1 ';
            }
        }
        else
        {
            $staffreplies_sql = '';
        }

		/* Delete any attachments to this post */
        if ( strlen($reply['attachments']) )
		{
        	$hesk_settings['server_path'] = dirname(dirname(__FILE__));

			/* List of attachments */
            $att=explode(',',substr($reply['attachments'], 0, -1));
			foreach ($att as $myatt)
			{
				list($att_id, $att_name) = explode('#', $myatt);

				/* Delete attachment files */
				$res = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."attachments` WHERE `att_id`='".intval($att_id)."' LIMIT 1");
				if (hesk_dbNumRows($res) && $file = hesk_dbFetchAssoc($res))
				{
					hesk_unlink($hesk_settings['server_path'].'/'.$hesk_settings['attach_dir'].'/'.$file['saved_name']);
				}

				/* Delete attachments info from the database */
				hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."attachments` WHERE `att_id`='".intval($att_id)."' LIMIT 1");
			}
		}

		/* Delete this reply */
		hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."replies` WHERE `id`='".intval($n)."' AND `replyto`='".intval($ticket['id'])."' LIMIT 1");

        /* Reply wasn't deleted */
        if (hesk_dbAffectedRows() != 1)
        {
			hesk_process_messages($hesklang['repl1'],$_SERVER['PHP_SELF']);
        }
        else
        {
            $closed_sql = '';
            $changeStatusRs = hesk_dbQuery('SELECT `id`, `LockedTicketStatus`, `IsCustomerReplyStatus`, `IsDefaultStaffReplyStatus`, `IsNewTicketStatus`
                                                  FROM `'.hesk_dbEscape($hesk_settings['db_pfix']).'statuses`
                                                  WHERE `LockedTicketStatus` = 1
                                                    OR `IsCustomerReplyStatus` = 1
                                                    OR `IsDefaultStaffReplyStatus` = 1
                                                    OR `IsNewTicketStatus` = 1');
            $lockedTicketStatus = '';
            $customerReplyStatus = '';
            $defaultStaffReplyStatus = '';
            $newTicketStatus = '';
            while ($row = hesk_dbFetchAssoc($changeStatusRs))
            {
                if ($row['LockedTicketStatus']) {
                    $lockedTicketStatus = $row['id'];
                } elseif ($row['IsCustomerReplyStatus']) {
                    $customerReplyStatus = $row['id'];
                } elseif ($row['IsDefaultStaffReplyStatus']) {
                    $defaultStaffReplyStatus = $row['id'];
                } elseif ($row['IsNewTicketStatus']) {
                    $newTicketStatus = $row['id'];
                }
            }

			/* Reply deleted. Need to update status and last replier? */
            $res = hesk_dbQuery("SELECT `dt`, `staffid` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."replies` WHERE `replyto`='".intval($ticket['id'])."' ORDER BY `id` DESC LIMIT 1");
			if (hesk_dbNumRows($res))
			{
                $replier_id = hesk_dbResult($res,0,1);
                $last_replier = $replier_id ? 1 : 0;

				/* Change status? */
                $status_sql = '';
				if ($last_reply_id == $n)
				{
					$status = $ticket['locked'] ? $lockedTicketStatus : ($last_replier ? $defaultStaffReplyStatus : $customerReplyStatus);
                    $status_sql = " , `status`='".intval($status)."' ";

                    // Update closedat and closedby columns as required
                    if ($status == $lockedTicketStatus)
                    {
                        $closed_sql = " , `closedat`=NOW(), `closedby`=".intval($_SESSION['id'])." ";
                    }
				}

                hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` SET `lastchange`=NOW(), `lastreplier`='{$last_replier}', `replierid`='".intval($replier_id)."', `replies`=`replies`-1 $status_sql $closed_sql $staffreplies_sql WHERE `id`='".intval($ticket['id'])."' LIMIT 1");
			}
			else
			{
                // Update status, closedat and closedby columns as required
                if ($ticket['locked'])
                {
                    $status = $lockedTicketStatus;
                    $closed_sql = " , `closedat`=NOW(), `closedby`=".intval($_SESSION['id'])." ";
                }
                else
                {
                    $status = $newTicketStatus;
                    $closed_sql = " , `closedat`=NULL, `closedby`=NULL ";
                }

                hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` SET `lastchange`=NOW(), `lastreplier`='0', `status`='$status', `replies`=0 $staffreplies_sql WHERE `id`='".intval($ticket['id'])."' LIMIT 1");
			}

			hesk_process_messages($hesklang['repl'],$_SERVER['PHP_SELF'],'SUCCESS');
        }
    }
    else
    {
    	hesk_process_messages($hesklang['repl0'],$_SERVER['PHP_SELF']);
    }
}

/* Delete notes action */
if (isset($_GET['delnote']) && hesk_token_check())
{
	$n = intval( hesk_GET('delnote') );
    if ($n)
    {
        // Get note info
        $res = hesk_dbQuery("SELECT `who`, `attachments` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."notes` WHERE `id`={$n}");

        if ( hesk_dbNumRows($res) )
        {
            $note = hesk_dbFetchAssoc($res);

            // Permission to delete note?
            if ($can_del_notes || $note['who'] == $_SESSION['id'])
            {
                // Delete note
                hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."notes` WHERE `id`='".intval($n)."' LIMIT 1");

                // Delete attachments
                if ( strlen($note['attachments']) )
                {
                    $hesk_settings['server_path'] = dirname(dirname(__FILE__));

                    $attachments = array();

                    $att=explode(',',substr($note['attachments'], 0, -1));
                    foreach ($att as $myatt)
                    {
                        list($att_id, $att_name) = explode('#', $myatt);
                        $attachments[] = intval($att_id);
                    }

                    if ( count($attachments) )
                    {
                        $res = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."attachments` WHERE `att_id` IN (".implode(',', $attachments).") ");
                        while ($file = hesk_dbFetchAssoc($res))
                        {
                            hesk_unlink($hesk_settings['server_path'].'/'.$hesk_settings['attach_dir'].'/'.$file['saved_name']);
                        }
                        hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."attachments` WHERE `att_id` IN (".implode(',', $attachments).") ");
                    }
                }
            }
        }
    }

    header('Location: admin_ticket.php?track='.$trackingID.'&Refresh='.mt_rand(10000,99999));
    exit();
}

/* Add a note action */
if (isset($_POST['notemsg']) && hesk_token_check('POST'))
{
    // Error buffer
    $hesk_error_buffer = array();

    // Get message
	$msg = hesk_input( hesk_POST('notemsg') );

    // Get attachments
    if ($hesk_settings['attachments']['use'])
    {
        require(HESK_PATH . 'inc/posting_functions.inc.php');
        require(HESK_PATH . 'inc/attachments.inc.php');
        $attachments = array();
        for ($i=1;$i<=$hesk_settings['attachments']['max_number'];$i++)
        {
            $att = hesk_uploadFile($i);
            if ($att !== false && !empty($att))
            {
                $attachments[$i] = $att;
            }
        }
    }
    $myattachments='';

    // We need message and/or attachments to accept note
    if ( count($attachments) || strlen($msg) || count($hesk_error_buffer) )
    {
        // Any errors?
        if ( count($hesk_error_buffer) != 0 )
        {
            $_SESSION['note_message'] = hesk_POST('notemsg');

            // Remove any successfully uploaded attachments
            if ($hesk_settings['attachments']['use'])
            {
                hesk_removeAttachments($attachments);
            }

            $tmp = '';
            foreach ($hesk_error_buffer as $error)
            {
                $tmp .= "<li>$error</li>\n";
            }
            $hesk_error_buffer = $tmp;

            $hesk_error_buffer = $hesklang['pcer'].'<br /><br /><ul>'.$hesk_error_buffer.'</ul>';
            hesk_process_messages($hesk_error_buffer,'admin_ticket.php?track='.$ticket['trackid'].'&Refresh='.rand(10000,99999));
        }

        // Process attachments
        if ($hesk_settings['attachments']['use'] && ! empty($attachments) )
        {
            foreach ($attachments as $myatt)
            {
                hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."attachments` (`ticket_id`,`saved_name`,`real_name`,`size`,`type`) VALUES ('".hesk_dbEscape($trackingID)."','".hesk_dbEscape($myatt['saved_name'])."','".hesk_dbEscape($myatt['real_name'])."','".intval($myatt['size'])."', '1')");
                $myattachments .= hesk_dbInsertID() . '#' . $myatt['real_name'] . '#' . $myatt['saved_name'] .',';
            }
        }

        // Add note to database
        $msg = nl2br(hesk_makeURL($msg));
        hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."notes` (`ticket`,`who`,`dt`,`message`,`attachments`) VALUES ('".intval($ticket['id'])."','".intval($_SESSION['id'])."',NOW(),'".hesk_dbEscape($msg)."','".hesk_dbEscape($myattachments)."')");

        /* Notify assigned staff that a note has been added if needed */
        $users = hesk_dbQuery("SELECT `email`, `notify_note` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users` WHERE (`id`='".intval($ticket['owner'])."' OR (`isadmin` = '1' AND `notify_note_unassigned` = '1')) AND `id` <> '".intval($_SESSION['id'])."'");

        if (hesk_dbNumRows($users) > 0)
        {
            // 1. Generate the array with ticket info that can be used in emails
            $info = array(
            'email'			=> $ticket['email'],
            'category'		=> $ticket['category'],
            'priority'		=> $ticket['priority'],
            'owner'			=> $ticket['owner'],
            'trackid'		=> $ticket['trackid'],
            'status'		=> $ticket['status'],
            'name'			=> $_SESSION['name'],
            'lastreplier'	=> $ticket['lastreplier'],
            'subject'		=> $ticket['subject'],
            'message'		=> stripslashes($msg),
            'dt'            => hesk_date($ticket['dt'], true),
            'lastchange'    => hesk_date($ticket['lastchange'], true),
            'attachments'	=> $myattachments,
            'id'			=> $ticket['id'],
            );

            // 2. Add custom fields to the array
            foreach ($hesk_settings['custom_fields'] as $k => $v)
            {
                $info[$k] = $v['use'] ? $ticket[$k] : '';
            }

            // 3. Make sure all values are properly formatted for email
            $ticket = hesk_ticketToPlain($info, 1, 0);

            /* Get email functions */
            require(HESK_PATH . 'inc/email_functions.inc.php');

            /* Format email subject and message for staff */
            $subject = hesk_getEmailSubject('new_note',$ticket);
            $message = hesk_getEmailMessage('new_note',$ticket,1);
            $htmlMessage = hesk_getHtmlMessage('new_note',$ticket,1);
            $hasMessage = hesk_doesTemplateHaveTag('new_note', '%%MESSAGE%%');


            /* Send email to staff */
            while ($user = hesk_dbFetchAssoc($users)) {
                hesk_mail($user['email'], $subject, $message, $htmlMessage, array(), array(), $hasMessage);
            }
        }
    }
    header('Location: admin_ticket.php?track='.$trackingID.'&Refresh='.mt_rand(10000,99999));
    exit();
}

/* Update time worked */
if ($hesk_settings['time_worked'] && ($can_reply || $can_edit) && isset($_POST['h']) && isset($_POST['m']) && isset($_POST['s']) && hesk_token_check('POST'))
{
	$h = intval( hesk_POST('h') );
	$m = intval( hesk_POST('m') );
	$s = intval( hesk_POST('s') );

	/* Get time worked in proper format */
    $time_worked = hesk_getTime($h . ':' . $m . ':' . $s);

	/* Update database */
    $revision = sprintf($hesklang['thist14'],hesk_date(),$time_worked,$_SESSION['name'].' ('.$_SESSION['user'].')');
	hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` SET `time_worked`='" . hesk_dbEscape($time_worked) . "', `history`=CONCAT(`history`,'" . hesk_dbEscape($revision) . "') WHERE `trackid`='" . hesk_dbEscape($trackingID) . "' LIMIT 1");

	/* Show ticket */
	hesk_process_messages($hesklang['twu'],'admin_ticket.php?track='.$trackingID.'&Refresh='.mt_rand(10000,99999),'SUCCESS');
}

/* Add child action */
if (($can_reply || $can_edit) && isset($_POST['childTrackingId'])) {
    //-- Make sure this isn't the same ticket or one of its merged tickets.
    $mergedTickets = hesk_dbQuery('SELECT * FROM `'.hesk_dbEscape($hesk_settings['db_pfix']).'tickets` WHERE `trackid` =
        \''.hesk_dbEscape($trackingID).'\' AND `merged` LIKE \'%#'.hesk_dbEscape($_POST['childTrackingId']).'#%\'');
    if ($_POST['childTrackingId'] == $trackingID || $mergedTickets->num_rows > 0) {
        hesk_process_messages($hesklang['child_is_itself'], 'admin_ticket.php?track='.$trackingID.'&Refresh='.mt_rand(10000,99999));
    }

    //-- Does the child exist?
    $existRs = hesk_dbQuery('SELECT * FROM `'.hesk_dbEscape($hesk_settings['db_pfix']).'tickets` WHERE `trackid` = \''.hesk_dbEscape($_POST['childTrackingId']).'\'');
    if ($existRs->num_rows == 0) {
        //-- Maybe it was merged?
        $existRs = hesk_dbQuery('SELECT `trackid` FROM `'.hesk_dbEscape($hesk_settings['db_pfix']).'tickets` WHERE `merged` LIKE \'#'.hesk_dbEscape($_POST['childTrackingId']).'#\'');
        if ($existRs->num_rows > 0) {
            //-- Yes, it was merged. Set the child to the "new" ticket; not the merged one.
            $exist = $existRs->fetch_assoc();
            $_POST['childTrackingId'] = $exist['trackid'];
        } else {
            hesk_process_messages(sprintf($hesklang['child_does_not_exist'], $_POST['childTrackingId']), 'admin_ticket.php?track='.$trackingID.'&Refresh='.mt_rand(10000,99999));
        }
    }

    //-- Check if the ticket is already a child.
    $childRs = hesk_dbQuery('SELECT * FROM `'.hesk_dbEscape($hesk_settings['db_pfix']).'tickets` WHERE `parent` = '.$ticket['id'].' AND `trackid` = \''.$_POST['childTrackingId'].'\'');
    if ($childRs->num_rows > 0) {
        hesk_process_messages(sprintf($hesklang['is_child_already'], $_POST['childTrackingId']), 'admin_ticket.php?track='.$trackingID.'&Refresh='.mt_rand(10000,99999), 'NOTICE');
    }

    hesk_dbQuery('UPDATE `'.hesk_dbEscape($hesk_settings['db_pfix']).'tickets` SET `parent` = '.$ticket['id'].' WHERE `trackid` = \''.$_POST['childTrackingId'].'\'');
    hesk_process_messages(sprintf($hesklang['child_added'], $_POST['childTrackingId']), 'admin_ticket.php?track='.$trackingID.'&Refresh='.mt_rand(10000,99999), 'SUCCESS');
}

/* Delete child action */
if (($can_reply || $can_edit) && isset($_GET['deleteChild'])) {
    //-- Delete the relationship
    hesk_dbQuery('UPDATE `'.hesk_dbEscape($hesk_settings['db_pfix']).'tickets` SET `parent` = NULL WHERE `ID` = '.hesk_dbEscape($_GET['deleteChild']));
    hesk_process_messages($hesklang['relationship_deleted'], 'admin_ticket.php?track='.$trackingID.'&Refresh='.mt_rand(10000,99999), 'SUCCESS');

} elseif (($can_reply || $can_edit) && isset($_GET['deleteParent'])) {
    //-- Delete the relationship
    hesk_dbQuery('UPDATE `'.hesk_dbEscape($hesk_settings['db_pfix']).'tickets` SET `parent` = NULL WHERE `ID` = '.hesk_dbEscape($ticket['id']));
    hesk_process_messages($hesklang['relationship_deleted'], 'admin_ticket.php?track='.$trackingID.'&Refresh='.mt_rand(10000,99999), 'SUCCESS');
}

/* Delete attachment action */
if (isset($_GET['delatt']) && hesk_token_check())
{
	if ( ! $can_delete || ! $can_edit)
    {
		hesk_process_messages($hesklang['no_permission'],'admin_ticket.php?track='.$trackingID.'&Refresh='.mt_rand(10000,99999));
    }

	$att_id = intval( hesk_GET('delatt') ) or hesk_error($hesklang['inv_att_id']);

	$reply = intval( hesk_GET('reply', 0) );
	if ($reply < 1)
	{
		$reply = 0;
	}

    $note = intval( hesk_GET('note', 0) );
    if ($note < 1)
    {
        $note = 0;
    }

    /* Get attachment info */
	$res = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."attachments` WHERE `att_id`='".intval($att_id)."' LIMIT 1");
	if (hesk_dbNumRows($res) != 1)
	{
		hesk_process_messages($hesklang['id_not_valid'].' (att_id)','admin_ticket.php?track='.$trackingID.'&Refresh='.mt_rand(10000,99999));
	}
	$att = hesk_dbFetchAssoc($res);

	/* Is ticket ID valid for this attachment? */
	if ($att['ticket_id'] != $trackingID)
	{
		hesk_process_messages($hesklang['trackID_not_found'],'admin_ticket.php?track='.$trackingID.'&Refresh='.mt_rand(10000,99999));
	}

	/* Delete file from server */
	hesk_unlink(HESK_PATH.$hesk_settings['attach_dir'].'/'.$att['saved_name']);

	/* Delete attachment from database */
	hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."attachments` WHERE `att_id`='".intval($att_id)."'");

	/* Update ticket or reply in the database */
    $revision = sprintf($hesklang['thist12'],hesk_date(),$att['real_name'],$_SESSION['name'].' ('.$_SESSION['user'].')');
	if ($reply)
	{
		hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."replies` SET `attachments`=REPLACE(`attachments`,'".hesk_dbEscape($att_id.'#'.$att['real_name'].'#'.$att['saved_name']).",','') WHERE `id`='".intval($reply)."' LIMIT 1");
        hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."replies` SET `attachments`=REPLACE(`attachments`,'".hesk_dbEscape($att_id.'#'.$att['real_name']).",','') WHERE `id`='".intval($reply)."' LIMIT 1");
		hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` SET `history`=CONCAT(`history`,'".hesk_dbEscape($revision)."') WHERE `id`='".intval($ticket['id'])."' LIMIT 1");
	}
    elseif ($note)
    {
        hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."notes` SET `attachments`=REPLACE(`attachments`,'".hesk_dbEscape($att_id.'#'.$att['real_name'].'#'.$att['saved_name']).",','') WHERE `id`={$note} LIMIT 1");
        hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."notes` SET `attachments`=REPLACE(`attachments`,'".hesk_dbEscape($att_id.'#'.$att['real_name']).",','') WHERE `id`={$note} LIMIT 1");
    }
	else
	{
        hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` SET `attachments`=REPLACE(`attachments`,'".hesk_dbEscape($att_id.'#'.$att['real_name'].'#'.$att['saved_name']).",','') WHERE `id`='".intval($ticket['id'])."' LIMIT 1");
		hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets` SET `attachments`=REPLACE(`attachments`,'".hesk_dbEscape($att_id.'#'.$att['real_name']).",',''), `history`=CONCAT(`history`,'".hesk_dbEscape($revision)."') WHERE `id`='".intval($ticket['id'])."' LIMIT 1");
	}

	hesk_process_messages($hesklang['kb_att_rem'],'admin_ticket.php?track='.$trackingID.'&Refresh='.mt_rand(10000,99999),'SUCCESS');
}

/* Print header */
require_once(HESK_PATH . 'inc/headerAdmin.inc.php');

/* List of categories */
$result = hesk_dbQuery("SELECT `id`,`name` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."categories` ORDER BY `cat_order` ASC");
$categories_options='';
while ($row=hesk_dbFetchAssoc($result))
{
    $selected = '';
    if ($row['id'] == $ticket['category']) {$selected='selected';}
    $categories_options.='<option value="'.$row['id'].'" '.$selected.'>'.$row['name'].'</option>';
}

/* List of users */
$admins = array();
$result = hesk_dbQuery("SELECT `id`,`name`,`isadmin`,`categories`,`heskprivileges` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users` WHERE `active` = '1' ORDER BY `name` ASC");
while ($row=hesk_dbFetchAssoc($result))
{
	/* Is this an administrator? */
	if ($row['isadmin'])
    {
	    $admins[$row['id']]=$row['name'];
	    continue;
    }

	/* Not admin, is user allowed to view tickets? */
	if (strpos($row['heskprivileges'], 'can_view_tickets') !== false)
	{
		/* Is user allowed to access this category? */
		$cat=substr($row['categories'], 0);
		$row['categories']=explode(',',$cat);
		if (in_array($ticket['category'],$row['categories']))
		{
			$admins[$row['id']]=$row['name'];
			continue;
		}
	}
}

/* Get replies */
if ($ticket['replies'])
{
    $reply = '';
    $result = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."replies` WHERE `replyto`='".intval($ticket['id'])."' ORDER BY `id` " . ($hesk_settings['new_top'] ? 'DESC' : 'ASC') );
}
else
{
    $reply = false;
}

// Demo mode
if ( defined('HESK_DEMO') )
{
	$ticket['email'] = 'hidden@demo.com';
	$ticket['ip']	 = '127.0.0.1';
}

// If an email address is tied to this ticket, check if there are any others
$recentTickets = NULL;
if($ticket['email'] != '') {
    $recentTicketsSql = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."tickets`
    WHERE `email` = '".hesk_dbEscape($ticket['email'])."' AND `trackid` <> '".hesk_dbEscape($trackingID)."' ORDER BY `lastchange` DESC LIMIT 5");
    while ($recentRow = hesk_dbFetchAssoc($recentTicketsSql)) {
        if ($recentTickets === NULL) {
            $recentTickets = array();
        }
        array_push($recentTickets, $recentRow);
    }

    if ($recentTickets !== NULL) {
        $recentTicketsWithStatuses = array();
        foreach ($recentTickets as $recentTicket) {
            $newRecentTicket = $recentTicket;
            $thisTicketStatusRS = hesk_dbQuery("SELECT * FROM `" . hesk_dbEscape($hesk_settings['db_pfix']) . "statuses` WHERE `ID` = " . intval($recentTicket['status']));
            $theStatusRow = hesk_dbFetchAssoc($thisTicketStatusRS);
            $newRecentTicket['statusText'] = $hesklang[$theStatusRow['ShortNameContentKey']];
            $newRecentTicket['statusColor'] = $theStatusRow['TextColor'];
            array_push($recentTicketsWithStatuses, $newRecentTicket);
        }
        $recentTickets = $recentTicketsWithStatuses;
    }
}

/* Print admin navigation */
require_once(HESK_PATH . 'inc/show_admin_nav.inc.php');
?>
<div class="row" style="padding: 20px">
    <div class="col-md-2">
        <div class="panel panel-default">
            <div class="panel-heading"><?php echo $hesklang['information']; ?></div>
            <ul class="list-group">
                <li class="list-group-item">
                    <strong><?php echo $hesklang['trackID']; ?></strong><br/>
                    <?php

	                $tmp = '';
	                if ($hesk_settings['sequential'])
	                {
    	                $tmp = ' ('.$hesklang['seqid'].': '.$ticket['id'].')';
	                } 
                    
                    echo $trackingID.'<br/>'.$tmp;?>
                </li>
                <li class="list-group-item">
                    <strong><?php echo $hesklang['lgs']; ?></strong><br>
                    <?php echo $ticket['language'] !== NULL ? $ticket['language'] : HESK_DEFAULT_LANGUAGE; ?>
                </li>
                <li class="list-group-item">
                    <strong><?php echo $hesklang['replies']; ?></strong><br/>
                    <?php echo $ticket['replies']; ?>
                </li>
                <li class="list-group-item">
                    <strong><?php echo $hesklang['owner']; ?></strong><br/>
                    <?php
                    echo isset($admins[$ticket['owner']]) ? $admins[$ticket['owner']] :
        	             ($can_assign_self ? $hesklang['unas'].' — <a href="assign_owner.php?track='.$trackingID.'&amp;owner='.$_SESSION['id'].'&amp;token='.hesk_token_echo(0).'">'.$hesklang['asss'].'</a>' : $hesklang['unas']);
                    ?>
                </li>
                <li class="list-group-item">
                    <strong><?php echo $hesklang['created_on']; ?></strong><br/>
                    <?php echo hesk_date($ticket['dt'], true); ?>
                </li>
                <li class="list-group-item">
                    <strong><?php echo $hesklang['last_update']; ?></strong><br/>
                    <?php echo hesk_date($ticket['lastchange'], true); ?>    
                </li>
                <li class="list-group-item">
                    <strong><?php echo $hesklang['last_replier']; ?></strong><br/>
                    <?php echo $ticket['repliername']; ?>
                </li>
                <?php
                if ($hesk_settings['time_worked']) {
                ?>
                <li class="list-group-item">
                    <strong><?php echo $hesklang['ts']; ?></strong><br/>
                    <?php
                    if ($can_reply || $can_edit)
                    {
                    ?>
	                <a href="Javascript:void(0)" onclick="Javascript:hesk_toggleLayerDisplay('modifytime')"><?php echo $ticket['time_worked']; ?></a>

                        <?php $t = hesk_getHHMMSS($ticket['time_worked']); ?>

		                <div id="modifytime" style="display:none">
			                <br />

			                <form method="post" action="admin_ticket.php" style="margin:0px; padding:0px;">
			                <table class="white">
			                <tr>
				                <td class="admin_gray"><?php echo $hesklang['hh']; ?>:</td>
				                <td class="admin_gray"><input type="text" name="h" value="<?php echo $t[0]; ?>" size="3" /></td>
			                </tr>
			                <tr>
				                <td class="admin_gray"><?php echo $hesklang['mm']; ?>:</td>
				                <td class="admin_gray"><input type="text" name="m" value="<?php echo $t[1]; ?>" size="3" /></td>
			                </tr>
			                <tr>
				                <td class="admin_gray"><?php echo $hesklang['ss']; ?>:</td>
				                <td class="admin_gray"><input type="text" name="s" value="<?php echo $t[2]; ?>" size="3" /></td>
			                </tr>
			                </table>

                            <br />

			                <input class="btn btn-default" type="submit" value="<?php echo $hesklang['save']; ?>" />
                            <a class="btn btn-default" href="Javascript:void(0)" onclick="Javascript:hesk_toggleLayerDisplay('modifytime')"><?php echo $hesklang['cancel']; ?></a>
                            <input type="hidden" name="track" value="<?php echo $trackingID; ?>" />
			                <input type="hidden" name="token" value="<?php hesk_token_echo(); ?>" />
			                </form>
		                </div>

                    </td>
                    <?php
                    }
                    else
                    {
    	                echo $ticket['time_worked'];
                    }
                    ?>
                </li>
                <?php } // End if time_worked ?>
                <li class="list-group-item">
                    <strong><?php echo $hesklang['parent']; ?></strong>
                    <p><?php
                        if ($ticket['parent'] != null) {
                            //-- Get the tracking ID of the parent
                            $parent = hesk_dbQuery('SELECT `trackid` FROM `'.hesk_dbEscape($hesk_settings['db_pfix']).'tickets`
                                WHERE `ID` = '.hesk_dbEscape($ticket['parent']))->fetch_assoc();
                            echo '<a href="admin_ticket.php?track='.$trackingID.'&Refresh='.mt_rand(10000,99999).'&deleteParent=true">
                                <i class="fa fa-times-circle" data-toggle="tooltip" data-placement="top" title="'.$hesklang['delete_relationship'].'"></i></a>';
                            echo '&nbsp;<a href="admin_ticket.php?track='.$parent['trackid'].'&Refresh='.mt_rand(10000,99999).'">'.$parent['trackid'].'</a>';
                        } else {
                            echo $hesklang['none'];
                        }
                    ?></p>
                </li>
                <li class="list-group-item">
                    <strong><?php echo $hesklang['children']; ?></strong>
                    <p><?php
                    //-- Check if any tickets have a parent set to this tracking ID
                    $hasRows = false;
                    $childrenRS = hesk_dbQuery('SELECT * FROM `'.hesk_dbEscape($hesk_settings['db_pfix']).'tickets`
                        WHERE `parent` = '.hesk_dbEscape($ticket['id']));
                    while ($row = $childrenRS->fetch_assoc()) {
                        $hasRows = true;
                        echo '<a href="admin_ticket.php?track='.$trackingID.'&Refresh='.mt_rand(10000,99999).'&deleteChild='.$row['id'].'">
                            <i class="fa fa-times-circle" data-toggle="tooltip" data-placement="top" title="'.$hesklang['delete_relationship'].'"></i></a>';
                        echo '&nbsp;<a href="admin_ticket.php?track='.$row['trackid'].'&Refresh='.mt_rand(10000,99999).'">'.$row['trackid'].'</a>';
                        echo '<br>';
                    }
                    if (!$hasRows) {
                        echo $hesklang['none'];
                    }
                    ?></p>
                    <?php
                    if ($can_reply || $can_edit) {
                    ?>
                    <div id="addChildText"><p><?php echo '<a class="btn btn-default btn-sm" href="javascript:void(0)" onclick="toggleChildrenForm(true)">'.$hesklang['add_child'].'</a>'; ?></p></div>
                    <div id="childrenForm" style="display: none">
                        <form action="admin_ticket.php" method="post">
                            <div class="form-group"><label for="childTrackingId" class="control-label">Tracking ID</label>
                            <input type="text" name="childTrackingId" class="form-control input-sm"></div>
                            <input type="submit" class="btn btn-primary btn-sm" value="<?php echo $hesklang['save']; ?>">
                            <a class="btn btn-default btn-sm" href="javascript:void(0)" onclick="toggleChildrenForm(false)">
                                <?php echo $hesklang['cancel']; ?>
                            </a>
                            <input type="hidden" name="track" value="<?php echo $trackingID; ?>" />
                            <input type="hidden" name="token" value="<?php hesk_token_echo(); ?>" />
                        </form>
                    </div>
                    <?php } ?>
                </li>
                <?php if ($recentTickets !== NULL): ?>
                    <li class="list-group-item">
                        <strong><?php echo $hesklang['recent_tickets']; ?></strong>
                        <?php foreach ($recentTickets as $recentTicket): ?>
                            <p style="margin: 0">
                                <i class="fa fa-circle" data-toggle="tooltip" data-placement="top"
                                   style="color: <?php echo $recentTicket['statusColor']; ?>"
                                   title="<?php echo sprintf($hesklang['current_status_colon'], $recentTicket['statusText']); ?>"></i>
                                <?php echo '<a href="admin_ticket.php?track='.$recentTicket['trackid'].'&amp;Refresh='.mt_rand(10000,99999).'">'.$recentTicket['trackid'].'</a>'; ?>
                            </p>
                        <?php endforeach; ?>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
    <div class="col-md-10">
        <?php
        /* This will handle error, success and notice messages */
        hesk_handle_messages();

        /* Do we need or have any canned responses? */
        $can_options = hesk_printCanned();
        
        echo hesk_getAdminButtons();
        ?>   
        <div class="blankSpace"></div>
        <!-- BEGIN TICKET HEAD -->
        <div class="table-bordered">
            <div class="row">
                <div class="col-md-12">
                    <h3>
                        <?php
                        if ($ticket['archive'])
                        {
	                        echo '<span class="fa fa-tag"></span> &nbsp;';
                        }
                        if ($ticket['locked'])
                        {
                            echo '<span class="fa fa-lock"></span>&nbsp;';
                        }
                        echo $ticket['subject'];
                        ?></h3>
                </div>
            </div>
            <div class="row">
                <div class="col-md-3 col-sm-12" style="padding-top: 6px">
                    <p><?php echo $hesklang['created_on'].': '.hesk_date($ticket['dt']); ?></p>
                </div>
                <div class="col-md-3 col-sm-12" style="padding-top: 6px">
                    <p><?php echo $hesklang['last_update'].': '.hesk_date($ticket['lastchange']); ?></p>
                </div>
                <div class="col-md-6 col-sm-12 close-ticket">
                    <?php
                            $random=rand(10000,99999);

                            $statusSql = 'SELECT `ID`, `ShortNameContentKey`, `IsStaffClosedOption`, `IsStaffReopenedStatus` FROM `'.hesk_dbEscape($hesk_settings['db_pfix']).'statuses` WHERE `IsStaffClosedOption` = 1 OR `IsStaffReopenedStatus` = 1';
                            $statusRs = hesk_dbQuery($statusSql);
                            $staffClosedOptionStatus = array();
                            $staffReopenedStatus = array();
                            while ($statusRow = $statusRs->fetch_assoc())
                            {
                                if ($statusRow['IsStaffReopenedStatus'] == 1)
                                {
                                    $staffReopenedStatus['ID'] = $statusRow['ID'];
                                } else
                                {
                                    $staffClosedOptionStatus['ID'] = $statusRow['ID'];
                                }
                            }

                            $isTicketClosedSql = 'SELECT `IsClosed`, `Closable` FROM `'.hesk_dbEscape($hesk_settings['db_pfix']).'statuses` WHERE `ID` = '.$ticket['status'];
                            $isTicketClosedRow = hesk_dbQuery($isTicketClosedSql)->fetch_assoc();
                            $isTicketClosed = $isTicketClosedRow['IsClosed'];
                            $isClosable = $isTicketClosedRow['Closable'] == 'yes' || $isTicketClosedRow['Closable'] == 'sonly';

                            echo '<div class="btn-group" role="group">';
                            if ($isTicketClosed == 0 && $isClosable) // Ticket is still open
                            {
                                echo '<a
		                        class="btn btn-default btn-sm" href="change_status.php?track='.$trackingID.'&amp;s='.$staffClosedOptionStatus['ID'].'&amp;Refresh='.$random.'&amp;token='.hesk_token_echo(0).'">
		                            <i class="fa fa-check-circle"></i> '.$hesklang['close_action'].'</a>';
                            }
                            elseif ($isTicketClosed == 1)
                            {
                                echo '<a
		                        class="btn btn-default btn-sm" href="change_status.php?track='.$trackingID.'&amp;s='.$staffReopenedStatus['ID'].'&amp;Refresh='.$random.'&amp;token='.hesk_token_echo(0).'">
		                            <i class="fa fa-check-circle"></i> '.$hesklang['open_action'].'</a>';
                            }

                            $strippedName = strip_tags($ticket['name']);
                            $strippedEmail = strip_tags($ticket['email']);
                            $linkText = 'new_ticket.php?name='.$strippedName.'&email='.$strippedEmail.'&catid='.$category['id'].'&priority='.$ticket['priority'];
                            foreach ($hesk_settings['custom_fields'] as $k=>$v)
                            {
                                if ($v['use'] == 1)
                                {

                                    if ($v['type'] == 'checkbox')
                                    {
                                        $value = str_replace('<br />', '-CHECKBOX-', $ticket[$k]);
                                    } else {
                                        $value = $ticket[$k];
                                    }
                                    $strippedCustomField = strip_tags($value);
                                    $linkText .= '&c_'.$k.'='.$strippedCustomField;
                                }
                            }

                            echo '<a class="btn btn-default btn-sm" href="'.$linkText.'">
                                      <i class="fa fa-plus"></i> '.$hesklang['create_based_on_contact'].'
                                  </a>';
                            echo '</div>';
                        ?>
                </div>
            </div>
            <div class="row medLowPriority">
                <?php

                   $priorityLanguages = array(
                        0 => $hesklang['critical'],
                        1 => $hesklang['high'],
                        2 => $hesklang['medium'],
                        3 => $hesklang['low']
                    );
                   $options = array();
                   for ($i = 0; $i < 4; $i++) {
                       $selected = $ticket['priority'] == $i ? 'selected' : '';
                       array_push($options, '<option value="'.$i.'" '.$selected.'>'.$priorityLanguages[$i].'</option>');
                   }

                   echo '<div class="ticket-cell-admin col-md-3 col-sm-12 ';
                   if ($ticket['priority'] == 0) {echo 'criticalPriority">';}
                   elseif ($ticket['priority'] == 1) {echo 'highPriority">';}
                   else {echo 'medLowPriority">';}

                   echo '<p class="ticketPropertyTitle">'.$hesklang['priority'].'</p>';

                   echo '<form style="margin-bottom:0;" id="changePriorityForm" action="priority.php" method="post">

                    <span style="white-space:nowrap;">
                    <select class="form-control" name="priority" onchange="document.getElementById(\'changePriorityForm\').submit();">';
                    echo implode('',$options);
                    echo '
                    </select>

                    <input type="submit" style="display: none" value="'.$hesklang['go'].'" /><input type="hidden" name="track" value="'.$trackingID.'" />
                    <input type="hidden" name="token" value="'.hesk_token_echo(0).'" />
                    </span>

                    </form>

                   </div>';

                echo '<div class="col-md-3 col-sm-12 ticket-cell-admin"><p class="ticketPropertyTitle">'.$hesklang['status'].'</p>'; 
                    $status_options = array();
                    $results = hesk_dbQuery("SELECT `ID`, `ShortNameContentKey` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."statuses`");
                    while ($row = $results->fetch_assoc())
                    {
                        $selected = $ticket['status'] == $row['ID'] ? 'selected' : '';
                        $status_options[$row['ID']] = '<option value="'.$row['ID'].'" '.$selected.'>'.$hesklang[$row['ShortNameContentKey']].'</option>';
                    }

                    echo '
                    <form role="form" id="changeStatusForm" style="margin-bottom:0;" action="change_status.php" method="post">
                        <span style="white-space:nowrap;">
                            <select class="form-control" onchange="document.getElementById(\'changeStatusForm\').submit();" name="s">
                                ' . implode('', $status_options) . '
                            </select>

                            <input type="submit" style="display:none;" value="'.$hesklang['go'].'" class="btn btn-default" /><input type="hidden" name="track" value="'.$trackingID.'" />
                            <input type="hidden" name="token" value="'.hesk_token_echo(0).'" />
                        </span>
                    </form>
                    </div>';
                echo '<div class="col-md-3 col-sm-12 ticket-cell-admin"><p class="ticketPropertyTitle">'.$hesklang['owner'].'</p>';

                        if (hesk_checkPermission('can_assign_others',0))
                        {
                            echo'
                            <form style="margin-bottom:0;" id="changeOwnerForm" action="assign_owner.php" method="post">
                            <span style="white-space:nowrap;">
                            <select class="form-control"  name="owner" onchange="document.getElementById(\'changeOwnerForm\').submit();">';
                            $selectedForUnassign = 'selected';
                            foreach ($admins as $k=>$v)
                            {
                                $selected = '';
                                if ($k == $ticket['owner']) {
                                    $selectedForUnassign = '';
                                    $selected = 'selected';
                                }
                                echo '<option value="'.$k.'" '.$selected.'>'.$v.'</option>';
                            }
                            echo '<option value="-1" '.$selectedForUnassign.'> &gt; '.$hesklang['unas'].' &lt; </option>';
                            echo '</select>
                            <input type="submit" style="display: none" value="'.$hesklang['go'].'" class="orangebutton" onmouseover="hesk_btn(this,\'orangebuttonover\');" onmouseout="hesk_btn(this,\'orangebutton\');" />
                            <input type="hidden" name="track" value="'.$trackingID.'" />
                            <input type="hidden" name="token" value="'.hesk_token_echo(0).'" />
                            </span>';
                        } else
                        {
                            echo '<p class="ticketPropertyText">';
                            echo isset($admins[$ticket['owner']]) ? $admins[$ticket['owner']] :
                            ($can_assign_self ? $hesklang['unas'].' [<a href="assign_owner.php?track='.$trackingID.'&amp;owner='.$_SESSION['id'].'&amp;token='.hesk_token_echo(0).'">'.$hesklang['asss'].'</a>]' : $hesklang['unas']);
                            echo '</p>';
                        }
                        echo '</form></div>';
                echo '<div class="col-md-3 col-sm-12 ticket-cell-admin"><p class="ticketPropertyTitle">'.$hesklang['category'].'</p>';
                        if ($can_change_cat)
                        {
                        echo '

                        <form style="margin-bottom:0;" id="changeCategory" action="move_category.php" method="post">

                            <span style="white-space:nowrap;">
                            <select name="category" class="form-control" onchange="document.getElementById(\'changeCategory\').submit();">
                            '.$categories_options.'
                            </select>

                            <input type="submit" style="display: none" value="'.$hesklang['go'].'" /><input type="hidden" name="track" value="'.$trackingID.'" />
                            <input type="hidden" name="token" value="'.hesk_token_echo(0).'" />
                            </span>

                        </form>';
                        } else
                        {
                            echo '<p class="ticketPropertyText">'.$category['name'].'</p>';
                        }
                        echo '</div>';
                    ?>
            </div>
        </div>
        <?php
        $res = hesk_dbQuery("SELECT t1.*, t2.`name` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."notes` AS t1 LEFT JOIN `".hesk_dbEscape($hesk_settings['db_pfix'])."users` AS t2 ON t1.`who` = t2.`id` WHERE `ticket`='".intval($ticket['id'])."' ORDER BY t1.`id` " . ($hesk_settings['new_top'] ? 'DESC' : 'ASC') );
        while ($note = hesk_dbFetchAssoc($res))
        {
            ?>
            <div class="row">
                <div class="col-md-12 alert-warning">
                    <div class="row" style="padding-top: 10px; padding-bottom: 10px">
                        <div class="col-md-8">
                            <p><i><?php echo $hesklang['noteby']; ?> <b><?php echo ($note['name'] ? $note['name'] : $hesklang['e_udel']); ?></b></i> - <?php echo hesk_date($note['dt'], true); ?></p>
                            <?php
                            // Message
                            echo $note['message'];

                            // Attachments
                            if ( $hesk_settings['attachments']['use'] && strlen($note['attachments']) )
                            {
                                echo strlen($note['message']) ? '<br /><br />' : '';

                                $att = explode(',', substr($note['attachments'], 0, -1) );
                                $num = count($att);
                                foreach ($att as $myatt)
                                {
                                    list($att_id, $att_name) = explode('#', $myatt);

                                    // Can edit and delete note (attachments)?
                                    if ($can_del_notes || $note['who'] == $_SESSION['id'])
                                    {
                                        // If this is the last attachment and no message, show "delete ticket" link
                                        if ($num == 1 && strlen($note['message']) == 0)
                                        {
                                            echo '<a href="admin_ticket.php?delnote='.$note['id'].'&amp;track='.$trackingID.'&amp;Refresh='.mt_rand(10000,99999).'&amp;token='.hesk_token_echo(0).'" onclick="return hesk_confirmExecute(\''.hesk_makeJsString($hesklang['pda']).'\');">
                                                    <i class="fa fa-times" style="font-size:16px;color:red;" data-toggle="tooltip" data-placement="top" data-original-title="'.$hesklang['dela'].'"></i>
                                                </a> ';
                                        }
                                        // Show "delete attachment" link
                                        else
                                        {
                                            echo '<a href="admin_ticket.php?delatt='.$att_id.'&amp;note='.$note['id'].'&amp;track='.$trackingID.'&amp;Refresh='.mt_rand(10000,99999).'&amp;token='.hesk_token_echo(0).'" onclick="return hesk_confirmExecute(\''.hesk_makeJsString($hesklang['pda']).'\');">
                                                    <i class="fa fa-times" style="font-size:16px;color:red;" data-toggle="tooltip" data-placement="top" data-original-title="'.$hesklang['dela'].'"></i>
                                                </a> ';
                                        }
                                    }

                                    echo '
                                        <a href="../download_attachment.php?att_id='.$att_id.'&amp;track='.$trackingID.'">
                                            <i class="fa fa-paperclip" style="font-size:16px;" data-toggle="tooltip" data-placement="top" data-original-title="'.$hesklang['dnl'].' '.$att_name.'"></i>
                                        </a>
                                        <a href="../download_attachment.php?att_id='.$att_id.'&amp;track='.$trackingID.'">'.$att_name.'</a><br />
                                    ';
                                }
                            }
                            ?>
                        </div>
                        <div class="col-md-4 text-right">
                            <?php if ($can_del_notes || $note['who'] == $_SESSION['id']) { ?>
                                <div class="btn-group" role="group">
                                    <a href="edit_note.php?track=<?php echo $trackingID; ?>&amp;Refresh=<?php echo mt_rand(10000,99999); ?>&amp;note=<?php echo $note['id']; ?>&amp;token=<?php hesk_token_echo(); ?>" class="btn btn-warning">
                                        <i class="fa fa-pencil"></i>&nbsp;<?php echo $hesklang['ednote']; ?>
                                    </a>
                                    <a href="admin_ticket.php?track=<?php echo $trackingID; ?>&amp;Refresh=<?php echo mt_rand(10000,99999); ?>&amp;delnote=<?php echo $note['id']; ?>&amp;token=<?php hesk_token_echo(); ?>" class="btn btn-danger">
                                        <i class="fa fa-times"></i>&nbsp;<?php echo $hesklang['delnote']; ?>
                                    </a>
                                </div>
                            <?php }?>
                        </div>
                    </div>
                </div>
            </div>
        <?php
        }
        ?>
        <div class="row">
            <div class="col-md-12">
                <b><i><?php echo $hesklang['notes']; ?>: </i></b>
                <?php
                if ($can_reply)
                {
                    ?>
                    &nbsp; <a href="Javascript:void(0)" onclick="Javascript:hesk_toggleLayerDisplay('notesform')"><i class="fa fa-plus"></i> <?php echo $hesklang['addnote']; ?></a>
                <?php
                }
                ?>

                <div id="notesform" style="display:<?php echo isset($_SESSION['note_message']) ? 'block' : 'none'; ?>">
                    <form method="post" action="admin_ticket.php" style="margin:0px; padding:0px;" enctype="multipart/form-data">
                        <textarea class="form-control" name="notemsg" rows="6" cols="60"><?php echo isset($_SESSION['note_message']) ? stripslashes(hesk_input($_SESSION['note_message'])) : ''; ?></textarea><br />
                        <?php
                        // attachments
                        if ($hesk_settings['attachments']['use'])
                        {
                            echo '<br />';
                            for ($i=1;$i<=$hesk_settings['attachments']['max_number'];$i++)
                            {
                                echo '<input type="file" name="attachment['.$i.']" size="50" /><br />';
                            }
                            echo '<br />';
                        }
                        ?>
                        <input class="btn btn-default" type="submit" value="<?php echo $hesklang['s']; ?>"  /><input type="hidden" name="track" value="<?php echo $trackingID; ?>" />
                        <i><?php echo $hesklang['nhid']; ?></i>
                        <input type="hidden" name="token" value="<?php hesk_token_echo(); ?>" />
                    </form>
                </div>
            </div>
        </div>
        <div class="blankSpace"></div>
        <!-- END TICKET HEAD -->

        <?php
        /* Reply form on top? */
        if ($can_reply && $hesk_settings['reply_top'] == 1)
        {
	        hesk_printReplyForm();
            echo '<br />';
        }
        ?>

        <!-- START TICKET REPLIES -->

        <?php
		    if ($hesk_settings['new_top'])
            {
        	    $i = hesk_printTicketReplies() ? 0 : 1;
            }
            else
            {
        	    $i = 1;
            }

            /* Make sure original message is in correct color if newest are on top */
            $color = 'class="ticketMessageContainer"';
		?>
        <div class="row ticketMessageContainer">
            <div class="col-md-3 col-xs-12">
                <div class="ticketName"><?php echo $ticket['name']; ?></div>
                <div class="ticketEmail">
                    <?php
                    if ($can_ban_emails && !empty($ticket['email']))
                    {
                        if ( $email_id = hesk_isBannedEmail($ticket['email']) )
                        {
                            if ($can_unban_emails)
                            {
                                echo '<a href="banned_emails.php?a=unban&amp;track='.$trackingID.'&amp;id='.intval($email_id).'&amp;token='.hesk_token_echo(0).'">
                                        <i class="fa fa-ban" style="font-size:16px;color:red" data-toggle="tooltip" data-placement="top" data-original-title="'.$hesklang['eisban'].' '.$hesklang['click_unban'].'"></i>
                                    </a> ';
                            }
                            else
                            {
                                echo '<i class="fa fa-ban" style="font-size:16px;color:red" data-toggle="tooltip" data-placement="top" data-original-title="'.$hesklang['eisban'].'"></i>';
                            }
                        }
                        else
                        {
                            echo '<a href="banned_emails.php?a=ban&amp;track='.$trackingID.'&amp;email='.urlencode($ticket['email']).'&amp;token='.hesk_token_echo(0).'">
                                    <i class="fa fa-ban" style="font-size:16px;color:grey" data-toggle="tooltip" data-placement="top" data-original-title="'.$hesklang['savebanemail'].'"></i>
                                </a> ';
                        }
                    }
                    ?><a href="mailto:<?php echo $ticket['email']; ?>"><?php echo $ticket['email']; ?></a>
                </div>
                <div class="ticketEmail"><?php echo $hesklang['ip']; ?>:
                    <?php

                    // Format IP for lookup
                    if ($ticket['ip'] == 'Unknown' || $ticket['ip'] == $hesklang['unknown'])
                    {
                        echo $hesklang['unknown'];
                    }
                    else
                    {
                        if ($can_ban_ips)
                        {
                            if ( $ip_id = hesk_isBannedIP($ticket['ip']) )
                            {
                                if ($can_unban_ips)
                                {
                                    echo '<a href="banned_ips.php?a=unban&amp;track='.$trackingID.'&amp;id='.intval($ip_id).'&amp;token='.hesk_token_echo(0).'">
                                            <i class="fa fa-ban" style="font-size:16px;color:red" data-toggle="tooltip" data-placement="top" data-original-title="'.$hesklang['ipisban'].' '.$hesklang['click_unban'].'"></i>
                                        </a> ';
                                }
                                else
                                {
                                    echo '<i class="fa fa-ban" style="font-size:16px;color:red" data-toggle="tooltip" data-placement="top" data-original-title="'.$hesklang['ipisban'].'"></i>';
                                }
                            }
                            else
                            {
                                echo '<a href="banned_ips.php?a=ban&amp;track='.$trackingID.'&amp;ip='.urlencode($ticket['ip']).'&amp;token='.hesk_token_echo(0).'">
                                        <i class="fa fa-ban" style="font-size:16px;color:grey" data-toggle="tooltip" data-placement="top" data-original-title="'.$hesklang['savebanip'].'"></i>
                                    </a> ';
                            }
                        }

                        echo '<a href="../ip_whois.php?ip=' . urlencode($ticket['ip']) . '">' . $ticket['ip'] . '</a>';
                    }
                    ?>
                </div>
            </div>
            <div class="col-md-9 col-xs-12 pushMarginLeft">
                <div class="ticketMessageTop withBorder">
                    <!-- Action Buttons -->
                    <?php echo hesk_getAdminButtonsInTicket(0, $i); ?>
                    
                    <!-- Date -->
                    <p><br/><?php echo $hesklang['date']; ?>: <?php echo hesk_date($ticket['dt'], true); ?>
                    
                    <!-- Custom Fields Before Message -->
                        <?php
		                    foreach ($hesk_settings['custom_fields'] as $k=>$v)
		                    {
			                    if ($v['use'] && $v['place']==0)
		                        {
                                    if ($modsForHesk_settings['custom_field_setting'])
                                    {
                                        $v['name'] = $hesklang[$v['name']];
                                    }

		                            echo '<p>'.$v['name'].': ';
                                    if ($v['type'] == 'date' && !empty($ticket[$k]))
                                    {
                                        $dt = date('Y-m-d h:i:s', $ticket[$k]);
                                        echo hesk_dateToString($dt, 0);
                                    } else
                                    {
                                        echo $ticket[$k];
                                    }
				                    echo '</p>';
		                        }
		                    }
		                ?>
                    </div>
                <div class="ticketMessageBottom">
                     <!-- Message -->
                    <p><b><?php echo $hesklang['message']; ?>:</b></p>
		            <p class="message"><?php echo $ticket['message']; ?><br />&nbsp;</p>
                </div>
                <div class="ticketMessageTop">
                         <!-- Custom Fields after Message -->
                         <?php
		                    foreach ($hesk_settings['custom_fields'] as $k=>$v)
		                    {
			                    if ($v['use'] && $v['place'])
		                        {
                                    if ($modsForHesk_settings['custom_field_setting'])
                                    {
                                        $v['name'] = $hesklang[$v['name']];
                                    }

                                    echo '<p>'.$v['name'].': ';
                                    if ($v['type'] == 'date' && !empty($ticket[$k]))
                                    {
                                        $dt = date('Y-m-d h:i:s', $ticket[$k]);
                                        echo hesk_dateToString($dt, 0);
                                    } else
                                    {
                                        echo $ticket[$k];
                                    }
                                    echo '</p>';
		                        }
		                    }
	    	                /* Attachments */
    		                hesk_listAttachments($ticket['attachments']);

                         // Show suggested KB articles
                         if ($hesk_settings['kb_enable'] && $hesk_settings['kb_recommendanswers'] && strlen($ticket['articles']) )
                         {
                             $suggested = array();
                             $suggested_list = '';

                             // Get article info from the database
                             $articles = hesk_dbQuery("SELECT `id`,`subject` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."kb_articles` WHERE `id` IN (".preg_replace('/[^0-9\,]/', '', $ticket['articles']).")");
                             while ($article=hesk_dbFetchAssoc($articles))
                             {
                                 $suggested[$article['id']] = '<a href="../knowledgebase.php?article='.$article['id'].'">'.$article['subject'].'</a><br />';
                             }

                             // Loop through the IDs to preserve the order they were suggested in
                             $articles = explode(',', $ticket['articles']);
                             foreach ($articles as $article)
                             {
                                 if ( isset($suggested[$article]) )
                                 {
                                     $suggested_list .= $suggested[$article];
                                 }
                             }

                             // Finally print suggested articles
                             if ( strlen($suggested_list) )
                             {
                                 $suggested_list = '<hr /><i>'.$hesklang['taws'].'</i><br />' . $suggested_list . '&nbsp;';
                                 echo $_SESSION['show_suggested'] ? $suggested_list : '<a href="Javascript:void(0)" onclick="Javascript:hesk_toggleLayerDisplay(\'suggested_articles\')">'.$hesklang['sska'].'</a><span id="suggested_articles" style="display:none">'.$suggested_list.'</span>';
                             }
                         }
		                ?>
                 </div>
            </div>
        </div>
        <?php
		if ( ! $hesk_settings['new_top'])
        {
        	hesk_printTicketReplies();
        }
		?>

        <?php
        /* Reply form on bottom? */
        if ($can_reply && ! $hesk_settings['reply_top'])
        {
	        hesk_printReplyForm();
        } 
        
        /* Display ticket history */
        if (strlen($ticket['history']))
        {
        ?>
        <h3><?php echo $hesklang['thist']; ?></h3>
        <div class="footerWithBorder blankSpace"></div>
        <ul><?php echo $ticket['history']; ?></ul>
        <?php }
        ?>
    </div>     
</div>
<?php


/* Clear unneeded session variables */
hesk_cleanSessionVars('ticket_message');
hesk_cleanSessionVars('time_worked');
hesk_cleanSessionVars('note_message');

require_once(HESK_PATH . 'inc/footer.inc.php');


/*** START FUNCTIONS ***/


function hesk_listAttachments($attachments='', $reply=0, $white=1)
{
	global $hesk_settings, $hesklang, $trackingID, $can_edit, $can_delete;

	/* Attachments disabled or not available */
	if ( ! $hesk_settings['attachments']['use'] || ! strlen($attachments) )
    {
    	return false;
    }

	/* List attachments */
	echo '<p><b>'.$hesklang['attachments'].':</b></p><br />';
	$att=explode(',',substr($attachments, 0, -1));
    echo '<div class="table-responsive">';
    echo '<table class="table table-striped attachment-table">';
    echo '<thead><tr><th>&nbsp;</th><th>'.$hesklang['file_name'].'</th><th>'.$hesklang['action'].'</th></tr></thead>';
    echo '<tbody>';
	foreach ($att as $myatt)
	{

        list($att_id, $att_name) = explode('#', $myatt);
        $fileparts = pathinfo($att_name);
        $fontAwesomeIcon = hesk_getFontAwesomeIconForFileExtension($fileparts['extension']);
        echo '
        <tr>
            <td>';
                //-- File is an image
                if ($fontAwesomeIcon == 'fa fa-file-image-o') {

                    //-- Get the actual image location and display a thumbnail. It will be linked to a modal to view a larger size.
                    $path = hesk_getSavedNameUrlForAttachment($att_id);
                    if ($path == '') {
                        echo '<i class="fa fa-ban fa-4x" data-toggle="tooltip" title="'.$hesklang['attachment_removed'].'"></i>';
                    } else {
                        echo '<span data-toggle="tooltip" title="'.$hesklang['click_to_preview'].'">
                                  <img src="'.$path.'" alt="'.$hesklang['image'].'" data-toggle="modal" data-target="#modal-attachment-'.$att_id.'">
                              </span>';
                        echo '<div class="modal fade" id="modal-attachment-'.$att_id.'" tabindex="-1" role="dialog" aria-hidden="true">
                                  <div class="modal-dialog">
                                      <div class="modal-content">
                                          <div class="modal-header">
                                              <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                              <h4 class="modal-title" id="myModalLabel">'.$att_name.'</h4>
                                          </div>
                                          <div class="modal-body">
                                              <img class="img-responsive" src="'.$path.'" alt="'.$hesklang['image'].'">
                                          </div>
                                          <div class="modal-footer">
                                              <button type="button" class="btn btn-default" data-dismiss="modal">'.$hesklang['close_modal'].'</button>
                                              <a href="../download_attachment.php?att_id='.$att_id.'&amp;track='.$trackingID.'" class="btn btn-success">'.$hesklang['dnl'].'</a>
                                          </div>
                                      </div>
                                  </div>
                              </div>';
                    }
                } else {
                    //-- Display the FontAwesome icon in the panel's body
                    echo '<i class="'.$fontAwesomeIcon.' fa-4x"></i>';
                }
        echo'
            </td>
            <td>
                <p>'.$att_name.'</p>
            </td>
            <td>
                <div class="btn-group">';
                /* Can edit and delete tickets? */
                if ($can_edit && $can_delete)
                {
                    echo '<a class="btn btn-danger" href="admin_ticket.php?delatt='.$att_id.'&amp;reply='.$reply.'&amp;track='.$trackingID.'&amp;Refresh='.mt_rand(10000,99999).'&amp;token='.hesk_token_echo(0).'" onclick="return hesk_confirmExecute(\''.hesk_makeJsString($hesklang['pda']).'\');" data-toggle="tooltip" data-placement="top" data-original-title="'.$hesklang['delete'].'"><i class="fa fa-times"></i></a> ';
                }
                echo '<a class="btn btn-success" href="../download_attachment.php?att_id='.$att_id.'&amp;track='.$trackingID.'"
                        data-toggle="tooltip" data-placement="top" data-original-title="'.$hesklang['dnl'].'">
                            <i class="fa fa-arrow-down"></i>
                      </a>';
        echo   '</div>
            </td>
        </tr>
        ';
	}
    echo '</tbody></table></div>';

    return true;
} // End hesk_listAttachments()

function hesk_getSavedNameUrlForAttachment($att_id)
{
    global $hesk_settings;

    //-- Call the DB for the attachment
    $nameRS = hesk_dbQuery("SELECT `saved_name` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."attachments` WHERE `att_id` = ".hesk_dbEscape($att_id));
    $name = hesk_dbFetchAssoc($nameRS);
    $realpath = '../'.$hesk_settings['attach_dir'] . '/' . $name['saved_name'];

    return !file_exists($realpath) ? '' : $realpath;
}

function hesk_getFontAwesomeIconForFileExtension($fileExtension)
{
    $imageExtensions = array('jpg','jpeg','png','bmp','gif');

    //-- Word, Excel, and PPT file extensions: http://en.wikipedia.org/wiki/List_of_Microsoft_Office_filename_extensions
    $wordFileExtensions = array('doc','docx','dotm','dot','docm','docb');
    $excelFileExtensions = array('xls','xlt','xlm','xlsx','xlsm','xltx','xltm');
    $pptFileExtensions = array('ppt','pot','pps','pptx','pptm','potx','potm','ppsx','ppsm','sldx','sldm');

    //-- File archive extensions: http://en.wikipedia.org/wiki/List_of_archive_formats
    $archiveFileExtensions = array('tar','gz','zip','rar','7z','bz2','lz','lzma','tgz','tbz2','zipx');

    //-- Audio file extensions: http://en.wikipedia.org/wiki/Audio_file_format#List_of_formats
    $audioFileExtensions = array('3gp','act','aiff','aac','amr','au','awb','dct','dss','dvf','flac','gsm','iklax','ivs','m4a','m4p','mmf','mp3','mpc','msv','ogg','oga','opus','ra','rm','raw','tta','vox','wav','wma','wv');

    //-- Video file extensions: http://en.wikipedia.org/wiki/Video_file_format#List_of_video_file_formats
    $videoFileExtensions = array('webm','mkv','flv','drc','mng','avi','mov','qt','wmv','yuv','rm','rmvb','asf','mp4','m4p','m4v','mpg','mp2','mpeg','mpe','mpv','m2v','svi','3gp','3g2','mxf','roq','nsv');

    //-- The only one I know of :D
    $pdfFileExtensions = array('pdf');

    $textFileExtensions = array('txt');

    $icon = 'fa fa-file-';
    $fileExtension = strtolower($fileExtension);
    if (in_array($fileExtension, $imageExtensions)) {
        $icon.='image-o';
    } elseif (in_array($fileExtension, $wordFileExtensions)) {
        $icon.='word-o';
    } elseif (in_array($fileExtension, $excelFileExtensions)) {
        $icon.='excel-o';
    } elseif (in_array($fileExtension, $pptFileExtensions)) {
        $icon.='powerpoint-o';
    } elseif (in_array($fileExtension, $archiveFileExtensions)) {
        $icon.='archive-o';
    } elseif (in_array($fileExtension, $audioFileExtensions)) {
        $icon.='audio-o';
    } elseif (in_array($fileExtension, $videoFileExtensions)) {
        $icon.='video-o';
    } elseif (in_array($fileExtension, $pdfFileExtensions)) {
        $icon.='pdf-o';
    } elseif (in_array($fileExtension, $textFileExtensions)) {
        $icon.='text-o';
    } else {
        $icon.='o';
    }
    return $icon;
}


function hesk_getAdminButtons($reply=0,$white=1)
{
	global $hesk_settings, $hesklang, $ticket, $reply, $trackingID, $can_edit, $can_archive, $can_delete;

	$options = '<div class="btn-group" style="width: 100%">';

    /* Style and mousover/mousout */
    $tmp = $white ? 'White' : 'Blue';
    $style = 'class="option'.$tmp.'OFF" onmouseover="this.className=\'option'.$tmp.'ON\'" onmouseout="this.className=\'option'.$tmp.'OFF\'"';

    /* Lock ticket button */
	if ( /* ! $reply && */ $can_edit)
	{
		if ($ticket['locked'])
		{
			$des = $hesklang['tul'] . ' - ' . $hesklang['isloc'];
            $options .= '<a class="btn btn-default" href="lock.php?track='.$trackingID.'&amp;locked=0&amp;Refresh='.mt_rand(10000,99999).'&amp;token='.hesk_token_echo(0).'"><i class="fa fa-unlock"></i> '.$hesklang['tul'].'</a> ';
		}
		else
		{
			$des = $hesklang['tlo'] . ' - ' . $hesklang['isloc'];
            $options .= '<a class="btn btn-default" href="lock.php?track='.$trackingID.'&amp;locked=1&amp;Refresh='.mt_rand(10000,99999).'&amp;token='.hesk_token_echo(0).'"><i class="fa fa-lock"></i> '.$hesklang['tlo'].'</a> ';
		}
	}

	/* Tag ticket button */
	if ( /* ! $reply && */ $can_archive)
	{
		if ($ticket['archive'])
		{
        	$options .= '<a class="btn btn-default" href="archive.php?track='.$trackingID.'&amp;archived=0&amp;Refresh='.mt_rand(10000,99999).'&amp;token='.hesk_token_echo(0).'"><i class="fa fa-tag"></i>'.$hesklang['remove_archive'].'</a> ';
		}
		else
		{
        	$options .= '<a class="btn btn-default" href="archive.php?track='.$trackingID.'&amp;archived=1&amp;Refresh='.mt_rand(10000,99999).'&amp;token='.hesk_token_echo(0).'"><i class="fa fa-tag"></i> '.$hesklang['add_archive'].'</a> ';
		}
	}

	/* Import to knowledgebase button */
	if ($hesk_settings['kb_enable'] && hesk_checkPermission('can_man_kb',0))
	{
		$options .= '<a class="btn btn-default" href="manage_knowledgebase.php?a=import_article&amp;track='.$trackingID.'"><i class="fa fa-lightbulb-o"></i> '.$hesklang['import_kb'].'</a> ';
	}

	/* Print ticket button */
    $options .= '<a class="btn btn-default" href="../print.php?track='.$trackingID.'"><i class="fa fa-print"></i> '.$hesklang['printer_friendly'].'</a> ';

	/* Edit post */
	if ($can_edit)
	{
    	$tmp = $reply ? '&amp;reply='.$reply['id'] : '';
		$options .= '<a class="btn btn-default" href="edit_post.php?track='.$trackingID.$tmp.'"><i class="fa fa-pencil"></i> '.$hesklang['edtt'].'</a> ';
	}


	/* Delete ticket */
	if ($can_delete)
	{
		if ($reply)
		{
			$url = 'admin_ticket.php';
			$tmp = 'delete_post='.$reply['id'];
			$img = 'delete.png';
			$txt = $hesklang['delt'];
		}
		else
		{
			$url = 'delete_tickets.php';
			$tmp = 'delete_ticket=1';
			$img = 'delete_ticket.png';
			$txt = $hesklang['dele'];
		}
		$options .= '<a class="btn btn-default" href="'.$url.'?track='.$trackingID.'&amp;'.$tmp.'&amp;Refresh='.mt_rand(10000,99999).'&amp;token='.hesk_token_echo(0).'" onclick="return hesk_confirmExecute(\''.hesk_makeJsString($txt).'?\');"><i class="fa fa-ban"></i> '.$txt.'</a> ';
	}

    /* Return generated HTML */
    $options .= '</div>';
    return $options;

} // END hesk_getAdminButtons()

function hesk_getAdminButtonsInTicket($reply=0,$white=1)
{
	global $hesk_settings, $hesklang, $ticket, $reply, $trackingID, $can_edit, $can_archive, $can_delete;

	$options = '<div class="btn-group text-right" style="width: 70%; margin-left: auto; margin-right: auto">';

    /* Style and mousover/mousout */
    $tmp = $white ? 'White' : 'Blue';
    $style = 'class="option'.$tmp.'OFF" onmouseover="this.className=\'option'.$tmp.'ON\'" onmouseout="this.className=\'option'.$tmp.'OFF\'"';

	/* Edit post */
	if ($can_edit)
	{
    	$tmp = $reply ? '&amp;reply='.$reply['id'] : '';
		$options .= '<a class="btn btn-default" href="edit_post.php?track='.$trackingID.$tmp.'"><i class="fa fa-pencil"></i> '.$hesklang['edtt'].'</a> ';
	}


	/* Delete ticket */
	if ($can_delete)
	{
		if ($reply)
		{
			$url = 'admin_ticket.php';
			$tmp = 'delete_post='.$reply['id'];
			$img = 'delete.png';
			$txt = $hesklang['delt'];
		}
		else
		{
			$url = 'delete_tickets.php';
			$tmp = 'delete_ticket=1';
			$img = 'delete_ticket.png';
			$txt = $hesklang['dele'];
		}
		$options .= '<a class="btn btn-default" href="'.$url.'?track='.$trackingID.'&amp;'.$tmp.'&amp;Refresh='.mt_rand(10000,99999).'&amp;token='.hesk_token_echo(0).'" onclick="return hesk_confirmExecute(\''.$txt.'?\');"><i class="fa fa-ban"></i> '.$txt.'</a> ';
	}

    /* Return generated HTML */
    $options .= '</div>';
    return $options;

} // END hesk_getAdminButtonsInTicket()


function print_form()
{
	global $hesk_settings, $hesklang;
    global $trackingID;

	/* Print header */
	require_once(HESK_PATH . 'inc/header.inc.php');

	/* Print admin navigation */
	require_once(HESK_PATH . 'inc/show_admin_nav.inc.php');
	?>

	</td>
	</tr>
	<tr>
	<td>

    &nbsp;<br />

	<?php
	/* This will handle error, success and notice messages */
	hesk_handle_messages();
	?>

	<div align="center">
	<table border="0" cellspacing="0" cellpadding="0" width="50%">
	<tr>
		<td width="7" height="7"><img src="../img/roundcornerslt.jpg" width="7" height="7" alt="" /></td>
		<td class="roundcornerstop"></td>
		<td><img src="../img/roundcornersrt.jpg" width="7" height="7" alt="" /></td>
	</tr>
	<tr>
		<td class="roundcornersleft">&nbsp;</td>
		<td>

	        <form action="admin_ticket.php" method="get">

	        <table width="100%" border="0" cellspacing="0" cellpadding="0">
	        <tr>
	                <td width="1"><img src="../img/existingticket.png" alt="" width="60" height="60" /></td>
	                <td>
	                <p><b><?php echo $hesklang['view_existing']; ?></a></b></p>
	                </td>
	        </tr>
	        <tr>
	                <td width="1">&nbsp;</td>
	                <td>&nbsp;</td>
	        </tr>
	        <tr>
	                <td width="1">&nbsp;</td>
	                <td>
	                <?php echo $hesklang['ticket_trackID']; ?>: <br /><input type="text" name="track" maxlength="20" size="35" value="<?php echo $trackingID; ?>" /><br />&nbsp;
	                </td>
	        </tr>
	        <tr>
	                <td width="1">&nbsp;</td>
	                <td><input type="submit" value="<?php echo $hesklang['view_ticket']; ?>" class="orangebutton" onmouseover="hesk_btn(this,'orangebuttonover');" onmouseout="hesk_btn(this,'orangebutton');" /><input type="hidden" name="Refresh" value="<?php echo rand(10000,99999); ?>"></td>
	        </tr>
	        </table>

	        </form>

		</td>
		<td class="roundcornersright">&nbsp;</td>
	</tr>
	<tr>
		<td><img src="../img/roundcornerslb.jpg" width="7" height="7" alt="" /></td>
		<td class="roundcornersbottom"></td>
		<td width="7" height="7"><img src="../img/roundcornersrb.jpg" width="7" height="7" alt="" /></td>
	</tr>
	</table>
	</div>

	<p>&nbsp;</p>
	<?php
	require_once(HESK_PATH . 'inc/footer.inc.php');
	exit();
} // End print_form()


function hesk_printTicketReplies() {
	global $hesklang, $hesk_settings, $result, $reply;

	$i = $hesk_settings['new_top'] ? 0 : 1;

    if ($reply === false)
    {
        return $i;
	}

	while ($reply = hesk_dbFetchAssoc($result))
	{
		$color = 'class="ticketMessageContainer"';

		$reply['dt'] = hesk_date($reply['dt'], true);
		?>
        <div class="row ticketMessageContainer">
            <div class="col-md-3 col-xs-12">
                <div class="ticketName"><?php echo $reply['name']; ?></div>
            </div>
            <div class="col-md-9 col-xs-12 pushMarginLeft">
                <div class="ticketMessageTop withBorder">
                    <?php echo hesk_getAdminButtonsInTicket(); ?>
                    <div class="blankSpace"></div>
                    <p><?php echo $hesklang['date']; ?>: <?php echo $reply['dt']; ?></p> 
                </div>
                <div class="ticketMessageBottom">
                    <p><b><?php echo $hesklang['message']; ?>:</b></p>
			        <p><?php echo $reply['message']; ?></p> 
                </div>
                <div class="ticketMessageTop pushMargin">
                     <?php hesk_listAttachments($reply['attachments'],$reply['id']);
                        /* Staff rating */
			            if ($hesk_settings['rating'] && $reply['staffid'])
			            {
				            if ($reply['rating']==1)
				            {
					            echo '<p class="rate">'.$hesklang['rnh'].'</p>';
				            }
				            elseif ($reply['rating']==5)
				            {
					            echo '<p class="rate">'.$hesklang['rh'].'</p>';
				            }
			            }

                        /* Show "unread reply" message? */
			            if ($reply['staffid'] && ! $reply['read'])
                        {
            	            echo '<p class="rate">'.$hesklang['unread'].'</p>';
                        }
                    ?>
                </div>
            </div>
        </div>
        <?php
	}

    return $i;

} // End hesk_printTicketReplies()


function hesk_printReplyForm() {
	global $hesklang, $hesk_settings, $ticket, $admins, $can_options, $options, $can_assign_self;
?>
<!-- START REPLY FORM -->

        <h3 class="text-left"><?php echo $hesklang['add_reply']; ?></h3>
        <div class="footerWithBorder"></div>
        <div class="blankSpace"></div>
        
        <form role="form" class="form-horizontal" method="post" action="admin_reply_ticket.php" enctype="multipart/form-data" name="form1" onsubmit="javascript:force_stop();return true;">
            <?php

            /* Ticket assigned to someone else? */
            if ($ticket['owner'] && $ticket['owner'] != $_SESSION['id'] && isset($admins[$ticket['owner']]) )
            {
    	        hesk_show_notice($hesklang['nyt'] . ' ' . $admins[$ticket['owner']]);
            }

            /* Ticket locked? */
            if ($ticket['locked'])
            {
    	        hesk_show_notice($hesklang['tislock']);
            }

            // Track time worked?
            if ($hesk_settings['time_worked']) {
                ?>

                <div class="form-group">
                    <label for="time_worked" class="col-sm-3 control-label"><?php echo $hesklang['ts']; ?>:</label>

                    <div class="col-sm-6">
                        <input type="text" class="form-control" name="time_worked" id="time_worked" size="10"
                               value="<?php echo(isset($_SESSION['time_worked']) ? hesk_getTime($_SESSION['time_worked']) : '00:00:00'); ?>"/>
                    </div>
                    <div class="col-sm-3 text-right">
                        <input type="button" class="btn btn-success" onclick="ss()" id="startb"
                               value="<?php echo $hesklang['start']; ?>"/>
                        <input type="button" class="btn btn-danger" onclick="r()"
                               value="<?php echo $hesklang['reset']; ?>"/>
                    </div>
                </div>
            <?php
            }
            /* Do we have any canned responses? */
            if (strlen($can_options))
            {
            ?>
            <div class="form-group">
                <label for="saved_replies" class="col-sm-3 control-label"><?php echo $hesklang['saved_replies']; ?>:</label>
                <div class="col-sm-9">
                    <label><input type="radio" name="mode" id="modeadd" value="1" checked="checked" /> <?php echo $hesklang['madd']; ?></label><br />
                    <label><input type="radio" name="mode" id="moderep" value="0" /> <?php echo $hesklang['mrep']; ?></label>
                   <select class="form-control" name="saved_replies" onchange="setMessage(this.value)">
		                <option value="0"> - <?php echo $hesklang['select_empty']; ?> - </option>
		                <?php echo $can_options; ?>
		            </select>
                </div>     
            </div>
            <?php
            }
            ?>
            <div class="form-group">
                <label for="message" class="col-sm-3 control-label"><?php echo $hesklang['message']; ?>: <font class="important">*</font></label>
                <div class="col-sm-9">
                    <span id="HeskMsg">
                        <textarea class="form-control" name="message" id="message" rows="12" placeholder="<?php echo htmlspecialchars($hesklang['message']); ?>" cols="72"><?php

                            // Do we have any message stored in session?
                            if ( isset($_SESSION['ticket_message']) )
                            {
                                echo stripslashes( hesk_input( $_SESSION['ticket_message'] ) );
                            }
                            // Perhaps a message stored in reply drafts?
                            else
                            {
                                $res = hesk_dbQuery("SELECT `message` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."reply_drafts` WHERE `owner`=".intval($_SESSION['id'])." AND `ticket`=".intval($ticket['id'])." LIMIT 1");
                                if (hesk_dbNumRows($res) == 1)
                                {
                                    echo hesk_dbResult($res);
                                }
                            }

                            ?></textarea></span>
                </div>
            </div>
            <?php
	        /* attachments */
	        if ($hesk_settings['attachments']['use'])
            {
	        ?>
            <div class="form-group">
                <label for="attachments" class="col-sm-3 control-label"><?php echo $hesklang['attachments']; ?>:</label>
                <div class="col-sm-9">
                    <?php for ($i=1;$i<=$hesk_settings['attachments']['max_number'];$i++)
		            {
			            echo '<input type="file" name="attachment['.$i.']" size="50" /><br />';
		            }

                        echo '<a href="Javascript:void(0)" onclick="Javascript:hesk_window(\'../file_limits.php\',250,500);return false;">' . $hesklang['ful'] . '</a>';
		            ?>
                </div>     
            </div>
            <?php
	        }
	        ?>
            <div class="form-group">
                <label for="options" class="col-sm-3 control-label"><?php echo $hesklang['addop']; ?>:</label>
                <div class="col-sm-9">
                   <?php
                    if ($ticket['owner'] != $_SESSION['id'] && $can_assign_self)
                    {
		                if (empty($ticket['owner']))
		                {
			                echo '<label><input type="checkbox" name="assign_self" value="1" checked="checked" /> <b>'.$hesklang['asss2'].'</b></label><br />';
		                }
		                else
		                {
			                echo '<label><input type="checkbox" name="assign_self" value="1" /> '.$hesklang['asss2'].'</label><br />';
		                }
                    }

                   $statusSql = 'SELECT `ID` FROM `'.hesk_dbEscape($hesk_settings['db_pfix']).'statuses` WHERE `IsStaffClosedOption` = 1';
                   $statusRow = hesk_dbQuery($statusSql)->fetch_assoc();
                   $staffClosedOptionStatus = array();
                   $staffClosedOptionStatus['ID'] = $statusRow['ID'];

	                ?>
	                <div class="form-inline">
                        <label>
                            <input type="checkbox" name="set_priority" value="1" /> <?php echo $hesklang['change_priority']; ?>
                        </label>
	                    <select class="form-control" name="priority">
	                        <?php echo implode('',$options); ?>
	                    </select>
                    </div>
                    <br />
	                <label>
                        <input type="checkbox" name="signature" value="1" checked="checked" /> <?php echo $hesklang['attach_sign']; ?>
                    </label>
	                (<a href="profile.php"><?php echo $hesklang['profile_settings']; ?></a>)
                    <br />
                    <label>
                        <input type="checkbox" name="no_notify" value="1" <?php echo ($_SESSION['notify_customer_reply'] && !empty($ticket['email'])) ? '' : 'checked="checked" '; ?> <?php if (empty($ticket['email'])) { echo 'disabled'; } ?>> <?php echo $hesklang['dsen']; ?>
                    </label><br/><br/>
                    <?php if (empty($ticket['email'])) {
                        echo '<input type="hidden" name="no_notify" value="1">';
                    } ?>
                    <input type="hidden" name="orig_id" value="<?php echo $ticket['id']; ?>" />
                    <input type="hidden" name="token" value="<?php hesk_token_echo(); ?>" />
                    <div class="btn-group">
                        <input class="btn btn-primary" type="submit" value="<?php echo $hesklang['submit_reply']; ?>">
                        <button type="button" class="btn btn-primary dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
                            <span class="caret"></span>
                            <span class="sr-only">Toggle Dropdown</span>
                        </button>
                        <ul class="dropdown-menu" role="menu">
                            <li><a>
                                <button class="dropdown-submit" type="submit" name="submit_as_customer">
                                    <?php echo $hesklang['sasc']; ?>
                                </button>
                            </a></li>
                            <li class="divider"></li>
                            <?php
                            $allStatusesRs = hesk_dbQuery('SELECT `ID`, `ShortNameContentKey`, `TextColor` FROM `'.hesk_dbEscape($hesk_settings['db_pfix']).'statuses`');
                            $statuses = array();
                            while ($row = hesk_dbFetchAssoc($allStatusesRs)) {
                                array_push($statuses, $row);
                            }

                            foreach ($statuses as $status) {
                                echo '<li><a>
                                        <button class="dropdown-submit" type="submit" name="submit_as_status" value="'.$status['ID'].'"">
                                            '.$hesklang['submit_reply'].' '.$hesklang['and_change_status_to'].' <b>
                                            <span style="color:'.$status['TextColor'].'">'.$hesklang[$status['ShortNameContentKey']].'</span></b>
                                        </button>
                                    </a></li>';
                            }
                            ?>
                        </ul>
                    </div>
                    <input class="btn btn-default" type="submit" name="save_reply" value="<?php echo $hesklang['sacl']; ?>"
                </div>
            </div></div>
        </form>

<!-- END REPLY FORM -->
<?php
} // End hesk_printReplyForm()


function hesk_printCanned()
{
	global $hesklang, $hesk_settings, $can_reply, $ticket;

	/* Can user reply to tickets? */
	if ( ! $can_reply)
    {
    	return '';
    }

	/* Get canned replies from the database */
	$res = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."std_replies` ORDER BY `reply_order` ASC");

	/* If no canned replies return empty */
    if ( ! hesk_dbNumRows($res) )
    {
    	return '';
    }

	/* We do have some replies, print the required Javascript and select field options */
	$can_options = '';
	?>
	<script language="javascript" type="text/javascript"><!--
    // -->
    var myMsgTxt = new Array();
	myMsgTxt[0]='';

	<?php
	while ($mysaved = hesk_dbFetchRow($res))
	{
	    $can_options .= '<option value="' . $mysaved[0] . '">' . $mysaved[1]. "</option>\n";
	    echo 'myMsgTxt['.$mysaved[0].']=\''.str_replace("\r\n","\\r\\n' + \r\n'", addslashes($mysaved[2]))."';\n";
	}

	?>

	function setMessage(msgid)
    {
		var myMsg=myMsgTxt[msgid];

        if (myMsg == '')
        {
        	if (document.form1.mode[1].checked)
            {
				document.getElementById('message').value = '';
            }
            return true;
        }

		myMsg = myMsg.replace(/%%HESK_NAME%%/g, '<?php echo hesk_jsString($ticket['name']); ?>');
		myMsg = myMsg.replace(/%%HESK_EMAIL%%/g, '<?php echo hesk_jsString($ticket['email']); ?>');
		myMsg = myMsg.replace(/%%HESK_custom1%%/g, '<?php echo hesk_jsString($ticket['custom1']); ?>');
		myMsg = myMsg.replace(/%%HESK_custom2%%/g, '<?php echo hesk_jsString($ticket['custom2']); ?>');
		myMsg = myMsg.replace(/%%HESK_custom3%%/g, '<?php echo hesk_jsString($ticket['custom3']); ?>');
		myMsg = myMsg.replace(/%%HESK_custom4%%/g, '<?php echo hesk_jsString($ticket['custom4']); ?>');
		myMsg = myMsg.replace(/%%HESK_custom5%%/g, '<?php echo hesk_jsString($ticket['custom5']); ?>');
		myMsg = myMsg.replace(/%%HESK_custom6%%/g, '<?php echo hesk_jsString($ticket['custom6']); ?>');
		myMsg = myMsg.replace(/%%HESK_custom7%%/g, '<?php echo hesk_jsString($ticket['custom7']); ?>');
		myMsg = myMsg.replace(/%%HESK_custom8%%/g, '<?php echo hesk_jsString($ticket['custom8']); ?>');
		myMsg = myMsg.replace(/%%HESK_custom9%%/g, '<?php echo hesk_jsString($ticket['custom9']); ?>');
		myMsg = myMsg.replace(/%%HESK_custom10%%/g, '<?php echo hesk_jsString($ticket['custom10']); ?>');
		myMsg = myMsg.replace(/%%HESK_custom11%%/g, '<?php echo hesk_jsString($ticket['custom11']); ?>');
		myMsg = myMsg.replace(/%%HESK_custom12%%/g, '<?php echo hesk_jsString($ticket['custom12']); ?>');
		myMsg = myMsg.replace(/%%HESK_custom13%%/g, '<?php echo hesk_jsString($ticket['custom13']); ?>');
		myMsg = myMsg.replace(/%%HESK_custom14%%/g, '<?php echo hesk_jsString($ticket['custom14']); ?>');
		myMsg = myMsg.replace(/%%HESK_custom15%%/g, '<?php echo hesk_jsString($ticket['custom15']); ?>');
		myMsg = myMsg.replace(/%%HESK_custom16%%/g, '<?php echo hesk_jsString($ticket['custom16']); ?>');
		myMsg = myMsg.replace(/%%HESK_custom17%%/g, '<?php echo hesk_jsString($ticket['custom17']); ?>');
		myMsg = myMsg.replace(/%%HESK_custom18%%/g, '<?php echo hesk_jsString($ticket['custom18']); ?>');
		myMsg = myMsg.replace(/%%HESK_custom19%%/g, '<?php echo hesk_jsString($ticket['custom19']); ?>');
		myMsg = myMsg.replace(/%%HESK_custom20%%/g, '<?php echo hesk_jsString($ticket['custom20']); ?>');

	    if (document.getElementById)
        {
			if (document.getElementById('moderep').checked)
            {
				document.getElementById('HeskMsg').innerHTML='<textarea class="form-control" name="message" id="message" rows="12" cols="72">'+myMsg+'</textarea>';
            }
            else
            {
            	var oldMsg = document.getElementById('message').value;
		        document.getElementById('HeskMsg').innerHTML='<textarea class="form-control" name="message" id="message" rows="12" cols="72">'+oldMsg+myMsg+'</textarea>';
            }
	    }
        else
        {
			if (document.form1.mode[0].checked)
            {
				document.form1.message.value=myMsg;
            }
            else
            {
            	var oldMsg = document.form1.message.value;
		        document.form1.message.value=oldMsg+myMsg;
            }
	    }

	}
	//-->
	</script>
    <?php

    /* Return options for select box */
    return $can_options;

} // End hesk_printCanned()
?>
