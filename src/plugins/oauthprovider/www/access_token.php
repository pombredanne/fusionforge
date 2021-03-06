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

// Access token endpoint

// This is not exactly using FusionForge, as is not meant for humans, and just an endpoint of the OAuth protocol.

require_once '../../env.inc.php';
require_once $gfwww.'include/pre.php';
//require_once 'checks.php';
/*if (!session_loggedin()) {
		exit_not_logged_in();
	}*/

try {
	$oauthprovider_server = new OAuthServer(FFDbOAuthDataStore::singleton());

	$hmac_method = new OAuthSignatureMethod_HMAC_SHA1();
	$oauthprovider_server->add_signature_method($hmac_method);

	// Retrieves an access token in exchange from the request token provided
	$req = OAuthRequest::from_request();
	//print_r($req->get_parameters());
	$verifier = $req->get_parameter('oauth_verifier');
	
	$request_token_key = $req->get_parameter('oauth_token');
	$request_token = OauthAuthzRequestToken::load_by_key($request_token_key);
	//make sure the verification code received in the request is the same as was sent when authorizing the request token
	$request_token->check_verifier($verifier);	
	
	$token = $oauthprovider_server->fetch_access_token($req);

	// the default print method is exactly what must be returned
	print $token;

} catch (OAuthException $e) {
	$code = $e->getCode();
	if ($code) {
		switch($code) {
			case 401:
				header('HTTP/1.1 401 Unauthorized', 401);
				break;
			case 400:
				header('HTTP/1.1 400 Bad Request', 400);
				break;
			default:
				break;
		}
	}
	print($e->getMessage() . "\n<hr />\n");
	print_r($req);
	die();
}
