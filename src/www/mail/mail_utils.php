<?php
/**
 * Mailing Lists Facility
 *
 * Copyright 1999-2001 (c) VA Linux Systems
 * Copyright 2003-2004 (c) Guillaume Smet - Open Wide
 * Copyright (C) 2010-2011 Alain Peyrat - Alcatel-Lucent
 * http://fusionforge.org/
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

function mail_header($params) {
	global $HTML, $group_id;

	if ($group_id) {
		//required for site_project_header
		$params['group'] = $group_id;
		$params['toptab'] = 'mail';

		$project = group_get_object($group_id);

		if ($project && is_object($project)) {
			if (!$project->usesMail()) {
				exit_disabled('home');
			}
		}

        $labels = array();
        $links = array();
        $labels[] = _('View Lists');
        $links[]  = '/mail/?group_id='.$group_id;
		if (session_loggedin()) {
			if (forge_check_perm ('project_admin', $project->getID())) {
		        $labels[] = _('Administration');
		        $links[]  = '/mail/admin/?group_id='.$group_id;
			}
		}
		$params['submenu'] = $HTML->subMenu($labels,$links);
		site_project_header($params);
	} else {
		exit_no_group();
	}
}

function mail_footer($params) {
	site_project_footer($params);
}

// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:
