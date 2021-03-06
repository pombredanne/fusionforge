<?php
/**
 * FusionForge Documentation Manager
 *
 * Copyright 2000, Quentin Cregan/Sourceforge
 * Copyright 2002-2003, Tim Perdue/GForge, LLC
 * Copyright 2010-2011, Franck Villaume - Capgemini
 * Copyright 2011, Roland Mas
 * Copyright (C) 2011 Alain Peyrat - Alcatel-Lucent
 * Copyright 2012, Thorsten Glaser - tarent solutions GmbH
 * Copyright 2012, Franck Villaume - TrivialDev
 * http://fusionforge.org
 *
 * This file is part of FusionForge. FusionForge is free software;
 * you can redistribute it and/or modify it under the terms of the
 * GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the Licence, or (at your option)
 * any later version.
 *
 * FusionForge is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with FusionForge; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

/* please do not add require here : use www/docman/index.php to add require */
/* global variables used */
global $g; //group object
global $group_id; // id of group
global $dirid;

$doc_group = getIntFromRequest('doc_group');
$title = trim(getStringFromRequest('title'));
$description = getStringFromRequest('description');
$file_url = getStringFromRequest('file_url');
$uploaded_data = getUploadedFile('uploaded_data');
$manual_path = getStringFromRequest('manual_path');
$type = getStringFromRequest('type');
$name = getStringFromRequest('name');
$stateid = getIntFromRequest('stateid');

if (!$doc_group) {
	$doc_group = $dirid;
}

$baseurl = '/docman/?group_id='.$group_id;
$redirecturl = $baseurl.'&view=listfile&dirid='.$doc_group;

// plugin projects-hierarchy handler
$childgroup_id = getIntFromRequest('childgroup_id');
if ($childgroup_id) {
	$redirecturl .= '&childgroup_id='.$childgroup_id;
	if (!forge_check_perm('docman', $childgroup_id, 'submit')) {
		$return_msg = _('Document Manager Action Denied.');
		session_redirect($redirecturl.'&warning_msg='.urlencode($return_msg));
	}
	$g = group_get_object($childgroup_id);
}

if (!forge_check_perm('docman', $g->getID(), 'submit')) {
	$return_msg = _('Document Manager Action Denied.');
	session_redirect($redirecturl.'&warning_msg='.urlencode($return_msg));
}

if ($error_msg) {
	session_redirect($baseurl.'&error_msg='.urlencode($error_msg));
}

if (!$doc_group) {
	//cannot add a doc unless an appropriate group is provided
	$return_msg = _('No valid folder was selected.');
	session_redirect($baseurl.'&error_msg='.urlencode($return_msg));
}

if (!$title || !$description || (!$uploaded_data && !$file_url && !$manual_path && (!$editor && !$name))) {
	$missing_params = array();
	if (!$title)
		$missing_params[] = 'title';

	if (!$description)
		$missing_params[] = 'description';

	exit_missing_param($_SERVER['HTTP_REFERER'], $missing_params, 'docman');
}

$d = new Document($g);

if (!$d || !is_object($d)) {
	$return_msg= _('Error getting blank document.');
	session_redirect($baseurl.'&error_msg='.urlencode($return_msg));
} elseif ($d->isError()) {
	session_redirect($baseurl.'&error_msg='.urlencode($d->getErrorMessage()));
}

switch ($type) {
	case 'editor' : {
		$filecontent = getStringFromRequest('details');
		$uploaded_data_name = $name;
		$sanitizer = new TextSanitizer();
		$filecontent = $sanitizer->SanitizeHtml($filecontent);
		if (strlen($filecontent) < 1) {
			$return_msg = _('Error getting blank document.');
			session_redirect($baseurl.'&error_msg='.urlencode($return_msg));
		}
		$data = tempnam("/tmp", "docman");
		$fh = fopen($data, 'w');
		fwrite($fh, $filecontent);
		fclose($fh);
		$uploaded_data_type = 'text/html';
		break;
	}
	case 'pasteurl' : {
		$data = '';
		$uploaded_data_name = $file_url;
		$uploaded_data_type = 'URL';
		break;
	}
	case 'httpupload' : {
		if (!is_uploaded_file($uploaded_data['tmp_name'])) {
		$return_msg = _('Invalid file name.');
			session_redirect($baseurl.'&error_msg='.urlencode($return_msg));
		}

		if (function_exists('finfo_open')) {
			$finfo = finfo_open(FILEINFO_MIME_TYPE);
			$uploaded_data_type = finfo_file($finfo, $uploaded_data['tmp_name']);
			if( $uploaded_data_type === 'application/msword') {
				$ext = pathinfo($uploaded_data['name'], PATHINFO_EXTENSION);
				if ( $ext === 'ppt' ) {
					$uploaded_data_type = 'application/vnd.ms-powerpoint';
				} elseif ( $ext === 'xls' ) {
					$uploaded_data_type = 'application/vnd.ms-excel';
				}
			}
		} else {
			$uploaded_data_type = $uploaded_data['type'];
		}
		if ($uploaded_data_type == 'application/octet-stream' && $uploaded_data_type != $uploaded_data['type']) {
			$uploaded_data_type = $uploaded_data['type'];
		}
		$data = $uploaded_data['tmp_name'];
		$file_url = '';
		$uploaded_data_name = $uploaded_data['name'];
		break;
	}
	case 'manualupload' : {
		if (!forge_get_config('use_manual_uploads')) {
			$return_msg = _('Manual uploads disabled.');
			session_redirect($baseurl.'&error_msg='.urlencode($return_msg));
		}

		$incoming = forge_get_config('groupdir_prefix')."/".$g->getUnixName()."/incoming";
		$filename = $incoming.'/'.$manual_path;

		if (!util_is_valid_filename($manual_path) || !is_file($filename)) {
			$return_msg = _('Invalid file name.');
			session_redirect($baseurl.'&error_msg='.urlencode($return_msg));
		}

		if (function_exists('finfo_open')) {
			$finfo = finfo_open(FILEINFO_MIME_TYPE);
			$uploaded_data_type = finfo_file($finfo, $filename);
		} else {
			$uploaded_data_type = 'application/binary';
		}
		$data = $filename;
		$file_url = '';
		$uploaded_data_name = $manual_path;
		break;
	}
	default: {
		$return_msg = _('Unknown type submission.');
		session_redirect($baseurl.'&error_msg='.urlencode($return_msg));
	}
}

if (!$d->create($uploaded_data_name, $uploaded_data_type, $data, $doc_group, $title, $description, $stateid)) {
	if (forge_check_perm('docman', $group_id, 'approve')) {
		session_redirect($redirecturl.'&error_msg='.urlencode($d->getErrorMessage()));
	} else {
		session_redirect($baseurl.'&error_msg='.urlencode($d->getErrorMessage()));
	}
} else {
	if ($type == 'editor') {
		//release the cookie for the document contents (should expire at the end of the session anyway)
		setcookie("gforgecurrentdocdata", "", time() - 3600);
	}
	if (forge_check_perm('docman', $group_id, 'approve')) {
		$return_msg = sprintf(_('Document %s submitted successfully.'), $d->getFilename());
		session_redirect($redirecturl.'&feedback='.urlencode($return_msg));
	} else {
		$return_msg = sprintf(_('Document %s has been successfully uploaded and is waiting to be approved.'),$d->getFilename());
		session_redirect($baseurl.'&feedback='.urlencode($return_msg));
	}
}
