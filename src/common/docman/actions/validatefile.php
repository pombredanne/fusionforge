<?php
/**
 * FusionForge Documentation Manager
 *
 * Copyright 2010-2011, Franck Villaume - Capgemini
 * Copyright 2013, Franck Villaume - TrivialDev
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
global $dirid; //id of doc_group
global $group_id; // id of group

$urlredirect = '/docman/?group_id='.$group_id.'&view=listfile&dirid='.$dirid;
// plugin projects-hierarchy handler
$childgroup_id = getIntFromRequest('childgroup_id');
if ($childgroup_id) {
	$g = group_get_object($childgroup_id);
	$urlredirect .= '&childgroup_id='.$childgroup_id;
}

if (!forge_check_perm('docman', $g->getID(), 'approve')) {
	$return_msg = _('Document Manager Action Denied.');
	session_redirect($urlredirect.'&warning_msg='.urlencode($return_msg));
}

$arr_fileid = explode(',', getStringFromRequest('fileid'));
$return_msg = _('Document(s)').' ';
foreach ($arr_fileid as $fileid) {
	if (!empty($fileid)) {
		$d = new Document($g, $fileid);
		$return_msg .= $d->getFilename().' ';

		if ($d->isError())
			session_redirect($urlredirect.'&error_msg='.urlencode($d->getErrorMessage()));

		if (!$d->setState('1'))
			session_redirect($urlredirect.'&error_msg='.urlencode($d->getErrorMessage()));
	} else {
		$warning_msg = _('No action to perform');
		session_redirect($urlredirect.'&warning_msg='.urlencode($warning_msg));
	}
}
$return_msg .= _('activated successfully.');
session_redirect($urlredirect.'&feedback='.urlencode($return_msg));
