<?php
/**
 * Project Management Facility : Admin
 *
 * Copyright 1999/2000, Sourceforge.net Tim Perdue
 * Copyright 2002 GForge, LLC, Tim Perdue
 * Copyright 2010, FusionForge Team
 * Copyright (C) 2011-2012 Alain Peyrat - Alcatel-Lucent
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

require_once '../../env.inc.php';
require_once $gfcommon.'include/pre.php';
require_once $gfwww.'pm/include/ProjectGroupHTML.class.php';
require_once $gfcommon.'pm/ProjectGroupFactory.class.php';
require_once $gfcommon.'pm/ProjectCategory.class.php';

if (!session_loggedin()) {
	exit_not_logged_in();
}

$group_id = getIntFromRequest('group_id');
$group_project_id = getIntFromRequest('group_project_id');

if (!$group_id) {
	exit_no_group();
}

$g = group_get_object($group_id);
if (!$g || !is_object($g)) {
	exit_no_group();
} elseif ($g->isError()) {
	exit_error($g->getErrorMessage(),'pm');
}

$update_cat = getStringFromRequest('update_cat');
$add_cat = getStringFromRequest('add_cat');
$delete = getStringFromRequest('delete');
$id = getIntFromRequest('id');

if (getStringFromRequest('post_changes')) {
	/*
		Update the database
	*/
	$pg = new ProjectGroup($g,$group_project_id);
	if (!$pg || !is_object($pg)) {
		exit_error(_('Unable to create ProjectCategory Object'),'pm');
	} elseif ($pg->isError()) {
		exit_error($pg->getErrorMessage(),'pm');
	}

	if (getStringFromRequest('addproject')) {
		$project_name = getStringFromRequest('project_name');
		$description = getStringFromRequest('description');
		$send_all_posts_to = getStringFromRequest('send_all_posts_to');

		/*
			Add new subproject
		*/
		session_require_perm ('pm_admin', $group_id) ;
		if (!$pg->create($project_name,$description,$send_all_posts_to)) {
			exit_error($pg->getErrorMessage(),'pm');
		} else {
			$feedback .= _('Subproject Inserted');
			$warning_msg .= _("Please configure also the roles (by default, it's 'No Access')");
		}

	} elseif ($add_cat) {
		$name = getStringFromRequest('name');

		/*
			Add a project_category
		*/
		session_require_perm ('pm', $pg->getID(), 'manager') ;

		$pc = new ProjectCategory($pg);
		if (!$pc || !is_object($pc)) {
			exit_error(_('Unable to create ProjectCategory Object'),'pm');
		} else {
			if (!$pc->create($name)) {
				exit_error(_('Error inserting: ').$pc->getErrorMessage(),'pm');
			} else {
				$feedback .= _('Category Inserted');
			}
		}

	} elseif ($update_cat) {
		$id = getIntFromRequest('id');
		$name = getStringFromRequest('name');

		/*
			Update a project_category
		*/
		session_require_perm ('pm', $pg->getID(), 'manager') ;

		$pc = new ProjectCategory($pg,$id);
		if (!$pc || !is_object($pc)) {
			exit_error(_('Unable to create ProjectCategory Object'),'pm');
		} elseif ($pc->isError()) {
			exit_error($pc->getErrorMessage(),'pm');
		} else {
			if (!$pc->update($name)) {
				exit_error(_('Error updating: '.$pc->getErrorMessage()),'pm');
			} else {
				$feedback .= _('Category Updated');
				$update_cat=false;
				$add_cat=true;
			}
		}

	} elseif (getStringFromRequest('update_pg')) {
		$project_name = getStringFromRequest('project_name');
		$description = getStringFromRequest('description');
		$send_all_posts_to = getStringFromRequest('send_all_posts_to');

		/*
			Update a subproject
		*/
		session_require_perm ('pm', $pg->getID(), 'manager') ;

		if (!$pg->update($project_name,$description,$send_all_posts_to)) {
			exit_error($pg->getErrorMessage(),'pm');
		} else {
			$feedback .= _('Subproject Updated successfully');
		}

	} elseif ($delete) {
		$sure = getStringFromRequest('sure');
		$really_sure = getStringFromRequest('really_sure');

		/*
			Delete a subproject
		*/
		session_require_perm ('pm', $pg->getID(), 'manager') ;

		if (!$pg->delete(getStringFromRequest('sure'),getStringFromRequest('really_sure'))) {
			exit_error($pg->getErrorMessage(),'pm');
		} else {
			$feedback .= _('Subproject Successfully Deleted');
			$group_project_id=0;
			$delete=false;
		}
	}
}
/*
	Show UI forms
*/
if ($add_cat && $group_project_id) {
//
//  FORM TO ADD CATEGORIES
//

	$pg = new ProjectGroup($g,$group_project_id);
	if (!$pg || !is_object($pg)) {
		exit_error(_('Unable to create ProjectCategory Object'),'pm');
	} elseif ($pg->isError()) {
		exit_error($pg->getErrorMessage(),'pm');
	}

	session_require_perm ('pm', $pg->getID(), 'manager') ;

	$title = sprintf(_('Add Categories to: %s'), $pg->getName());
	pm_header(array('title'=>$title));

	/*
		List of possible categories for this ArtifactType
	*/
	$result=$pg->getCategories();
	$rows=db_numrows($result);
	if ($result && $rows > 0) {
		$title_arr=array();
		$title_arr[]=_('Id');
		$title_arr[]=_('Title');

		echo $GLOBALS['HTML']->listTableTop ($title_arr);

		for ($i=0; $i < $rows; $i++) {
			echo '<tr '. $GLOBALS['HTML']->boxGetAltRowStyle($i) .'>'.
				'<td>'.db_result($result, $i, 'category_id').'</td>'.
				'<td><a href="'.getStringFromServer('PHP_SELF').'?update_cat=1&amp;id='.
					db_result($result, $i, 'category_id').'&amp;group_id='.$group_id.'&amp;group_project_id='. $pg->getID() .'">'.
					db_result($result, $i, 'category_name').'</a></td></tr>';
		}

		echo $GLOBALS['HTML']->listTableBottom();

	} else {
		echo "\n<p class=\"information\">"._('No categories defined')."</p>";
	}

	?>
	<form action="<?php echo getStringFromServer('PHP_SELF').'?group_id='.$group_id; ?>" method="post">
	<p>
	<input type="hidden" name="add_cat" value="y" />
	<input type="hidden" name="group_project_id" value="<?php echo $pg->getID(); ?>" />
	<label for="name">
		<strong><?php echo _('Category Name') . _(':'); ?></strong>
	</label>
	<input id="name" required="required" type="text" name="name" value="" />
	</p>
	<p class="important"><?php echo _('Once you add a category, it cannot be deleted') ?></p>
	<p><input type="submit" name="post_changes" value="<?php echo _('Submit') ?>" /></p>
	</form>
	<?php

	pm_footer(array());

} elseif ($update_cat && $group_project_id && $id) {

//
//  FORM TO UPDATE CATEGORIES
//
	/*
		Allow modification of a category
	*/

	$pg = new ProjectGroup($g,$group_project_id);
	if (!$pg || !is_object($pg)) {
		exit_error(_('Unable to create ProjectCategory Object'),'pm');
	} elseif ($pg->isError()) {
		exit_error($pg->getErrorMessage(),'pm');
	}

	session_require_perm ('pm', $pg->getID(), 'manager') ;

	$title = sprintf(_('Modify a Category in: %s'), $pg->getName());

	$ac = new ProjectCategory($pg,$id);
	if (!$ac || !is_object($ac)) {
		exit_error(_('Unable to create ProjectCategory Object'),'pm');
	} elseif ($ac->isError()) {
		exit_error($ac->getErrorMessage(),'pm');
	} else {
		pm_header(array ('title'=>$title));
		?>
		<p class="information"><?php echo _('It is not recommended that you change the category name because other things are dependent upon it. When you change the category name, all related items will be changed to the new name.')?></p>
		<form action="<?php echo getStringFromServer('PHP_SELF').'?group_id='.$group_id; ?>" method="post">
		<p>
		<input type="hidden" name="update_cat" value="y" />
		<input type="hidden" name="id" value="<?php echo $ac->getID(); ?>" />
		<input type="hidden" name="group_project_id" value="<?php echo $pg->getID(); ?>" />
		<label for="name">
			<strong><?php echo _('Category Name') . _(': '); ?></strong>
		</label>
		<input id="name" required="required" type="text" name="name" value="<?php echo $ac->getName(); ?>" />
		</p>
		<p><input type="submit" name="post_changes" value="<?php echo _('Submit') ?>" /></p>
		</form>
		<?php
	}

	pm_footer(array());

} elseif (getStringFromRequest('addproject')) {
	/*
		Create a new subproject
	*/
	session_require_perm ('pm_admin', $group_id) ;

	pm_header(array ('title'=>_('Add a new subproject')));

	?>
	<p><?php echo _('Add a new subproject to the Tasks. <strong>This is different than adding a task to a subproject.</strong>') ?></p>

	<form action="<?php echo getStringFromServer('PHP_SELF')."?group_id=$group_id"; ?>" method="post">
	<p>
	<input type="hidden" name="addproject" value="y" />
	<input type="hidden" name="post_changes" value="y" />
	<strong><?php echo _('New Subproject Name').utils_requiredField()?></strong>
	<br />
	<input type="text" name="project_name" value="" size="15" maxlength="30" />
	<p />
	<strong><?php echo _('Description').utils_requiredField() ?></strong><br />
	<input type="text" name="description" value="" size="40" maxlength="80" />
	<p />
	<strong><?php echo _('Send All Updates To')?>:</strong><br />
	<input type="text" name="send_all_posts_to" value="" size="40" maxlength="80" /><p />
	<input type="submit" name="submit" value="<?php echo _('Submit') ?>" />
	</form>
	<?php
	pm_footer(array());

} elseif (getStringFromRequest('update_pg') && $group_project_id) {

	$pg = new ProjectGroup($g,$group_project_id);
	if (!$pg || !is_object($pg)) {
		exit_error(_('Could Not Get ProjectGroup'),'pm');
	} elseif ($pg->isError()) {
		exit_error($pg->getErrorMessage(),'pm');
	}
	session_require_perm ('pm', $pg->getID(), 'manager') ;

	pm_header(array('title'=>_('Change Tasks Status')));

	?>
	<p><?php echo _('You can modify an existing subproject using this form. Please note that private subprojects can still be viewed by members of your project, but not the general public.') ?></p>

	<form action="<?php echo getStringFromServer('PHP_SELF').'?group_id='.$group_id; ?>" method="post">
	<input type="hidden" name="post_changes" value="y" />
	<input type="hidden" name="update_pg" value="y" />
	<input type="hidden" name="group_project_id" value="<?php echo $pg->getID(); ?>" />
	<table class="infotable">
	<tr>
		<td>
		<label for="project_name">
			<?php echo _('Subproject Name')._(':'); ?>
		</label>
		</td>
		<td>
			<input id="project_name" type="text" name="project_name" value="<?php echo $pg->getName() ?>" />
		</td>
	</tr>
	<tr>
		<td>
		<label for="description">
			<?php echo _('Description')._(':'); ?>
		</label>
		</td>
		<td>
			<input id="description" type="text" name="description" value="<?php echo $pg->getDescription(); ?>" size="40" maxlength="80" />
		</td>
	</tr>
	<tr>
		<td>
		<label for="send_all_posts_to">
			<?php echo _('Send All Updates To')._(':'); ?>
		</label>
		</td>
		<td>
			<input id="send_all_posts_to" type="text" name="send_all_posts_to" value="<?php echo $pg->getSendAllPostsTo(); ?>" size="40" maxlength="80" />
		</td>
	</tr>
	<tr>
		<td colspan="2">
			<input type="submit" name="submit" value="<?php echo _('Update') ?>" />
		</td>
	</tr>
	</table>
	</form>
	<?php

	pm_footer(array());

} elseif ($delete && $group_project_id) {

	$pg = new ProjectGroup($g,$group_project_id);
	if (!$pg || !is_object($pg)) {
		exit_error(_('Could Not Get ProjectGroup'),'pm');
	} elseif ($pg->isError()) {
		exit_error($pg->getErrorMessage(),'pm');
	}

	session_require_perm ('pm', $pg->getID(), 'manager') ;

	pm_header(array('title'=>_('Permanently delete this subproject and all its data')));

	?>
	<form action="<?php echo getStringFromServer('PHP_SELF').'?group_id='.$group_id.'&amp;group_project_id='.$group_project_id; ?>" method="post">
	<p>
	<input type="hidden" name="post_changes" value="y" />
	<input type="hidden" name="delete" value="y" />
	<?php echo _('You are about to permanently and irretrievably delete this subproject and all its related data!'); ?>
	</p>
	<p>
		<input id="sure" type="checkbox" name="sure" value="1" />
		<label for="sure">
			<?php echo _('I am Sure') ?>
		</label>
	</p>
	<p>
		<input id="really_sure" type="checkbox" name="really_sure" value="1" />
		<label for="really_sure">
			<?php echo _('I am Really Sure'); ?>
		</label>
	</p>
	<p>
		<input type="submit" name="post_changes" value="<?php echo _('Permanently delete this subproject and all its data') ?>" />
	</p>
	</form>
	<?php

	pm_footer(array());

} else {

	$pgf = new ProjectGroupFactory($g);
	if (!$pgf || !is_object($pgf)) {
		exit_error(_('Could Not Get Factory'),'pm');
	} elseif ($pgf->isError()) {
		exit_error($pgf->getErrorMessage(),'pm');
	}

	/*
		Show main page
	*/
	pm_header(array('title'=>_('Tasks Administration')));

	//
	//	Show link to create new subproject
	//
	if (forge_check_perm ('pm_admin', $group_id)) {
		?>
		<p />
		<a href="<?php echo getStringFromServer('PHP_SELF').'?group_id='.$group_id; ?>&amp;addproject=1"><?php echo _('Add a Subproject') ?></a><br />
		<?php echo _('Add a subproject, which can contain a set of tasks. This is different than creating a new task.') ?>
		<p />
		<?php
	}

	$pg_arr = $pgf->getProjectGroups();

	if (count($pg_arr) < 1 || $pg_arr == false) {
		echo '<p class="information">' . _('No Subprojects Found in this Project') . '</p>';
		echo '<p>' . _('You may add new Subprojects using the “Add a Subproject” link above.') . '</p>';
		echo db_error();
	} else {
		for ($i=0; $i<count($pg_arr); $i++) {
			echo '<a href="'. getStringFromServer('PHP_SELF').'?group_id='.$group_id.'&amp;group_project_id='.$pg_arr[$i]->getID().'&amp;update_pg=1">'._('Edit/Update Subproject').': <strong>'.$pg_arr[$i]->getName().'</strong></a><p />';
		}

	}

	pm_footer(array());
}
