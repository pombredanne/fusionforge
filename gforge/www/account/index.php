<?php
/**
 * User account main page - show settings with means to change them
 *
 * Copyright 1999-2001 (c) VA Linux Systems
 *
 * @version   $Id$
 *
 * This file is part of GForge.
 *
 * GForge is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * GForge is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with GForge; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

require_once('pre.php');
require_once('common/include/timezones.php');

session_require(array('isloggedin'=>'1'));

// get global users vars
$u =& user_get_object(user_getid());
if (!$u || !is_object($u)) {
    exit_error('Error','Could Not Get User');
} elseif ($u->isError()) {
    exit_error('Error',$u->getErrorMessage());
}

if (getStringFromRequest('submit')) {
	if (!form_key_is_valid(getStringFromRequest('form_key'))) {
		exit_form_double_submit();
	}


	$firstname = getStringFromRequest('firstname');
	$lastname = getStringFromRequest('lastname');
	$language = getIntFromRequest('language');
	$timezone = getStringFromRequest('timezone');
	$theme_id = getIntFromRequest('theme_id');
	$ccode = getStringFromRequest('ccode');
	$address = getStringFromRequest('address');
	$address2 = getStringFromRequest('address2');
	$phone = getStringFromRequest('phone');
	$fax = getStringFromRequest('fax');
	$title = getStringFromRequest('title');
	$jabber_address = getStringFromRequest('jabber');
	$jabber_only = getStringFromRequest('jabber');
	$mail_site = getStringFromRequest('mail_site');
	$mail_va = getStringFromRequest('mail_va');
	$remember_user = getStringFromRequest('remember_user');
	$use_ratings = getStringFromRequest('use_ratings');

/*
//needs security audit
	if ($remember_user) {
		// set cookie, expire in 3 months
		setcookie("sf_user_hash",$u->getID().'_'.substr($u->getMD5Passwd(),0,16),time()+90*24*60*60,'/');
	} else {
		// remove cookie
		setcookie("sf_user_hash",'',0,'/');
	}
*/
	// Refresh page if language or theme changed
	if ($language != $u->getLanguage() || $theme_id != $u->getThemeID()) {
		$refresh = 1;
	}

	if (!$u->update($firstname, $lastname, $language, $timezone, $mail_site, $mail_va, $use_ratings,
		$jabber_address,$jabber_only,$theme_id,$address,$address2,$phone,$fax,$title,$ccode)) {
		form_release_key(getStringFromRequest('form_key'));
		$feedback .= $u->getErrorMessage().'<br />';
	} else {
		$feedback .= $Language->getText('account','updated').'<br />';
	}

	if ($refresh) {
		header("Location: /account/?feedback=".urlencode($feedback));
	}

}

site_user_header(array('title'=>$Language->getText('account_options', 'title')));

echo '<form action="'.getStringFromServer('PHP_SELF').'" method="post">';
echo '<input type="hidden" name="form_key" value="'.form_generate_key().'">';
echo $HTML->boxTop($Language->getText('account_options', 'title'));

?>


<p> <?php echo $Language->getText('account_options', 'welcome'); ?> <strong><?php print $u->getRealName(); ?></strong>. </p>
<p>

<?php echo $Language->getText('account_options', 'intro'); ?>
</p>
<ul>
<li><a href="/users/<?php print $u->getUnixName(); ?>/"><strong><?php echo $Language->getText('account_options', 'view_developer_profile'); ?></strong></a></li>
<?php if($GLOBALS['sys_use_people']) { ?>
	<li><a href="/people/editprofile.php"><strong><?php echo $Language->getText('account_options', 'edit_skills_profile'); ?></strong></a></li>
<?php } ?>
</ul>
<?php echo $HTML->boxBottom(); ?>

&nbsp;<br />
<table width="100%" border="0">

<tr valign="top">
<td><?php echo $Language->getText('account_options', 'member_since'); ?> </td>
<td><strong><?php print date($sys_datefmt,$u->getAddDate()); ?></strong></td>
</tr>
<tr valign="top">
<td><?php echo $Language->getText('account_options', 'user_id'); ?> </td>
<td><strong><?php print $u->getID(); ?></strong></td>
</tr>

<tr valign="top">
<td><?php echo $Language->getText('account_options', 'login_name'); ?> </td>
<td><strong><?php print $u->getUnixName(); ?></strong>
<br /><a href="change_pw.php">[<?php echo $Language->getText('account_options', 'change_password'); ?>]</a></td>
</tr>

<tr valign="top">
<td><?php echo $Language->getText('account_options', 'firstname'); ?></td>
<td><input type="text" name="firstname" value="<?php print $u->getFirstName(); ?>" />
</td>
</tr>

<tr valign="top">
<td><?php echo $Language->getText('account_options', 'lastname'); ?></td>
<td><input type="text" name="lastname" value="<?php print $u->getLastName(); ?>" />
</td>
</tr>

<tr valign="top">
<td><?php echo $Language->getText('account_options', 'language'); ?> </td>
<td><?php echo html_get_language_popup ($Language,'language',$u->getLanguage()); ?>
</td>
</tr>

<tr valign="top">
<td><?php echo $Language->getText('account_options', 'timezone'); ?> </td>
<td><?php echo html_get_timezone_popup('timezone', $u->getTimeZone()); ?>
</td>
</tr>

<tr valign="top">
<td><?php echo $Language->getText('account_options', 'themeid'); ?> </td>
<td><?php echo html_get_theme_popup('theme_id', $u->getThemeID()); ?>
</td>
</tr>

<tr valign="top">
<td><?php echo $Language->getText('account_options', 'ccode'); ?> </td>
<td><?php echo html_get_ccode_popup('ccode', $u->getCountryCode()); ?>
</td>
</tr>

<tr valign="top">
<td><?php echo $Language->getText('account_options', 'email_address'); ?> </td>
<td><strong><?php print $u->getEmail(); ?></strong>
<br /><a href="change_email.php">[<?php echo $Language->getText('account_options', 'change_email_address'); ?>]</a>
</td>
</tr>

<tr valign="top">
	<td><?php echo $Language->getText('account_options', 'address'); ?></td>
	<td><input type="text" name="address" value="<?php echo $u->getAddress(); ?>" size="80"></td>
</tr>

<tr valign="top">
	<td><?php echo $Language->getText('account_options', 'address'); ?></td>
	<td><input type="text" name="address2" value="<?php echo $u->getAddress2(); ?>" size="80"></td>
</tr>

<tr valign="top">
	<td><?php echo $Language->getText('account_options', 'phone'); ?></td>
	<td><input type="text" name="phone" value="<?php echo $u->getPhone(); ?>" size="20"></td>
</tr>

<tr valign="top">
	<td><?php echo $Language->getText('account_options', 'fax'); ?></td>
	<td><input type="text" name="fax" value="<?php echo $u->getFax(); ?>" size="20"></td>
</tr>

<tr valign="top">
	<td><?php echo $Language->getText('account_options', 'title2'); ?></td>
	<td><input type="text" name="title" value="<?php echo $u->getTitle(); ?>" size="10"></td>
</tr>

<?php
if ($sys_use_jabber) {
    echo '<tr valign="top">
<td>'. $Language->getText('account_register','jabberaddr') .'</td>
<td>
    <input size=30 type="text" name="jabber_address" value="'. $u->getJabberAddress() .'" /><p />
	<input type="checkbox" name="jabber_only" value="1" '.(($u->getJabberOnly()) ? 'checked="CHECKED"' : '' ).' />
	'.$Language->getText('account_register','jabberonly').'.
</td></tr>';

}
?>


<tr>
<td colspan="2">
<?php
// ############################# Preferences
echo $HTML->boxTop($Language->getText('account_register','Preferences')); ?>

<input type="checkbox" name="mail_site" value="1"<?php
	if ($u->getMailingsPrefs('site')) print " checked=\"checked\""; ?> />
	<?php echo $Language->getText('account_register','siteupdate'); ?>

<p /><input type="checkbox"  name="mail_va" value="1"<?php
	if ($u->getMailingsPrefs('va')) print " checked=\"checked\""; ?> />
	<?php echo $Language->getText('account_register','communitymail'); ?>
<?php /*
<p /><input type="checkbox"  name="remember_user" value="1"<?php
	if ($sf_user_hash) print " checked=\"checked\""; ?> />
<?php echo $Language->getText('account_register','remember_me','<a href="/my/">');
*/ ?>

<p /><input type="checkbox"  name="use_ratings" value="1"<?php
	if ($u->usesRatings()) print " checked=\"checked\""; ?> />
	<?php echo $Language->getText('account_register','partecipate_peer_ratings','<a href="/users/'.$u->getUnixName().'">'); ?>
<?php 
$hookParams['user']= user_get_object(user_getid());
if (getStringFromRequest('submit')) {//if this is set, then the user has issued an Update
	plugin_hook("userisactivecheckboxpost", $hookParams);
} else {
	plugin_hook("userisactivecheckbox", $hookParams);
}

?>

<?php echo $HTML->boxBottom();

// ############################### Shell Account

if (($u->getUnixStatus() == 'A') && ($sys_use_shell)) {
	echo $HTML->boxTop($Language->getText('account_shell','title')."");
	print '&nbsp;
<br />'.$Language->getText('account_shell','shell_box').': <strong>'.$u->getUnixBox().'</strong>
<br />'.$Language->getText('account_shell','autorized_keys').': <strong>';
	// get shared key count from db
	$expl_keys = explode("\n", $u->getAuthorizedKeys());
	if ($expl_keys[0]) {
		print (sizeof($expl_keys));
	} else {
		print '0';
	}
	print '</strong> <a href="editsshkeys.php">['.$Language->getText('account_shell','edit_keys').']</a>';
	echo $HTML->boxBottom();
}
?>
</td>
</tr>



</table>

<div align="center">
<input type="submit" name="submit" value="<?php echo $Language->getText('account_register','update'); ?>" />
<input type="reset" name="reset" value="<?php echo $Language->getText('account_register','reset'); ?>" />
</div>
</form>

<?php
site_user_footer(array());
?>
