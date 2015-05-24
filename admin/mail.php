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
require(HESK_PATH . 'inc/email_functions.inc.php');

hesk_session_start();
hesk_dbConnect();
hesk_isLoggedIn();

/* List of staff */
$admins = array();
$res = hesk_dbQuery("SELECT `id`,`name` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users` ORDER BY `name` ASC");
while ($row=hesk_dbFetchAssoc($res))
{
	$admins[$row['id']]=$row['name'];
}

/* What folder are we in? */
$hesk_settings['mailtmp']['inbox']  = '<a href="mail.php"><i class="fa fa-download"></i></a> <a href="mail.php">'.$hesklang['inbox'].'</a>';
$hesk_settings['mailtmp']['outbox'] = '<a href="mail.php?folder=outbox"><i class="fa fa-upload" style="font-size: 16px"></i></a> <a href="mail.php?folder=outbox">'.$hesklang['outbox'].'</a>';
$hesk_settings['mailtmp']['new']    = '<a href="mail.php?a=new"><i class="fa fa-pencil-square-o" style="font-size:16px"></i></a> <a href="mail.php?a=new">'.$hesklang['m_new'].'</a>';

/* Get action */
if ( $action = hesk_REQUEST('a') )
{
	if ( defined('HESK_DEMO') && $action != 'new' && $action != 'read' )
	{
		hesk_process_messages($hesklang['ddemo'], 'mail.php', 'NOTICE');
	}
}

/* Sub-page specific settings */
if (isset($_GET['folder']) && hesk_GET('folder') == 'outbox')
{
	$hesk_settings['mailtmp']['this']   = 'from';
	$hesk_settings['mailtmp']['other']  = 'to';
	$hesk_settings['mailtmp']['m_from'] = $hesklang['m_to'];
	$hesk_settings['mailtmp']['outbox'] = '<b><i class="fa fa-upload" style="font-size:16px"></i> '.$hesklang['outbox'].'</b>';
    $hesk_settings['mailtmp']['folder'] = 'outbox';
}
elseif ($action == 'new')
{
	$hesk_settings['mailtmp']['new'] = '<b><i class="fa fa-pencil-square-o" style="font-size: 16px"></i> '.$hesklang['m_new'].'</b>';
	$_SESSION['hide']['list'] = 1;

    /* Do we have a recipient selected? */
    if (!isset($_SESSION['mail']['to']) && isset($_GET['id']))
    {
    	$_SESSION['mail']['to'] = intval( hesk_GET('id') );
    }
}
else
{
	$hesk_settings['mailtmp']['this']   = 'to';
	$hesk_settings['mailtmp']['other']  = 'from';
	$hesk_settings['mailtmp']['m_from'] = $hesklang['m_from'];
    if ($action != 'read')
    {
		$hesk_settings['mailtmp']['inbox']  = '<b><i class="fa fa-download" style="font-size: 16px"></i> '.$hesklang['inbox'].'</b>';
        $hesk_settings['mailtmp']['folder'] = '';
    }
}

/* What should we do? */
switch ($action)
{
	case 'send':
    	mail_send();
        break;
    case 'mark_read':
    	mail_mark_read();
        break;
    case 'mark_unread':
    	mail_mark_unread();
        break;
    case 'delete':
    	mail_delete();
        break;
}

/* Print header */
require_once(HESK_PATH . 'inc/headerAdmin.inc.php');

/* Print main manage users page */
require_once(HESK_PATH . 'inc/show_admin_nav.inc.php');
?>

<script language="javascript" type="text/javascript"><!--
function confirm_delete()
{
	if (confirm('<?php echo addslashes($hesklang['delete_saved']); ?>')) {return true;}
	else {return false;}
}
//-->
</script>

<div class="row" style="margin-top: 20px">
    <div class="col-md-3">
        <div class="panel panel-default">
            <div class="panel-heading"><?php echo $hesklang['navigation']; ?></div>
            <ul class="list-group">
            <?php
            /* Print sub-navigation */
            echo
            '<li class="list-group-item">'.$hesk_settings['mailtmp']['inbox'].'</li>
            <li class="list-group-item">' . $hesk_settings['mailtmp']['outbox'] . '</li>
            <li class="list-group-item">' . $hesk_settings['mailtmp']['new'] . '</li>';
            ?>
            </ul>
        </div>
    </div>
    <div class="col-md-7">
        <h3><?php echo $hesklang['m_h']; ?></h3>
        <div class="footerWithBorder blankSpace"></div>

        <?php


/* This will handle error, success and notice messages */
hesk_handle_messages();

/* Show a message? */
if ($action == 'read')
{
	show_message();
}

/* Hide list of messages? */
if (!isset($_SESSION['hide']['list']))
{
	mail_list_messages();
} // END hide list of messages

/* Show new message form */
show_new_form();

/* Clean unneeded session variables */
hesk_cleanSessionVars('hide');
hesk_cleanSessionVars('mail');

require_once(HESK_PATH . 'inc/footer.inc.php');
exit();


/*** START FUNCTIONS ***/


function mail_delete()
{
	global $hesk_settings, $hesklang;

	/* A security check */
	hesk_token_check();

	$ids = mail_get_ids();

	if ($ids)
	{
		foreach ($ids as $id)
        {
        	/* If both correspondents deleted the mail remove it from database, otherwise mark as deleted by this user */
	        hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."mail` SET `deletedby`='".intval($_SESSION['id'])."' WHERE `id`='".intval($id)."' AND (`to`='".intval($_SESSION['id'])."' OR `from`='".intval($_SESSION['id'])."') AND `deletedby`=0 LIMIT 1");

            if (hesk_dbAffectedRows() != 1)
            {
		        hesk_dbQuery("DELETE FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."mail` WHERE `id`='".intval($id)."' AND (`to`='".intval($_SESSION['id'])."' OR `from`='".intval($_SESSION['id'])."') AND `deletedby`!=0 LIMIT 1");
            }
        }

		hesk_process_messages($hesklang['smdl'],'NOREDIRECT','SUCCESS');
	}

    return true;
} // END mail_mark_unread()


function mail_mark_unread()
{
	global $hesk_settings, $hesklang;

	/* A security check */
	hesk_token_check();

	$ids = mail_get_ids();

	if ($ids)
	{
		foreach ($ids as $id)
        {
	        hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."mail` SET `read`='0' WHERE `id`='".intval($id)."' AND `to`='".intval($_SESSION['id'])."' LIMIT 1");
        }

		hesk_process_messages($hesklang['smmu'],'NOREDIRECT','SUCCESS');
	}

    return true;
} // END mail_mark_unread()


function mail_mark_read()
{
	global $hesk_settings, $hesklang;

	/* A security check */
	hesk_token_check('POST');

	$ids = mail_get_ids();

	if ($ids)
	{
		foreach ($ids as $id)
        {
	        hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."mail` SET `read`='1' WHERE `id`='".intval($id)."' AND `to`='".intval($_SESSION['id'])."' LIMIT 1");
        }

		hesk_process_messages($hesklang['smmr'],'NOREDIRECT','SUCCESS');
	}

    return true;
} // END mail_mark_read()


function mail_get_ids()
{
	global $hesk_settings, $hesklang;

	// Mail id as a query parameter?
	if ( $id = hesk_GET('id', false) )
	{
		return array($id);
	}
	// Mail id as a post array?
	elseif ( isset($_POST['id']) && is_array($_POST['id']) )
	{
		return array_map('intval', $_POST['id']);
	}
	// No valid ID parameter
	else
	{
		hesk_process_messages($hesklang['nms'],'NOREDIRECT','NOTICE');
		return false;
	}
    
} // END mail_get_ids()


function mail_send()
{
	global $hesk_settings, $hesklang;

	/* A security check */
	hesk_token_check('POST');

	$hesk_error_buffer = '';

	/* Recipient */
	$_SESSION['mail']['to'] = intval( hesk_POST('to') );

	/* Valid recipient? */
    if (empty($_SESSION['mail']['to']))
    {
		$hesk_error_buffer .= '<li>' . $hesklang['m_rec'] . '</li>';
    }
	elseif ($_SESSION['mail']['to'] == $_SESSION['id'])
	{
		$hesk_error_buffer .= '<li>' . $hesklang['m_inr'] . '</li>';
	}
	else
	{
		$res = hesk_dbQuery("SELECT `name`,`email`,`notify_pm` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."users` WHERE `id`='".intval($_SESSION['mail']['to'])."' LIMIT 1");
		$num = hesk_dbNumRows($res);
		if (!$num)
		{
			$hesk_error_buffer .= '<li>' . $hesklang['m_inr'] . '</li>';
		}
        else
        {
        	$pm_recipient = hesk_dbFetchAssoc($res);
        }
	}

	/* Subject */
	$_SESSION['mail']['subject'] = hesk_input( hesk_POST('subject') ) or $hesk_error_buffer .= '<li>' . $hesklang['m_esu'] . '</li>';

	/* Message */
	$_SESSION['mail']['message'] = hesk_input( hesk_POST('message') ) or $hesk_error_buffer .= '<li>' . $hesklang['enter_message'] . '</li>';

	/* Any errors? */
	if (strlen($hesk_error_buffer))
	{
    	$_SESSION['hide']['list'] = 1;
		$hesk_error_buffer = $hesklang['rfm'].'<br /><br /><ul>'.$hesk_error_buffer.'</ul>';
		hesk_process_messages($hesk_error_buffer,'NOREDIRECT');
	}
    else
    {
		$_SESSION['mail']['message'] = hesk_makeURL($_SESSION['mail']['message']);
		$_SESSION['mail']['message'] = nl2br($_SESSION['mail']['message']);
        
		hesk_dbQuery("INSERT INTO `".hesk_dbEscape($hesk_settings['db_pfix'])."mail` (`from`,`to`,`subject`,`message`,`dt`,`read`) VALUES ('".intval($_SESSION['id'])."','".intval($_SESSION['mail']['to'])."','".hesk_dbEscape($_SESSION['mail']['subject'])."','".hesk_dbEscape($_SESSION['mail']['message'])."',NOW(),'0')");

        /* Notify receiver via e-mail? */
        if (isset($pm_recipient) && $pm_recipient['notify_pm'])
        {
            $pm_id = hesk_dbInsertID();

            $pm = array(
				'name'		=> hesk_msgToPlain( addslashes($_SESSION['name']) ,1,1),
				'subject'	=> hesk_msgToPlain($_SESSION['mail']['subject'],1,1),
				'message'	=> hesk_msgToPlain($_SESSION['mail']['message'],1,1),
				'id'		=> $pm_id,
            );

			/* Format email subject and message for recipient */
			$subject = hesk_getEmailSubject('new_pm',$pm,0);
			$message = hesk_getEmailMessage('new_pm',$pm,1,0);
            $htmlMessage = hesk_getHtmlMessage('new_pm',$pm,1,0);
            $hasMessage = hesk_doesTemplateHaveTag('new_pm','%%MESSAGE%%');

			/* Send e-mail */
			hesk_mail($pm_recipient['email'], $subject, $message, $htmlMessage, array(), array(), $hasMessage);
        }

		unset($_SESSION['mail']);

		hesk_process_messages($hesklang['m_pms'],'./mail.php','SUCCESS');
    }
} // END mail_send()


function show_message()
{
	global $hesk_settings, $hesklang, $admins;

		$id = intval( hesk_GET('id') );

		/* Get the message details */
		$res = hesk_dbQuery("SELECT * FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."mail` WHERE `id`='".intval($id)."' AND `deletedby`!='".intval($_SESSION['id'])."' LIMIT 1");
		$num = hesk_dbNumRows($res);

	    if ($num)
	    {
	    	$pm = hesk_dbFetchAssoc($res);

	        /* Allowed to read the message? */
	        if ($pm['to'] == $_SESSION['id'])
	        {

			    if (!isset($_SESSION['mail']['subject']))
			    {
			    	$_SESSION['mail']['subject'] = $hesklang['m_re'] . ' ' . $pm['subject'];
			    }

			    if (!isset($_SESSION['mail']['to']))
			    {
			    	$_SESSION['mail']['to'] = $pm['from'];
			    }

	        }
	        elseif ($pm['from'] == $_SESSION['id'])
	        {

			    if (!isset($_SESSION['mail']['subject']))
			    {
			    	$_SESSION['mail']['subject'] = $hesklang['m_fwd'] . ' ' . $pm['subject'];
			    }

			    if (!isset($_SESSION['mail']['to']))
			    {
			    	$_SESSION['mail']['to'] = $pm['to'];
			    }

				$hesk_settings['mailtmp']['this']   = 'from';
				$hesk_settings['mailtmp']['other']  = 'to';
				$hesk_settings['mailtmp']['m_from'] = $hesklang['m_to'];
				$hesk_settings['mailtmp']['outbox'] = '<b>'.$hesklang['outbox'].'</b>';
				$hesk_settings['mailtmp']['inbox']  = '<a href="mail.php">'.$hesklang['inbox'].'</a>';
				$hesk_settings['mailtmp']['outbox'] = '<a href="mail.php?folder=outbox">'.$hesklang['outbox'].'</a>';

	        }
	        else
	        {
	        	hesk_process_message($hesklang['m_ena'],'mail.php');
	        }

	        /* Mark as read */
	        if ($hesk_settings['mailtmp']['this'] == 'to' && !$pm['read'])
	        {
				$res = hesk_dbQuery("UPDATE `".hesk_dbEscape($hesk_settings['db_pfix'])."mail` SET `read`='1' WHERE `id`='".intval($id)."' LIMIT 1");
	        }

	        $pm['name'] = isset($admins[$pm[$hesk_settings['mailtmp']['other']]]) ? '<a href="mail.php?a=new&amp;id='.$pm[$hesk_settings['mailtmp']['other']].'">'.$admins[$pm[$hesk_settings['mailtmp']['other']]].'</a>' : (($pm['from'] == 9999) ? '<a href="http://www.hesk.com" target="_blank">HESK.com</a>' : $hesklang['e_udel']);
	        
            echo $pm['dt'];
            
            $pm['dt'] = hesk_dateToString($pm['dt'],0,1,0,true);
			?>

            <table width="100%" border="0" cellspacing="0" cellpadding="0">
            <tr>
            <td>

                <table border="0" cellspacing="0" cellpadding="0" width="100%">
                <tr>
                <td valign="top">
                    <table border="0">
                    <tr>
                        <td><b><?php echo $hesk_settings['mailtmp']['m_from']; ?></b></td>
                        <td><?php echo $pm['name']; ?></td>
                    </tr>
                    <tr>
                        <td><b><?php echo $hesklang['date']; ?></b></td>
                        <td><?php echo $pm['dt']; ?></td>
                    </tr>
                    <tr>
                        <td><b><?php echo $hesklang['m_sub']; ?></b></td>
                        <td><?php echo $pm['subject']; ?></td>
                    </tr>
                    </table>
                </td>
                <td class="text-right" style="vertical-align:top;">

                    <?php
                    $folder = '&amp;folder=outbox';
                    if ($pm['to'] == $_SESSION['id'])
                    {
                        echo '<a href="mail.php?a=mark_unread&amp;id='.$id.'&amp;token='.hesk_token_echo(0).'"><i style="font-size: 14px" class="fa fa-envelope-o"></i></a> ';
                        $folder = '';
                    }
                    echo '<a href="mail.php?a=delete&amp;id='.$id.'&amp;token='.hesk_token_echo(0).$folder.'" onclick="return hesk_confirmExecute(\''.hesk_makeJsString($hesklang['delm']).'?\');"><i class="fa fa-times" style="font-size: 14px; color: red"></i></a>';
                    ?>

                </td>
                </tr>
                </table>

            <p><?php echo $pm['message']; ?></p>

            </td>
            </tr>
        </table>
    </div><hr />


			<?php
	    } // END if $num

		$_SESSION['hide']['list'] = 1;

} // END show_message()


function mail_list_messages()
{
	global $hesk_settings, $hesklang, $admins;

    $href = 'mail.php';
    $query = '';
    if ($hesk_settings['mailtmp']['folder'] == 'outbox')
    {
    	$query .= 'folder=outbox&amp;';
    }
    $query .= 'page=';

	$maxresults = 30;

	$tmp  = intval( hesk_POST('page', 1) );
	$page = ($tmp > 1) ? $tmp : 1;

	/* List of private messages */
	$res = hesk_dbQuery("SELECT COUNT(*) FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."mail` WHERE `".hesk_dbEscape($hesk_settings['mailtmp']['this'])."`='".intval($_SESSION['id'])."' AND `deletedby`!='".intval($_SESSION['id'])."'");
	$total = hesk_dbResult($res,0,0);

    if ($total > 0)
	{

		$pages = ceil($total/$maxresults) or $pages = 1;
		if ($page > $pages)
		{
			$page = $pages;
		}
		$limit_down = ($page * $maxresults) - $maxresults;

		$prev_page = ($page - 1 <= 0) ? 0 : $page - 1;
		$next_page = ($page + 1 > $pages) ? 0 : $page + 1;

		if ($pages > 1)
		{
			echo $hesklang['pg'] . ': ';

			/* List pages */
			if ($pages >= 7)
			{
				if ($page > 2)
				{
					echo '<a href="'.$href.'?'.$query.'1"><b>&laquo;</b></a> &nbsp; ';
				}

				if ($prev_page)
				{
					echo '<a href="'.$href.'?'.$query.$prev_page.'"><b>&lsaquo;</b></a> &nbsp; ';
				}
			}

			for ($i=1; $i<=$pages; $i++)
			{
				if ($i <= ($page+5) && $i >= ($page-5))
				{
					if ($i == $page)
					{
						echo ' <b>'.$i.'</b> ';
					}
					else
					{
						echo ' <a href="'.$href.'?'.$query.$i.'">'.$i.'</a> ';
					}
				}
			}

			if ($pages >= 7)
			{
				if ($next_page)
				{
					echo ' &nbsp; <a href="'.$href.'?'.$query.$next_page.'"><b>&rsaquo;</b></a> ';
				}

				if ($page < ($pages - 1))
				{
					echo ' &nbsp; <a href="'.$href.'?'.$query.$pages.'"><b>&raquo;</b></a>';
				}
			}

            echo '<br />&nbsp;';

		} // end PAGES > 1

		// Get messages from the database
        $res = hesk_dbQuery("SELECT `id`, `from`, `to`, `subject`, `dt`, `read` FROM `".hesk_dbEscape($hesk_settings['db_pfix'])."mail` WHERE `".hesk_dbEscape($hesk_settings['mailtmp']['this'])."`='".intval($_SESSION['id'])."' AND `deletedby`!='".intval($_SESSION['id'])."' ORDER BY `id` DESC LIMIT ".intval($limit_down)." , ".intval($maxresults)." ");
		?>

		<form action="mail.php<?php if ($hesk_settings['mailtmp']['folder'] == 'outbox') {echo '?folder=outbox';} ?>" name="form1" method="post">

		<div align="center">
		<table class="table table-striped">
		<tr>
		<th><input type="checkbox" name="checkall" value="2" onclick="hesk_changeAll(this)" /></th>
		<th><?php echo $hesklang['m_sub']; ?></th>
		<th><?php echo $hesk_settings['mailtmp']['m_from']; ?></th>
		<th><?php echo $hesklang['date']; ?></th>
		</tr>

		<?php
		$i = 0;
		while ($pm=hesk_dbFetchAssoc($res))
		{
			if ($i) {$color="admin_gray"; $i=0;}
			else {$color="admin_white"; $i=1;}

			$pm['subject'] = '<a href="mail.php?a=read&amp;id='.$pm['id'].'">'.$pm['subject'].'</a>';
		    if ($hesk_settings['mailtmp']['this'] == 'to' && !$pm['read'])
		    {
		    	$pm['subject'] = '<b>'.$pm['subject'].'</b>';
		    }
			$pm['name'] = isset($admins[$pm[$hesk_settings['mailtmp']['other']]]) ? '<a href="mail.php?a=new&amp;id='.$pm[$hesk_settings['mailtmp']['other']].'">'.$admins[$pm[$hesk_settings['mailtmp']['other']]].'</a>' : (($pm['from'] == 9999) ? '<a href="http://www.hesk.com" target="_blank">HESK.com</a>' : $hesklang['e_udel']);
		    $pm['dt'] = hesk_dateToString($pm['dt'],0,0,0,true);

			echo <<<EOC
			<tr>
			<td><input type="checkbox" name="id[]" value="$pm[id]" />&nbsp;</td>
			<td>$pm[subject]</td>
			<td>$pm[name]</td>
			<td>$pm[dt]</td>
			</tr>

EOC;
		} // End while
		?>
		</table>
		</div>

		<div class="form-group">
            <div class="col-sm-6">
                <select class="form-control" name="a">
		<?php
		if ($hesk_settings['mailtmp']['this'] == 'to')
		{
			?>
            <option value="mark_read" selected="selected"><?php echo $hesklang['mo1']; ?></option>
			<option value="mark_unread"><?php echo $hesklang['mo2']; ?></option>
			<?php
		}
		?>
		<option value="delete"><?php echo $hesklang['mo3']; ?></option>
		</select>
            </div>
            <div class="col-sm-3">
                <input type="hidden" name="token" value="<?php hesk_token_echo(); ?>" />
		<input type="submit" value="<?php echo $hesklang['execute']; ?>" onclick="Javascript:if (document.form1.a.value=='delete') return hesk_confirmExecute('<?php echo hesk_makeJsString($hesklang['mo3']); ?>?');" class="btn btn-default" />
            </div>
        </div>

		</form>

		<p>&nbsp;</p>
	    <?php

	} // END if total > 0
    else
    {
    	echo '<i>' . $hesklang['npm'] . '</i> <p>&nbsp;</p>';
    }

} // END mail_list_messages()


function show_new_form()
{
	global $hesk_settings, $hesklang, $admins;
	?>
    <br/>
    <form action="mail.php" method="post" name="form2" class="form-horizontal" role="form">
        <h3><?php echo $hesklang['new_mail']; ?></h3>
        <div class="footerWithBorder blankSpace"></div>

        <div class="form-group">
            <label for="to" class="col-sm-3 control-label"><?php echo $hesklang['m_to']; ?></label>
            <div class="col-sm-9">
                <select class="form-control" name="to">
					<option value="" selected="selected"><?php echo $hesklang['select']; ?></option>
					<?php
					foreach ($admins as $k=>$v)
					{
						if ($k != $_SESSION['id'])
						{
							if (isset($_SESSION['mail']) && $k == $_SESSION['mail']['to'])
							{
								echo '<option value="'.$k.'" selected="selected">'.$v.'</option>';
							}
							else
							{
								echo '<option value="'.$k.'">'.$v.'</option>';
							}
						}
					}
					?>
					</select>
            </div>
        </div>
        <div class="form-group">
            <label for="subject" class="col-sm-3 control-label"><?php echo $hesklang['m_sub']; ?></label>
            <div class="col-sm-9">
                <input type="text" class="form-control" placeholder="<?php echo htmlspecialchars($hesklang['subject']); ?>" name="subject" size="40" maxlength="50"
				<?php
				if (isset($_SESSION['mail']['subject']))
				{
					echo ' value="'.stripslashes($_SESSION['mail']['subject']).'" ';
				}
				?>
				/>
            </div>
        </div>
        <div class="form-group">
            <label for="message" class="col-sm-3 control-label"><?php echo $hesklang['message']; ?>:</label>
            <div class="col-sm-9">
                <textarea name="message" class="form-control" placeholder="<?php echo htmlspecialchars($hesklang['message']); ?>" rows="15" cols="70"><?php
                if (isset($_SESSION['mail']['message']))
                {
                    echo stripslashes($_SESSION['mail']['message']);
                }
                ?></textarea>
            </div>
        </div>
        <div class="form-group">
            <div class="col-sm-9 col-sm-offset-3">
                <input type="hidden" name="token" value="<?php hesk_token_echo(); ?>" />
                <input type="hidden" name="a" value="send" />
                <input type="submit" value="<?php echo $hesklang['m_send']; ?>" class="btn btn-default" />
            </div>
        </div>
    <?php
} // END show_new_form()
?>
