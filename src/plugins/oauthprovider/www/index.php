<?php

/**
 * This file is (c) Copyright 2010 by Olivier BERGER, Madhumita DHAR, Institut TELECOM
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * This program has been developed in the frame of the COCLICO
 * project with financial support of its funders.
 *
 */

require_once '../../env.inc.php';
require_once 'checks.php';

$pluginname = 'oauthprovider';
if($type == 'group') {
	oauthprovider_CheckGroup();
	if($name)	{
		$suffix = "?type=group&name=$name";
	}
	else if($id) {
		$suffix = "?type=group&id=$id";
	}
}
else oauthprovider_CheckUser();

?>

<h3>OAuth endpoints</h3>

<p>This OAuthProvider plugin provides the following OAuth endpoints for OAuth consumers to use, in "3-legs" mode.</p>

<?php
  $scheme = (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] != "on") ? 'http' : 'https';
  $http_url = $scheme . '://' . $_SERVER['HTTP_HOST'];
?>

<ul>
  <li><b>Request Token URL:</b> <tt><?php echo $http_url . '/plugins/'.$pluginname.'/request_token.php' ?></tt> (GET)</li>
  <li><b>User Authorization URL:</b> <tt><?php echo $http_url . '/plugins/'.$pluginname.'/authorize.php'.$suffix ?></tt></li>
  <li><b>Access Token URL:</b> <tt><?php echo $http_url . '/plugins/'.$pluginname.'/access_token.php' ?></tt></li>
</ul>

    <p>For instance, with Zend_Oauth, in PHP, the consumer should use such code to request a token :<pre><tt>
      $consumer = new Zend_OAuth_Consumer($config);
      $consumer->setRequestMethod(Zend_Oauth::GET);
      $consumer->setRequestTokenUrl($BASE_FF_URL.'/plugins/oauthprovider/request_token.php');
      $customServiceParameters= array(
				'type' => 'group',
				'id' => n
				);
      $token = $consumer->getRequestToken($customServiceParameters);</tt></pre></p>

<h3>Signature method</h3>

  <p>The <b>HMAC_SHA1</b> signature method is the only one supported at the moment.</p>

<?php
$link_param = '?type='.$type.'&'.$type_param[0].'='.$type_param[1];
echo util_make_link('/plugins/'.$pluginname.'/consumer.php', _('Consumers')). ' <br />';
echo util_make_link('/plugins/'.$pluginname.'/request_tokens.php'.$link_param, _('Request tokens')).'<br /> ';
echo util_make_link('/plugins/'.$pluginname.'/access_tokens.php'.$link_param, _('Access tokens')).'<br /> ';

//html_page_bottom();
site_project_footer(array());
