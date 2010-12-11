<?php
/**
 * MantisBPlugin Class
 *
 * Copyright 2009, Fabien Dubois - Capgemini
 * Copyright 2009-2010, Franck Villaume - Capgemini
 * http://fusionforge.org
 *
 * This file is part of FusionForge.
 *
 * FusionForge is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * FusionForge is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with FusionForge; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307 USA
 */

/*
 * @todo :	need a massive cleanup
 *		deal correctly with password (might need direct db access ?)
 *		limit non SOAP call aka direct db access to mantisbt
 */

require_once 'include/database-pgsql.php';

class MantisBTPlugin extends Plugin {
	function MantisBTPlugin () {
		$this->Plugin() ;
		$this->name = "mantisbt" ;
		$this->text = "MantisBT" ; // To show in the tabs, use...
		$this->_addHook('user_personal_links'); //to make a link to the user's personal part of the plugin
		$this->_addHook('usermenu');
		$this->_addHook('groupmenu'); // To put into the project tabs
		$this->_addHook('groupisactivecheckbox'); // The "use ..." checkbox in editgroupinfo
		$this->_addHook('groupisactivecheckboxpost'); //
		$this->_addHook('userisactivecheckbox'); // The "use ..." checkbox in user account
		$this->_addHook('userisactivecheckboxpost'); //
		$this->_addHook('project_admin_plugins'); // to show up in the admin page fro group
		$this->_addHook('change_cal_permission');
		$this->_addHook('change_cal_mail');
		$this->_addHook('add_cal_link_father');
		$this->_addHook('del_cal_link_father');
		$this->_addHook('group_approved');
		$this->_addHook('group_delete');
		$this->_addHook('group_update');
	}

	function CallHook ($hookname, &$params) {
		global $G_SESSION, $HTML;
		$returned = false;
		switch ($hookname) {
			case "usermenu": {
				$text = $this->text; // this is what shows in the tab
				if ($G_SESSION->usesPlugin($this->name)) {
					$param = '?type=user&id=' . $G_SESSION->getId() . '&pluginname=' . $this->name; // we indicate the part we're calling is the user one
					echo $HTML->PrintSubMenu(array($text), array('/plugins/mantisbt/index.php' . $param));
				}
				$returned = true;
				break;
			}
			case "groupmenu": {
				$group_id=$params['group'];
				$project = group_get_object($group_id);
				if (!$project || !is_object($project) || $project->isError() || !$project->isProject()) {
					return;
				}
				if ($project->usesPlugin($this->name)) {
					$params['TITLES'][]=$this->text;
					$params['DIRS'][]='/plugins/' . $this->name . '/?type=group&id=' . $group_id . '&pluginname=' . $this->name;
				}
				if ($params['toptab'] == $this->name) {
					$params['selected']=(count($params['TITLES'])-1);
				}
				$returned = true;
				break;
			}
			case "user_personal_links": {
				// this displays the link in the user's profile page to it's personal MantisBT (if you want other sto access it, youll have to change the permissions in the index.php
				$userid = $params['user_id'];
				$user = user_get_object($userid);
				$text = $params['text'];
				//check if the user has the plugin activated
				if ($user->usesPlugin($this->name)) {
					echo '<p>';
					echo util_make_link ("/plugins/mantisbt/index.php?id=$userid&type=user&pluginname=".$this->name,
					_('View Personal MantisBT')
					);
					echo '</p>';
				}
				$returned = true;
				break;
			}
			case "project_admin_plugins": {
				// this displays the link in the project admin options page to it's  MantisBT administration
				$group_id = $params['group_id'];
				$group = group_get_object($group_id);
				if ($group->usesPlugin ($this->name)) {
					echo '<p>';
					echo util_make_link ("/plugins/mantisbt/index.php?id=$group_id&type=admin&pluginname=".$this->name,
					_('View Admin MantisBT')
					);
					echo '</p>';
				}
				$returned = true;
				break;
			}
			case "group_approved": {
				$group_id = $params['1'];
				$group = group_get_object($group_id);
				if ($group->usesPlugin($this->name)) {
					if (!$this->isProjectMantisCreated($group->getID())) {
						if($this->addProjectMantis($group->getID())) {
							$members = array();
							foreach($group->getMembers() as $member) {
								$members[] = $member->getUnixName();
								if($this->updateUsersProjectMantis($group->getID(), $members)) {
									$group->setPluginUse($this->name);
									$returned = true;
								};
							}
						}
					} else {
						$returned = true;
					}
				} else {
					$returned = true;
				}
				break;
			}
			case "change_cal_permission": {
				// mise a jour des utilisateurs avec les roles
				$group_id=$params[1];
				$group = group_get_object($group_id);
				$members = array ();
				foreach($group->getMembers() as $member){
					$members[] = $member->data_array['user_name'];
				}
				$this->updateUsersProjetMantis($group->data_array['group_id'],$members);
				break;
			}
			// mise a jour de l'adresse mail utilisateur
			case "change_cal_mail": {
				$user_id=$params[1];
				$this->updateUserInMantis($user_id);
				break;
			}
			case "add_cal_link_father":
			case "del_cal_link_father": {
				$sub_group_id = $params[0];
				$group_id = $params[1];
				$this->refreshHierarchyMantisBt();
				break;
			}
			case "group_delete": {
				$group_id=$params['group_id'];
				$group = group_get_object($group_id);
				if ($group->usesPlugin($this->name)) {
					if ($this->isProjectMantisCreated($group_id)) {
						$this->removeProjectMantis($group_id);
					}
				}
				break;
			}
			case "group_update": {
				$group_id = $params['group_id'];
				$group_name =$params['group_name'];
				$group_ispublic = $params['group_ispublic'];
				$group = group_get_object($group_id);
				if ($group->usesPlugin($this->name)) {
					if ($this->isProjectMantisCreated($group_id)) {
						if ($this->updateProjectMantis($group_id, $group_name, $group_ispublic)) {
							$returned = true;
						}
					} else {
						$returned = true;
					}
				} else {
					$returned = true;
				}
				break;
			}
		}
		return $returned;
	}

	/**
	 * groupisactivecheckboxpost - overwrite default function : initialize plugin
	 *
	 * @return	bool	success or not
	 */
	function groupisactivecheckboxpost(&$params) {
		// this code actually activates/deactivates the plugin after the form was submitted in the project edit public info page
		$group = group_get_object($params['group']);
		$flag = strtolower('use_'.$this->name);
		$returned = false;
		if ( getStringFromRequest($flag) == 1 ) {
			if (!$this->isProjectMantisCreated($group->getID())) {
				if($this->addProjectMantis($group->getID())) {
					$members = array();
					foreach($group->getMembers() as $member) {
						$members[] = $member->getUnixName();
						if($this->updateUsersProjectMantis($group->getID(), $members)) {
							$group->setPluginUse($this->name);
							$returned = true;
						};
					}
				}
			} else {
				$group->setPluginUse($this->name);
				$returned = true;
			}
		} else {
			$group->setPluginUse($this->name, false);
			$returned = true;
		}
		return $returned;
	}

	/**
	 * addProjectMantis - inject the Group into Mantisbt
	 *
	 * @param	int	The Group Id
	 * @return	bool	success or not
	 */
	function addProjectMantis($groupId) {

		$groupObject = group_get_object($groupId);
		$project = array();
		$project['name'] = $groupObject->getPublicName();
		$project['status'] = "development";

		if ($groupObject->isPublic()) {
			$project['view_state'] = 10;
		}else{
			$project['view_state'] = 50;
		}

		$project['description'] = $groupObject->getDescription();

		try {
			$clientSOAP = new SoapClient(forge_get_config('server_url','mantisbt')."/api/soap/mantisconnect.php?wsdl", array('trace'=>true, 'exceptions'=>true));
			$idProjetMantis = $clientSOAP->__soapCall('mc_project_add', array("username" => forge_get_config('adminsoap_user', 'mantisbt'), "password" => forge_get_config('adminsoap_passwd', 'mantisbt'), "project" => $project));
		} catch (SoapFault $soapFault) {
			$groupObject->setError('addProjectMantis::Error: ' . $soapFault->faultstring);
			return false;
		}
		if (!isset($idProjetMantis) || !is_int($idProjetMantis)){
			$groupObject->setError('addProjectMantis::Error: ' . _('Unable to create project in Mantisbt'));
			return false;
		}else{
			$res = db_query_params('INSERT INTO group_mantisbt (id_group, id_mantisbt) VALUES ($1,$2)',
					array($groupObject->getID(), $idProjetMantis));
			if (!$res) {
				$groupObject->setError('addProjectMantis::Error: ' . _('db_error') . ' ' .db_error());
				return false;
			}
		}
		return true;
	}

	function removeProjectMantis($idProjet) {
		$resIdProjetMantis = db_query_params('SELECT group_mantisbt.id_mantisbt FROM group_mantisbt WHERE group_mantisbt.id_group = $1',
						array($idProjet));

		echo db_error();
		$row = db_fetch_array($resIdProjetMantis);

		if ($row == null || count($row)>2) {
			echo 'removeProjetMantis:: ' . _('No project found');
		}else{
			$idMantisbt = $row['id_mantisbt'];
			try {
				$clientSOAP = new SoapClient(forge_get_config('server_url','mantisbt')."/api/soap/mantisconnect.php?wsdl", array('trace'=>true, 'exceptions'=>true));
				$delete = $clientSOAP->__soapCall('mc_project_delete', array("username" => forge_get_config('adminsoap_user','mantisbt'), "password" => forge_get_config('adminsoap_password','mantisbt'), "project_id" => $idMantisbt));
			} catch (SoapFault $soapFault) {
				echo $soapFault->faultstring;
			}
			if (!isset($delete)){
				echo 'removeProjetMantis:: ' . _('No project found in MantisBT') . ' ' .$idProjet;
			}else{
				db_query_params('DELETE FROM group_mantisbt WHERE group_mantisbt.id_mantisbt = $1',
						array($idMantisbt));
				echo db_error();
			}
		}
	}

	/**
	 * updateProjectMantis - update the Group informations into Mantisbt
	 * @param	int	id of the Group
	 * @param	string	group name
	 * @param	int	public or private
	 * @return	bool	success or not
	 */
	function updateProjectMantis($groupId,$groupName, $groupIspublic) {
		$groupObject = group_get_object($groupId);
		$projet = array();
		$project['name'] = $groupName;
		$project['status'] = "development";

		// should check the config on mantisbt side and not used hard coded values
		if ($groupIspublic) {
			$project['view_state'] = 10;
		} else {
			$project['view_state'] = 50;
		}

		
		$idMantisbt = getIdProjetMantis($groupId);

		if ($idMantisbt) {
			try {
				$clientSOAP = new SoapClient(forge_get_config('server_url','mantisbt')."/api/soap/mantisconnect.php?wsdl", array('trace'=>true, 'exceptions'=>true));
				$update = $clientSOAP->__soapCall('mc_project_update', array("username" => forge_get_config('adminsoap_user','mantisbt'), "password" => forge_get_config('adminsoap_password','mantisbt'), "project_id" => $idMantisbt, "project" => $project));;
			} catch (SoapFault $soapFault) {
				$groupObject->setError('updateProjectMantis::Error' . ' '. $soapFault->faultstring);
				return false;
			}
			if (!isset($update)) {
				$groupObject->setError('updateProjectMantis::Error' . ' ' . _('Update MantisBT project'));
				return false;
			}
		} else {
			$groupObject->setError('updateProjectMantis::Error ' . _('ID MantisBT project not found'));
			return false;
		}
		return true;
	}

	/**
	 * isProjectMantisCreated - check if the Project is already created
	 *
	 * @param	int	the Group Id
	 * @return	boolean	created or not
	 */
	function isProjectMantisCreated($idProjet){

		$resIdProjetMantis = db_query_params('SELECT group_mantisbt.id_mantisbt FROM group_mantisbt WHERE group_mantisbt.id_group = $1',
					array($idProjet));
		if (!$resIdProjetMantis)
			return false;

		if (db_numrows($resIdProjetMantis) > 0) {
			return true;
		}else{
			return false;
		}
	}

	function updateUserInMantis($user_id) {
		global $sys_mantisbt_host, $sys_mantisbt_db_user, $sys_mantisbt_db_password, $sys_mantisbt_db_port, $sys_mantisbt_db_name;
		// recuperation du nouveau mail
		$resUser = db_query_params ('SELECT user_name, email FROM users WHERE user_id = $1',array($user_id));
		echo db_error();
		$row = db_fetch_array($resUser);
		$dbConnection = db_connect_host($sys_mantisbt_db_name, $sys_mantisbt_db_user, $sys_mantisbt_db_password, $sys_mantisbt_host, $sys_mantisbt_db_port);
		if(!$dbConnection){
			$errMantis1 = "Error : Could not open connection" . db_error($dbConnection);
			echo $errMantis1;
			db_rollback($dbConnection);
		} else {
			db_query_params('UPDATE mantis_user_table set email = $1 where username = $2',array($row['email'],$row['user_name']),'-1','0',$dbConnection);
			echo db_error();
		}
	}

	/**
	 * updateUsersProjectMantis - inject Username in mantisbt for specific project
	 *
	 * @param	int	Group Id
	 * @param	array	Unix username array
	 * @return	boolean	success or not
	 */
	function updateUsersProjectMantis($groupId, $members) {
		$groupObject = group_get_object($groupId);
		$returned = false;
		global $role;

		// @TODO put that in config file ?
		if ($role == null){
			$role['Manager'] = 70;
			$role['Concepteur'] = 55;
			$role['Collaborateur'] = 55;
			$role['Rapporteur'] = 55;
		}

		// @TODO : make a robust function there based on RBAC ?
		$stateForge = array();
		foreach ($members as $key => $member){
			$resUserRole = db_query_params('SELECT role.role_name
							FROM role, user_group, users
							WHERE users.user_name = $1
							AND ( user_group.user_id = users.user_id AND user_group.group_id = $2 )
							AND user_group.role_id = role.role_id',
							array($member, $groupObject->getID()));
			if (!$resUserRole) {
				$groupObject->setError('updateUsersProjectMantis::'. _('Error : Cannot retrieve information about role') . ' ' .db_error());
				return $returned;
			} else {
				$row = db_fetch_array($resUserRole);
				$stateForge[$member]['name'] = $member;
				$stateForge[$member]['role'] = $row['role_name'];
			}
		}

		if ($this->__getDBType() === "pgsql") {
			if ($this->__updateUsersProjectMantisPgsql($groupObject->getID(), $stateForge)) {
				$returned = true;
			}
		}
		return $returned;
	}



	function refreshHierarchyMantisBt(){
		global $sys_mantisbt_host, $sys_mantisbt_db_user, $sys_mantisbt_db_password, $sys_mantisbt_db_port, $sys_mantisbt_db_name;

		$hierarchies=db_query_params('SELECT project_id, sub_project_id FROM plugin_projects_hierarchy WHERE activated=true',array());
		echo db_error();
		$dbConnection = db_connect_host($sys_mantisbt_db_name, $sys_mantisbt_db_user, $sys_mantisbt_db_password, $sys_mantisbt_host, $sys_mantisbt_db_port);
		if(!$dbConnection){
			db_rollback($dbConnection);
			return false;
		}

		db_begin($dbConnection);
		db_query_params('TRUNCATE TABLE mantis_project_hierarchy_table', array() , '-1', 0, $dbConnection);
		while ($hierarchy = db_fetch_array($hierarchies)) {
			$result = db_query_params ('INSERT INTO mantis_project_hierarchy_table (child_id, parent_id, inherit_parent) VALUES ($1, $2, $3)',
						array (getIdProjetMantis($hierarchy['sub_project_id']), getIdProjetMantis($hierarchy['project_id']), 1),
						'-1',
						0,
						$dbConnection);

			if (!$result) {
				$this->setError(_('Insert Failed') . db_error($dbConnection));
				db_rollback();
				return false;
			}
		}

		db_commit($dbConnection);
		pg_close($dbConnection);
		return true;
	}

	/**
	 * __updateUsersProjectMantisPgsql - update Users for this project in PostgreSQL DB
	 *
	 * @param	int	this Group Id
	 * @param	array	the role of this forge
	 * @return	boolean	success or not
	 * @private
	 */
	function __updateUsersProjectMantisPgsql($groupId, $stateForge) {
		$groupObject = group_get_object($groupId);
		$returned = false;
		$dbConnection = db_connect_host(forge_get_config('db_name','mantisbt'), forge_get_config('db_user','mantisbt'), forge_get_config('db_password','mantisbt'), forge_get_config('db_host','mantisbt'), forge_get_config('db_port','mantisbt'));
		if(!$dbConnection) {
			$groupObject->setError('updateUsersProjectMantis::'. _('Error : Could not open connection') . db_error($dbConnection));
			db_rollback($dbConnection);
		}else{
			$idMantis = getIdProjetMantis($groupObject->getID());
			$result = pg_delete($dbConnection,"mantis_project_user_list_table",array("project_id"=>$idMantis));
			if (!$result){
				echo 'updateUsersProjectMantis::Error '. _('Unable to clean roles in Mantisbt');
			}else{
				foreach($stateForge as $member => $array){

					$resultIdUser = db_query_params('SELECT mantis_user_table.id FROM mantis_user_table WHERE mantis_user_table.username = $1',
								array($member), '-1', 0, $dbConnection);

					$rowIdUser = db_fetch_array($resultIdUser);
					$idUser = $rowIdUser['id'];

					$resultInsert = pg_insert($dbConnection,
									"mantis_project_user_list_table",
									array("project_id" => $idMantis, "user_id" => $idUser, "access_level" => $role[$array['role']])
								);
					if (!isset($resultInsert)) {
						echo 'updateUsersProjectMantis::Error '. _('Unable to update roles in mantisbt');
					} else {
						$returned = true;
					}
				}
			}
		}
		return $returned;
	}

	/*
	 * __getDBType - return the type of DB used for mantisbt
	 *
	 * @return	string	type of the DB
	 * @private
	 */
	function __getDBType() {
		switch (forge_get_config('db_name','mantisbt')) {
			case "pgsql": {
				return "pgsql";
				break;
			}
			default: {
				return false;
				break;
			}
		}
	}
}

function getIdProjetMantis($groupId) {

	$group = group_get_object($groupId);
	$resIdProjetMantis = db_query_params('SELECT group_mantisbt.id_mantisbt FROM group_mantisbt WHERE group_mantisbt.id_group = $1',
				array($groupId));
	if (!$resIdProjetMantis) {
		$group->setError('getIdProjetMantis::error ' .db_error());
		return 0;
	}

	$row = db_fetch_array($resIdProjetMantis);
	if ($row == null || count($row)>2) {
		$group->setError('getIdProjetMantis::error ' . _('ID project not found'));
		return 0;
	}else{
		return $row['id_mantisbt'];
	}

}
// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:

?>
