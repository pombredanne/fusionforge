<?php
/**
 * Tracker Front Page
 *
 * Copyright 2010-2011, Franck Villaume - Capgemini
 * Copyright (C) 2011 Alain Peyrat - Alcatel-Lucent
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

$atf = new ArtifactTypeFactory($group);
if (!$group || !is_object($group) || $group->isError()) {
	exit_error(_('Could Not Get ArtifactTypeFactory'),'tracker');
}

$at_arr = $atf->getArtifactTypes();
if ($at_arr === false) {
	exit_permission_denied('tracker');
}

use_javascript('/js/sortable.js');

//required params for site_project_header();
$params['group']=$group_id;
$params['title']=sprintf(_('Trackers for %1$s'), $group->getPublicName());
$params['toptab']='tracker';

site_project_header($params);

if (forge_check_perm('tracker_admin', $group_id)) {
	$menu_text = array();
	$menu_links = array();
	$menu_text[] = _('Administration');
	$menu_links[] = '/tracker/admin/?group_id='.$group_id;
	$menu_attr[] = array('title' => _('Global administration for trackers. Create, clone, workflow, fields ...'), 'class' => 'tabtitle-nw');
	echo $HTML->subMenu($menu_text, $menu_links, $menu_attr);
}


if (!$at_arr || count($at_arr) < 1) {
	echo '<div class="warning">'._('No Accessible Trackers Found').'</div>';
	printf(_('<p><strong>No trackers have been set up, or you cannot view them.</strong></p><p><span class="important">The Admin for this project will have to set up data types using the %1$s admin page %2$s</span></p>'), '<a href="'.util_make_url ('/tracker/admin/?group_id='.$group_id).'">', '</a>');
} else {

	plugin_hook ("blocks", "tracker index");

	echo '<p>'._('Choose a tracker and you can browse/edit/add items to it.').'</p>';

	/*
		Put the result set (list of trackers for this group) into a column with folders
	*/
	$tablearr = array(_('Tracker'),_('Description'),_('Open'),_('Total'));

	echo $HTML->listTableTop($tablearr, false, 'sortable_table_tracker', 'sortable_table_tracker');

	for ($j = 0; $j < count($at_arr); $j++) {
		if (!is_object($at_arr[$j])) {
			//just skip it
		} elseif ($at_arr[$j]->isError()) {
			echo $at_arr[$j]->getErrorMessage();
		} else {
			echo '
		<tr>
			<td><a href="'.util_make_url ('/tracker/?atid='.$at_arr[$j]->getID().'&amp;group_id='.$group_id.'&amp;func=browse').'">'.
 				html_image("ic/tracker20w.png","20","20").' &nbsp;'.
				$at_arr[$j]->getName() .'</a>
			</td>
			<td>' .  $at_arr[$j]->getDescription() .'
			</td>
			<td style="text-align:center">'. (int) $at_arr[$j]->getOpenCount() . '
			</td>
			<td style="text-align:center">'. (int) $at_arr[$j]->getTotalCount() .'
			</td>
		</tr>';
		}
	}
	echo $HTML->listTableBottom();
}

site_project_footer(array());

// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:

?>