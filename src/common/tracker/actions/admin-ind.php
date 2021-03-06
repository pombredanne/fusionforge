<?php
/**
 * FusionForge Tracker Listing
 *
 * Copyright 2000, Quentin Cregan/Sourceforge
 * Copyright 2002-2003, Tim Perdue/GForge, LLC
 * Copyright 2010, FusionForge Team
 * Copyright 2011, Franck Villaume - Capgemini
 * Copyright 2012-2013, Franck Villaume - TrivialDev
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

global $group;

if (getStringFromRequest('post_changes')) {
	$name = getStringFromRequest('name');
	$description = getStringFromRequest('description');
	$email_all = getStringFromRequest('email_all');
	$email_address = getStringFromRequest('email_address');
	$due_period = getStringFromRequest('due_period');
	$use_resolution = getStringFromRequest('use_resolution');
	$submit_instructions = getStringFromRequest('submit_instructions');
	$browse_instructions = getStringFromRequest('browse_instructions');

	if (!forge_check_perm ('tracker_admin', $group->getID())) {
		exit_permission_denied('','tracker');
	}

	if (getStringFromRequest('add_at')) {
		$res=new ArtifactTypeHtml($group);
		if (!$res->create($name,$description,$email_all,$email_address,
			$due_period,$use_resolution,$submit_instructions,$browse_instructions)) {
			exit_error($res->getErrorMessage(),'tracker');
		} else {
			$feedback .= _('Tracker created successfully');
			$feedback .= '<br/>';
			$feedback .= _('Please configure also the roles (by default, it\'s \'No Access\')');
		}
		$group->normalizeAllRoles () ;
	}
}


//
//	Display existing artifact types
//
$atf = new ArtifactTypeFactoryHtml($group);
if (!$atf || !is_object($atf) || $atf->isError()) {
	exit_error(_('Could Not Get ArtifactTypeFactory'),'tracker');
}

// Only keep the Artifacts where the user has admin rights.
$arr = $atf->getArtifactTypes();
$i=0;
for ($j = 0; $j < count($arr); $j++) {
	if (forge_check_perm ('tracker', $arr[$j]->getID(), 'manager')) {
		$at_arr[$i++] =& $arr[$j];
	}
}
// If no more tracker now,
if ($i==0 && $j>0) {
	exit_permission_denied('','tracker');
}

//required params for site_project_header();
$params['group']=$group_id;
$params['toptab']='tracker';
if(isset($page_title)){
	$params['title'] = $page_title;
} else {
	$params['title'] = '';
}

$atf->header( array('title' => _('Trackers Administration')));

if (!isset($at_arr) || !$at_arr || count($at_arr) < 1) {
	echo '<div class="warning">'._('No trackers found').'</div>';
} else {

	echo '
	<p>'._('Choose a data type and you can set up prefs, categories, groups, users, and permissions').'.</p>';

	/*
		Put the result set (list of forums for this group) into a column with folders
	*/
	$tablearr=array(_('Tracker'),_('Description'));
	echo $HTML->listTableTop($tablearr);

	for ($j = 0; $j < count($at_arr); $j++) {
		echo '
		<tr '. $HTML->boxGetAltRowStyle($j) . '>
			<td><a href="'.util_make_url ('/tracker/admin/?atid='. $at_arr[$j]->getID() . '&amp;group_id='.$group_id).'">' .
				html_image("ic/tracker20w.png","20","20") . ' &nbsp;'.
				$at_arr[$j]->getName() .'</a>
			</td>
			<td>'.$at_arr[$j]->getDescription() .'
			</td>
		</tr>';
	}
	echo $HTML->listTableBottom();

	$roadmap_factory = new RoadmapFactory($group);
	$roadmaps = $roadmap_factory->getRoadmaps(true);
	if (!empty($roadmaps)) {
		echo '	<p id="roadmapadminlink">
			<a href="'.util_make_url('/tracker/admin/?group_id='.$group_id.'&admin_roadmap=1').'" >'._('Manage your roadmaps.').'</a>
			</p>';
	}
}

//
//	Set up blank ArtifactType
//

if (forge_check_perm ('tracker_admin', $group->getID())) {
	?><?php echo _('<h3>Create a new tracker</h3><p>You can use this system to track virtually any kind of data, with each tracker having separate user, group, category, and permission lists. You can also easily move items between trackers when needed.</p><p>Trackers are referred to as "Artifact Types" and individual pieces of data are "Artifacts". "Bugs" might be an Artifact Type, whiles a bug report would be an Artifact. You can create as many Artifact Types as you want, but remember you need to set up categories, groups, and permission for each type, which can get time-consuming.</p>') ?>
	<form action="<?php echo getStringFromServer('PHP_SELF').'?group_id='.$group_id; ?>" method="post">
	<input type="hidden" name="add_at" value="y" />
	<p>
	<?php echo _('<strong> Name:</strong> (examples: meeting minutes, test results, RFP Docs)') ?><br />
	<input type="text" name="name" value="" /></p>
	<p>
	<strong><?php echo _('Description') ?>:</strong><br />
	<input type="text" name="description" value="" size="50" /></p>
	<p>
	<strong><?php echo _('Send email on new submission to address') ?>:</strong><br />
	<input type="text" name="email_address" value="" /></p>
	<p>
	<input type="checkbox" name="email_all" value="1" /> <strong><?php echo _('Send email on all changes') ?></strong></p>
	<p>
	<strong><?php echo _('Days till considered overdue') ?>:</strong><br />
	<input type="text" name="due_period" value="30" /></p>
	<p>
	<strong><?php echo _('Days till pending tracker items time out') ?>:</strong><br />
	<input type="text" name="status_timeout" value="14" /></p>
	<p>
	<strong><?php echo _('Free form text for the "submit new item" page') ?>:</strong><br />
	<textarea name="submit_instructions" rows="10" cols="55"></textarea></p>
	<p>
	<strong><?php echo _('Free form text for the "browse items" page') ?>:</strong><br />
	<textarea name="browse_instructions" rows="10" cols="55"></textarea></p>
	<p>
	<input type="submit" name="post_changes" value="<?php echo _('Submit') ?>" /></p>
	</form>
	<?php
}

$atf->footer();

// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:
