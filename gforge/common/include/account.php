<?php
/**
 * account.php - A library of common account management functions.
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

/**
 * account_pwvalid() - Validates a password
 *
 * @param		string	The plaintext password string
 * @returns		true on success/false on failure
 *
 */
function account_pwvalid($pw) {
	global $Language;
	if (strlen($pw) < 6) {
		$GLOBALS['register_error'] = $Language->getText('common_include_account','sixchar');
		return 0;
	}
	return 1;
}

/**
 * account_namevalid() - Validates a login username
 *
 * @param		string	The username string
 * @returns		true on success/false on failure
 *
 */
function account_namevalid($name) {
	global $Language;
	// no spaces
	if (strrpos($name,' ') > 0) {
		$GLOBALS['register_error'] = $Language->getText('common_include_account','nospace');
		return 0;
	}

	// min and max length
	if (strlen($name) < 3) {
		$GLOBALS['register_error'] = $Language->getText('common_include_account','tooshort');
		return 0;
	}
	if (strlen($name) > 15) {
		$GLOBALS['register_error'] = $Language->getText('common_include_account','toolong');
		return 0;
	}

	if (!ereg('^[a-z][-a-z0-9_]+$', $name)) {
		$GLOBALS['register_error'] = $Language->getText('common_include_account','illegal');
		return 0;
	}

	// illegal names
	if (eregi("^((root)|(bin)|(daemon)|(adm)|(lp)|(sync)|(shutdown)|(halt)|(mail)|(news)"
		. "|(uucp)|(operator)|(games)|(mysql)|(httpd)|(nobody)|(dummy)"
		. "|(www)|(cvs)|(shell)|(ftp)|(irc)|(debian)|(ns)|(download))$",$name)) {
		$GLOBALS['register_error'] = "Name is reserved.";
		return 0;
	}
	if ( exec("getent passwd $name") != "" ){
		$GLOBALS['register_error'] = $Language->getText('account_register','err_userexist');
		return 0;
	}
	if (eregi("^(anoncvs_)",$name)) {
		$GLOBALS['register_error'] = $Language->getText('common_include_account','cvsreserved');
		return 0;
	}
		
	return 1;
}

/**
 * account_groupnamevalid() - Validates an account group name
 *
 * @param		string	The group name string
 * @returns		true on success/false on failure
 *
 */
function account_groupnamevalid($name) {
	global $Language;
	if (!account_namevalid($name)) return 0;
	
	// illegal names
	if (eregi("^((www[0-9]?)|(cvs[0-9]?)|(shell[0-9]?)|(ftp[0-9]?)|(irc[0-9]?)|(news[0-9]?)"
		. "|(mail[0-9]?)|(ns[0-9]?)|(download[0-9]?)|(pub)|(users)|(compile)|(lists)"
		. "|(slayer)|(orbital)|(tokyojoe)|(webdev)|(projects)|(cvs)|(slayer)|(monitor)|(mirrors?))$",$name)) {
		$GLOBALS['register_error'] = $Language->getText('common_include_account','dnsreserved');
		return 0;
	}

	if (eregi("_",$name)) {
		$GLOBALS['register_error'] = $Language->getText('common_include_account','nounderscore');
		return 0;
	}

	return 1;
}

/**
 * rannum() - Generate a random number
 * 
 * This is a local function used for account_salt()
 *
 * @return int $num A random number
 *
 */
function rannum(){	     
	mt_srand((double)microtime()*1000000);		  
	$num = mt_rand(46,122);		  
	return $num;		  
}	     

/**
 * genchr() - Generate a random character
 * 
 * This is a local function used for account_salt()
 *
 * @return int $num A random character
 *
 */
function genchr(){
	do {	  
		$num = rannum();		  
	} while ( ( $num > 57 && $num < 65 ) || ( $num > 90 && $num < 97 ) );	  
	$char = chr($num);	  
	return $char;	  
}	   

/**
 * account_gensalt() - A random salt generator
 *
 * @returns The random salt string
 *
 */
function account_gensalt(){

	$a = genchr(); 
	$b = genchr();
	$salt = "$1$" . "$a$b";
	return $salt;	
}

/**
 * account_genunixpw() - Generate unix password
 *
 * @param		string	The plaintext password string
 * @return		The encrypted password
 *
 */
function account_genunixpw($plainpw) {
	return crypt($plainpw,account_gensalt());
}

/**
 * account_shellselects() - Print out shell selects
 *
 * @param		string	The current shell
 *
 */
function account_shellselects($current) {
	$shells = file("/etc/shells");
	$shells[count($shells)] = "/bin/cvssh";

	for ($i = 0; $i < count($shells); $i++) {
		$this_shell = chop($shells[$i]);

		if ($current == $this_shell) {
			echo "<option selected value=$this_shell>$this_shell</option>\n";
		} else {
			if (! ereg("^#",$this_shell)){
				echo "<option value=$this_shell>$this_shell</option>\n";
			}
		}
	}
}

/**
 *	account_user_homedir() - Returns full path of user home directory
 *
 *  @param		string	The username
 *	@return home directory path
 */
function account_user_homedir($user) {
	//return '/home/users/'.substr($user,0,1).'/'.substr($user,0,2).'/'.$user;
	return $GLOBALS['homedir_prefix'].'/'.$user;
}

/**
 *	account_group_homedir() - Returns full path of group home directory
 *
 *  @param		string	The group name
 *	@return home directory path
 */
function account_group_homedir($group) {
	//return '/home/groups/'.substr($group,0,1).'/'.substr($group,0,2).'/'.$group;
	return $GLOBALS['groupdir_prefix'].'/'.$group;
}

/**
 *	account_group_cvsweb_url() - Returns URL for group's CVS interface WWW
 *
 *  @param		string	The group name
 *	@return URL to access CVS over HTTP
 */
function account_group_cvsweb_url($group) {
	/*
	return 'http://'.$GLOBALS['sys_cvs_host'].'/cgi-bin/viewcvs.cgi/'.$group;
	*/
	return 'http://'.$GLOBALS['sys_cvs_host'].'/cgi-bin/cvsweb.cgi?cvsroot='.$group;
}

?>
