<?php
/**
 * projects_hierarchyPlugin Class
 *
 * Copyright 2006 (c) Fabien Regnier - Sogeti
 * Copyright 2010-2011, Franck Villaume - Capgemini
 * Copyright 2012-2013, Franck Villaume - TrivialDev
 * Copyright 2013, French Ministry of National Education
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

class projects_hierarchyPlugin extends Plugin {
	function __construct() {
		$this->Plugin();
		$this->name = 'projects-hierarchy';
		$this->text = _('Project Hierarchy'); // To show in the tabs, use...
		$this->_addHook('groupisactivecheckbox'); // The "use ..." checkbox in editgroupinfo
		$this->_addHook('groupisactivecheckboxpost');
		$this->_addHook('hierarchy_views'); // include specific views
		$this->_addHook('display_hierarchy'); // to see the tree of projects
		$this->_addHook('group_delete'); // clean tables on delete
		$this->_addHook('project_admin_plugins'); // to show up in the admin page fro group
		$this->_addHook('site_admin_option_hook'); // to provide a link to the site wide administrative pages of plugin
		$this->_addHook('display_hierarchy_submenu'); // to display a submenu in software map if projects-hierarchy plugin is used
		$this->_addHook('docmansearch_has_hierarchy'); // used by the search menu in docman
		$this->_addHook('clone_project_from_template'); // add project in database
	}

	function CallHook($hookname, &$params) {
		global $G_SESSION, $HTML;
		$returned = false;
		switch($hookname) {
			case "display_hierarchy": {
				if ($this->displayHierarchy()) {
					$returned = true;
				}
				break;
			}
			case "hierarchy_views": {
				global $gfplugins;
				require_once $gfplugins.$this->name.'/include/hierarchy_utils.php';
				$group_id = $params[0];
				$project = group_get_object($group_id);
				if ($project->usesPlugin($this->name)) {
					switch ($params[1]) {
						case "admin":
						case "docman":
						case "home": {
							include($gfplugins.$this->name.'/view/'.$params[1].'_project_link.php');
							$returned = true;
							break;
						}
						default: {
							break;
						}
					}
				}
				break;
			}
			case "group_delete": {
				$this->remove($params['group_id']);
				$returned = true;
				break;
			}
			case "site_admin_option_hook": {
				// Use this to provide a link to the site wide administrative pages for your plugin
				echo '<li>'.$this->getAdminOptionLink().'</li>';
				$returned = true;
				break;
			}
			case "project_admin_plugins": {
				// this displays the link in the project admin options page to it's administration
				$group_id = $params['group_id'];
				$group = group_get_object($group_id);
				if ($group->usesPlugin($this->name)) {
					echo '<p>';
					echo util_make_link('/plugins/'.$this->name.'/?group_id='.$group_id.'&type=admin&pluginname='.$this->name, _('Hierarchy Admin'), array('class'=>'tabtitle', 'title'=>_('Configure the projets-hierarchy plugin (docman, tree, delegate, globalconf features)')));
					echo '</p>';
				}
				$returned = true;
				break;
			}
			case "display_hierarchy_submenu": {
				$globalConf = $this->getGlobalconf();
				if ($globalConf['tree']) {
					// Use to display a submenu in software map page if at least one project has a valid relationship
					$res1 = db_query_params('SELECT g.group_name FROM plugins p, group_plugin gp, groups g WHERE plugin_name = $1 and gp.group_id = g.group_id and p.plugin_id = gp.plugin_id',
								array($this->name));
					if ($res1) {
						if (db_numrows($res1) > 0) {
							$res2 = db_query_params('SELECT count(*) as used FROM plugin_projects_hierarchy_relationship where status = $1',
										array('t'));
							if ($res2)
								$row = db_fetch_array($res2);
							if ($row['used']) {
								$hierarchy_used = true;
							}
						}
					}
					if (isset($hierarchy_used)) {
						$hierarMenuTitle[] = _('Per Category');
						$hierarMenuTitle[] = _('Per Hierarchy');
						$hierarMenuAttr[] = array('title' => _('Browse per category the available projects. Some projects might not appear here they do not choose any categories'), 'class' => 'tabtitle-nw');
						$hierarMenuAttr[] = array('title' => _('Browse per hierarchy. Projects can share relationship between projects, as father and sons'), 'class' => 'tabtitle');
						$hierarMenuUrl[] = '/softwaremap/trove_list.php?cat=c';
						$hierarMenuUrl[] = '/softwaremap/trove_list.php?cat=h';
						echo ($HTML->subMenu($hierarMenuTitle, $hierarMenuUrl, $hierarMenuAttr));
					}
				}
				$returned = true;
				break;
			}
			case "docmansearch_has_hierarchy": {
				if ($params['includesubprojects']) {
					$group_id = $params['group_id'];
					$group = group_get_object($group_id);
					if ($group->usesPlugin($this->name)) {
						$arrayChilds= $this->getFamily($group_id, 'child', true, 'validated');
						foreach ( $arrayChilds as $childId ) {
							$params['qpa'] = db_construct_qpa($params['qpa'], ' OR group_id = $1', array($childId));
						}
					}
				}
				$returned = true;
				break;
			}
			case "clone_project_from_template": {
				$project = $params['project'];
				if ($project->usesPlugin($this->name)) {
					$this->add($project->getID());
					$templateConfArr = $this->getConf($params['template']->getID());
					$this->updateConf($project->getID(), $templateConfArr);
				}
				break;
			}
		}
		return $returned;
	}

	function displayHierarchy() {
		$tree = $this->buildTree();
		$displayTree = $this->affTree($tree, 0);
		$this->dTreeJS();
		echo $displayTree;
		return true;
	}

	function dTreeJS() {
		echo '<link rel="StyleSheet" href="/plugins/projects-hierarchy/dtree.css" type="text/css" />
			<script type="text/javascript" src="/plugins/projects-hierarchy/dtree.js"></script>';
	}

	function buildTree() {
		global $project_name;
		$res = db_query_params('select p1.group_id as father_id,p1.unix_group_name as father_unix_name,p1.group_name as father_name,p2.group_id as son_id,p2.unix_group_name as son_unix_name,p2.group_name as son_name
					from groups as p1,groups as p2,plugin_projects_hierarchy_relationship
					where p1.group_id=plugin_projects_hierarchy_relationship.project_id
					and p2.group_id=plugin_projects_hierarchy_relationship.sub_project_id
					and plugin_projects_hierarchy_relationship.status=$1
					and (select tree from plugin_projects_hierarchy where project_id = p2.group_id)
					order by father_name, son_name',
					array('t'));
		echo db_error();
		// construction du tableau associatif
		// key = name of the father
		// value = list of sons
		$tree = array();
		while ($row = db_fetch_array($res)) {
			if (forge_check_perm('project_read', $row['father_id']) && forge_check_perm('project_read', $row['son_id'])) {
				$tree[$row['father_id']][] = $row['son_id'];
				//get the unix name of the project
				$project_name[$row['father_id']][0] = $row['father_name'];
				$project_name[$row['son_id']][0] = $row['son_name'];
				$project_name[$row['father_id']][1] = $row['father_unix_name'];
				$project_name[$row['son_id']][1] = $row['son_unix_name'];
			}
		}
		return $tree;
	}

	function affTree($tree, $lvl) {
		global $project_name;
		$returnTree = '';

		$arbre = array();
		$cpt_pere = 0;

		while (list($key, $sons) = each($tree)) {
			// Really don't know why there is a warning there, and added @
			if(@!$arbre[$key] != 0){
				$arbre[$key] = 0;
			}
			$cpt_pere = $key;
			foreach ($sons as $son) {
				$arbre[$son] = $cpt_pere;
			}
		}

		if (sizeof($arbre)) {
			$returnTree .= '<table ><tr><td>';
			$returnTree .= '<script type="text/javascript">';
			$returnTree .= 'd = new dTree(\'d\');';
			$returnTree .= 'd.add(0,-1,\'Project Hierarchy Tree\');';
			reset($arbre);
			//construction automatique de l'arbre format : (num_fils, num_pere,nom,nom_unix)
			while (list($key2, $sons2) = each($arbre)) {
				$returnTree .= "d.add('".$key2."','".$sons2."','".$project_name[$key2][0]."','".util_make_url( '/projects/'.$project_name[$key2][1] .'/', $project_name[$key2][1] ) ."');";
			}

			$returnTree .= 'document.write(d);';
			$returnTree .= '</script>';
			$returnTree .= '</td></tr></table>';
		}
		return $returnTree;
	}

	/**
	 * getFamily - find the children or parent group_id of this project.
	 *
	 * @param	integer	group_id to search for
	 * @param	string	parent or child ?
	 * @param	boolean	recurcive or not ?
	 * @param	string	validated or pending or any relation ?
	 * @return	array	array of arrays with group_id of parent or childs
	 * @access	public
	 */
	function getFamily($group_id, $order, $deep = false, $status = 'any') {
		$localFamily = array();
		switch ($status) {
			case "validated": {
				$statusCondition = 't';

				break;
			}
			case "pending": {
				$statusCondition = 'f';
				break;
			}
			case "any":
			default: {
				break;
			}
		}
		switch ($order) {
			case "parent": {
				$qpa = db_construct_qpa(false, 'SELECT project_id as id FROM plugin_projects_hierarchy_relationship
							WHERE sub_project_id = $1', array($group_id));
				if (isset($statusCondition))
					$qpa = db_construct_qpa($qpa, ' AND status = $1', array($statusCondition));

				$res = db_query_qpa($qpa);
				break;
			}
			case "child": {
				$qpa = db_construct_qpa(false, 'SELECT sub_project_id as id FROM plugin_projects_hierarchy_relationship
							WHERE project_id = $1', array($group_id));
				if (isset($statusCondition))
					$qpa = db_construct_qpa($qpa, ' AND status = $1', array($statusCondition));

				$res = db_query_qpa($qpa);
				break;
			}
			default: {
				return $localFamily;
				break;
			}
		}
		if ($res && db_numrows($res) > 0) {
			while ($arr = db_fetch_array($res)) {
				$localFamily[] = $arr['id'];
			}
		}

		if ($deep) {
			$nextFamily = array();
			for ( $i = 0; $i < count($localFamily); $i++) {
				$nextFamily = $this->getFamily($localFamily[$i], $order, $deep, $status);
			}
		}
		if (isset($nextFamily) && sizeof($nextFamily))
			$localFamily = array_merge($localFamily, $nextFamily);

		return $localFamily;
	}

	/**
	 * getDocmanStatus - returns the docman status for this project
	 *
	 * @param	integer	group_id
	 * @return	boolean	true/false
	 * @access	public
	 */
	function getDocmanStatus($group_id) {
		$res = db_query_params('SELECT docman FROM plugin_projects_hierarchy WHERE project_id = $1 limit 1',
					array($group_id));
		if (!$res)
			return false;

		$resArr = db_fetch_array($res);
		if ($resArr['docman'] == 't')
			return true;

		return false;
	}

	/**
	 * setDocmanStatus - allow parent to browse your document manager and allow yourself to select your childs to be browsed.
	 *
	 * @param	integer	your groud_id
	 * @param	boolean	the status to set
	 * @return	boolean	success or not
	 */
	function setDocmanStatus($group_id, $status = 0) {
		$res = db_query_params('UPDATE plugin_projects_hierarchy set docman = $1 WHERE project_id = $2',
					array($status, $group_id));

		if (!$res)
			return false;

		return true;
	}

	/**
	 * redirect - encapsulate session_redirect to handle correctly the redirection URL
	 *
	 * @param	string	usually http_referer from $_SERVER
	 * @param	string	type of feedback : error, warning, feedback
	 * @param	string	the message of feedback
	 * @access	public
	 */
	function redirect($http_referer, $type, $message) {
		switch ($type) {
			case 'warning_msg':
			case 'error_msg':
			case 'feedback': {
				break;
			}
			default: {
				$type = 'error_msg';
			}
		}
		$url = util_find_relative_referer($http_referer);
		if (strpos($url,'?')) {
			session_redirect($url.'&'.$type.'='.urlencode($message));
		}
		session_redirect($url.'?'.$type.'='.urlencode($message));
	}

	/**
	 * override default groupisactivecheckboxpost function for init value in db
	 */
	function groupisactivecheckboxpost(&$params) {
		// this code actually activates/deactivates the plugin after the form was submitted in the project edit public info page
		$group = group_get_object($params['group']);
		$flag = strtolower('use_'.$this->name);
		if ( getIntFromRequest($flag) == 1 ) {
			if ($this->add($group->getID())) {
				$group->setPluginUse($this->name);
				return true;
			}
		} else {
			if ($this->remove($group->getID())) {
				$group->setPluginUse($this->name, false);
				return true;
			}
		}
		return false;
	}

	/**
	 * add - add a new group_id using the plugin
	 *
	 * @param	integer	group_id
	 * @return	boolean	true on success
	 * @access	public
	 */
	function add($group_id) {
		if (!$this->exists($group_id)) {
			$globalConf = $this->getGlobalconf();
			$res = db_query_params('INSERT INTO plugin_projects_hierarchy (project_id, tree, docman, delegate) VALUES ($1, $2, $3, $4)', array($group_id, (int)$globalConf['tree'], (int)$globalConf['docman'], (int)$globalConf['delegate']));
			if (!$res)
				return false;
		}
		return true;
	}

	/**
	 * remove - remove group_id using the plugin
	 *
	 * @param	integer	group_id
	 * @return	boolean	true on success
	 * @access	public
	 */
	function remove($group_id) {
		if ($this->exists($group_id)) {
			db_begin();
			$res = db_query_params('DELETE FROM plugin_projects_hierarchy where project_id = $1', array($group_id));
			if (!$res) {
				db_rollback();
				return false;
			}

			$res = db_query_params('DELETE FROM plugin_projects_hierarchy_relationship where project_id = $1 OR sub_project_id = $1',
						array($group_id));
			if (!$res) {
				db_rollback();
				return false;
			}
			db_commit();
		}
		return true;
	}

	/**
	 * addChild - add a new child to this project
	 *
	 * @param	integer	group_id
	 * @param	integer	sub_group_id
	 * @return	boolean	true on success
	 * @access	public
	 */
	function addChild($project_id, $sub_project_id) {
		if ($this->exists($project_id) && $this->exists($sub_project_id)) {
			if (!$this->hasRelation($project_id, $sub_project_id)) {
				$res = db_query_params('INSERT INTO plugin_projects_hierarchy_relationship (project_id, sub_project_id)
							VALUES ($1, $2)', array($project_id, $sub_project_id));
				if (!$res)
					return false;

				return true;
			}
		}
		return false;
	}

	/**
	 * removeChild - remove a child to this project
	 *
	 * @param	integer	group_id
	 * @param	integer	sub_group_id
	 * @return	boolean	true on success
	 * @access	public
	 */
	function removeChild($project_id, $sub_project_id) {
		if ($this->exists($project_id) && $this->exists($sub_project_id)) {
			if ($this->hasRelation($project_id, $sub_project_id)) {
				$res = db_query_params('DELETE FROM plugin_projects_hierarchy_relationship
							WHERE project_id = $1 AND sub_project_id = $2',
							array($project_id, $sub_project_id));
				if (!$res)
					return false;

				return true;
			}
		}
		return false;
	}

	/**
	 * removeParent - remove a parent to this project
	 *
	 * @param	integer	group_id
	 * @param	integer	sub_group_id
	 * @return	boolean	true on success
	 * @access	public
	 */
	function removeParent($project_id, $sub_project_id) {
		if ($this->exists($project_id) && $this->exists($sub_project_id)) {
			if ($this->hasRelation($project_id, $sub_project_id)) {
				$res = db_query_params('DELETE FROM plugin_projects_hierarchy_relationship
							WHERE project_id = $1 AND sub_project_id = $2',
							array($sub_project_id, $project_id));
				if (!$res)
					return false;

				return true;
			}
		}
		return false;
	}

	/**
	 * hasRelation - check if there is a relation child->parent or parent->child between 2 projects
	 *
	 * @param	integer	group_id
	 * @param	integer	sub_group_id
	 * @return	boolean	true on success
	 * @access	public
	 */
	function hasRelation($project_id, $sub_project_id) {
		if ($this->exists($project_id) && $this->exists($sub_project_id)) {
			$res = db_query_params('SELECT * FROM plugin_projects_hierarchy_relationship
						WHERE ( project_id = $1 AND sub_project_id = $2 )
						OR ( project_id = $2 AND sub_project_id = $1 )',
						array($project_id, $sub_project_id));
			if ($res && db_numrows($res) == 1 )
				return true;
		}
		return false;
	}

	/**
	 * validateRelationship - validate or reject a relation between 2 projects
	 *
	 * @param	integer	group_id
	 * @param	integer	sub_group_id
	 * @param	string	type of relation
	 * @param	integer	status of the relation
	 * @return	boolean	true on success
	 * @access	public
	 */
	function validateRelationship($project_id, $sub_project_id, $relation, $status) {
		if ($this->exists($project_id) && $this->exists($sub_project_id)) {
			if ($this->hasRelation($project_id, $sub_project_id)) {
				if ($status) {
					$qpa = db_construct_qpa(false, 'UPDATE plugin_projects_hierarchy_relationship SET status = $1
								WHERE ', array($status));
					switch ($relation) {
						case "parent": {
							$qpa = db_construct_qpa($qpa, 'project_id = $1 AND sub_project_id = $2',
										array($sub_project_id, $project_id));
							break;
						}
						case "child": {
							$qpa = db_construct_qpa($qpa, 'project_id = $1 AND sub_project_id = $2',
										array($project_id, $sub_project_id));
							break;
						}
						default: {
							return false;
							break;
						}
					}
					$res = db_query_qpa($qpa);
					if (!$res)
						return false;

					if (db_affected_rows($res))
						return true;
				} else {
					$qpa = db_construct_qpa(false, 'DELETE FROM plugin_projects_hierarchy_relationship WHERE ');
					switch ($relation) {
						case "parent": {
							$qpa = db_construct_qpa($qpa, 'project_id = $1 AND sub_project_id = $2',
										array($sub_project_id, $project_id));
							break;
						}
						case "child": {
							$qpa = db_construct_qpa($qpa, 'project_id = $1 AND sub_project_id = $2',
										array($project_id, $sub_project_id));
							break;
						}
						default: {
							return false;
							break;
						}
					}
					$res = db_query_qpa($qpa);
					if (!$res)
						return false;

					if (db_affected_rows($res))
						return true;
				}
			}
		}
		return false;
	}

	/**
	 * exists - if this project use the plugin
	 *
	 * @param	integer	group_id
	 * @return	boolean	true on success
	 * @access	public
	 */
	function exists($group_id) {
		$res = db_query_params('SELECT project_id FROM plugin_projects_hierarchy WHERE project_id = $1', array($group_id));
		if (!$res)
			return false;

		if (db_numrows($res))
			return true;

		return false;
	}

	/**
	 * getAdminOptionLink - return the admin link url
	 *
	 * @return	string	html url
	 * @access	public
	 */
	function getAdminOptionLink() {
		return util_make_link('/plugins/'.$this->name.'/?type=globaladmin',_('Global Hierarchy admin'), array('class'=>'tabtitle', 'title'=>_('Configure the projets-hierarchy plugin (docman, tree, delegate, globalconf features)')));
	}

	/**
	 * getHeader - initialize header and js
	 * @param	string	type : user, project (aka group)
	 * @param       array   params
	 * @return	bool	success or not
	 */
	function getHeader($type, $params=NULL) {
		global $gfplugins;
		$returned = false;
		switch ($type) {
			case 'globaladmin': {
				session_require_global_perm('forge_admin');
				global $gfwww;
				require_once($gfwww.'admin/admin_utils.php');
				site_admin_header(array('title'=>_('Site Global Hierarchy Admin'), 'toptab'=>''));
				$returned = true;
				break;
			}
			case 'admin':
			default: {
                                site_project_header($params);
				$returned = true;
				break;
			}
		}
		return $returned;
	}

	/**
	 * getGlobalAdminView - display the global configuration view
	 *
	 * @return	boolean	True
	 * @access	public
	 */
	function getGlobalAdminView() {
		global $gfplugins, $use_tooltips;
		include $gfplugins.$this->name.'/view/admin/viewGlobalConfiguration.php';
		return true;
	}

	/**
	 * getGlobalconf - return the global configuration defined at forge level
	 *
	 * @return	array	the global configuration array
	 */
	function getGlobalconf() {
		$resGlobConf = db_query_params('SELECT * from plugin_projects_hierarchy_global',array());
		if (!$resGlobConf) {
			return false;
		}

		$row = db_numrows($resGlobConf);

		if ($row == null || count($row) > 2) {
			return false;
		}

		$resArr = db_fetch_array($resGlobConf);
		$returnArr = array();

		foreach($resArr as $column => $value) {
			if ($value == 't') {
				$returnArr[$column] = true;
			} else {
				$returnArr[$column] = false;
			}
		}

		return $returnArr;
	}

	/**
	 * getConf - return the configuration defined at project level
	 *
	 * @param	integer	the group id
	 * @return	array	the configuration array
	 */
	function getConf($project_id) {
		$resConf = db_query_params('SELECT * from plugin_projects_hierarchy where project_id=$1',array($project_id));
		if (!$resConf) {
			return false;
		}

		$row = db_numrows($resConf);

		if ($row == null || count($row) > 2) {
			return false;
		}

		$resArr = db_fetch_array($resConf);
		$returnArr = array();

		foreach($resArr as $column => $value) {
			if ($value == 't') {
				$returnArr[$column] = 1;
			} else {
				$returnArr[$column] = 0;
			}
		}

		return $returnArr;
	}


	function getProjectAdminView() {
		global $gfplugins, $use_tooltips;
		include $gfplugins.$this->name.'/view/admin/viewProjectConfiguration.php';
		return true;
	}

	/**
	 * getFooter - display footer
	 */
	function getFooter($type) {
		global $gfplugins;
		$returned = false;
		switch ($type) {
			case 'globaladmin': {
				session_require_global_perm('forge_admin');
				site_admin_footer(array());
				break;
			}
			case 'admin':
			default: {
				site_project_footer(array());
				break;
			}
		}
		return $returned;
	}

	/**
	 * updateGlobalConf - update the global configuration in database
	 *
	 * @param	array	configuration array (tree, docman, delegate)
	 * @return	bool	true on success
	 */
	function updateGlobalConf($confArr) {
		if (!isset($confArr['tree']) || !isset($confArr['docman']) || !isset($confArr['delegate']))
			return false;

		$res = db_query_params('truncate plugin_projects_hierarchy_global',array());
		if (!$res)
			return false;

		$res = db_query_params('insert into plugin_projects_hierarchy_global (tree, docman, delegate)
					values ($1, $2, $3)',
					array(
						$confArr['tree'],
						$confArr['docman'],
						$confArr['delegate']
					));
		if (!$res)
			return false;

		return true;
	}

	/**
	 * updateConf - update the configuration in database for this project
	 *
	 * @param	array	configuration array (tree, docman, delegate)
	 * @return	bool	true on success
	 */
	function updateConf($project_id, $confArr) {
		if (!isset($confArr['tree']) || !isset($confArr['docman']) || !isset($confArr['delegate']) || !isset($confArr['globalconf']))
			return false;

		$res = db_query_params('update plugin_projects_hierarchy
					set (tree, docman, delegate, globalconf) = ($1, $2, $3, $4)
					where project_id = $5',
					array(
						$confArr['tree'],
						$confArr['docman'],
						$confArr['delegate'],
						$confArr['globalconf'],
						$project_id
					));
		if (!$res)
			return false;

		return true;
	}

	/**
	 * son_box - display a select box for son selection
	 *
	 * @param	integer	group_id
	 * @param	string
	 * @param	string	selected value
	 * @return	string	html box
	 * @access	public
	 */
	function son_box($group_id, $name, $selected = 'xzxzxz') {
		$sons = $this->getFamily($group_id, 'child', true, 'any');
		$parent = $this->getFamily($group_id, 'parent', true, 'any');
		$family = array_merge($parent, $sons);
		$son = db_query_params('SELECT group_id, group_name FROM groups
					WHERE status = $1
					AND group_id != $2
					AND group_id <> ALL ($3)
					AND group_id IN (select group_id from group_plugin,plugins where group_plugin.plugin_id = plugins.plugin_id and plugins.plugin_name = $4)
					AND group_id NOT IN (select sub_project_id from plugin_projects_hierarchy_relationship);',
					array('A',
						$group_id,
						db_int_array_to_any_clause($family),
						$this->name));
		return html_build_select_box($son, $name, $selected, false);
	}

	/**
	 * isUsed - is this plugin used by other projects than the current family ?
	 *
	 * @return	bool	yes or no
	 */
	function isUsed($group_id) {
		$sons = $this->getFamily($group_id, 'child', true, 'any');
		$parent = $this->getFamily($group_id, 'parent', true, 'any');
		$family = array_merge($parent, $sons);
		$used =false;
		$res1 = db_query_params('SELECT g.group_name FROM plugins p, group_plugin gp, groups g
					WHERE plugin_name = $1
					AND gp.group_id = g.group_id
					AND p.plugin_id = gp.plugin_id
					AND g.group_id <> ALL ($2)',
					array($this->name, db_int_array_to_any_clause($family))
					);
		if ($res1) {
			// we want at least more than ourself
			if (db_numrows($res1) > 1) {
				$used = true;
			}
		}
		return $used;
	}

	/**
	 * is_child - to verif if project already has a parent
	 *
	 * @param	integer	group_id
	 * @return	bool	true on success
	 * @access	public
	 */
	function is_child($group_id) {
		if (count($this->getFamily($group_id, 'parent', true, 'any'))>0)
			return true;
		else
			return false;
	}
}
// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:
