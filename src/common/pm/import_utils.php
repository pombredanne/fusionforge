<?php
/**
 * FusionForge project manager
 *
 * Copyright 1999-2000, Tim Perdue/Sourceforge
 * Copyright 2002, Tim Perdue/GForge, LLC
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

require_once $gfcommon.'pm/ProjectTaskFactory.class.php';

function &pm_import_tasks($group_project_id,&$tasks) {
	$was_error = false;
	$foo = "";

	$pg = projectgroup_get_object($group_project_id);
	if (!$pg || !is_object($pg)) {
		$array['success']=false;
		$array['errormessage']='Could Not Get ProjectGroup';
	} elseif ($pg->isError()) {
		$array['success']=false;
		$array['errormessage']='Could Not Get ProjectGroup: '.$pg->getErrorMessage();
	} else {
		$count=count($tasks);
		//
		//  Build hash list of technicians so we can get their ID for assigning tasks
		//
		$engine = RBACEngine::getInstance () ;
		$techs = $engine->getUsersByAllowedAction ('pm', $pg->getID(), 'tech') ;
		$tcount=count($techs);
		for ($i=0; $i<$tcount; $i++) {
			$tarr[strtolower($techs[$i]->getUnixName())]=$techs[$i]->getID();
			$tarr[strtolower($techs[$i]->getRealName())]=$techs[$i]->getID();
		}
		$invalid_names=array();
		//
		//  Create a linked list based on the msproj_id
		//
		for ($i=0; $i<$count; $i++) {
			$msprojid[$tasks[$i]['msproj_id']] =& $tasks[$i];
			$resrc = $tasks[$i]['resources'];
			for ($j=0; $j<count($resrc); $j++) {
				//validate user - see if they really exist as techs in this subproject
				if (!util_ifsetor($tarr[strtolower($resrc[$j]['user_name'])])) {
					//create list of bogus names to send back
					if (array_search(strtolower($resrc[$j]['user_name']),$invalid_names) === false) {
						$invalid_names[]=$resrc[$j]['user_name'];
					}
				}
			}
		}

		//
		//  invalid assignees - send it back for remapping
		//
		if (count($invalid_names)) {
			$array['success']=false;
			$array['errormessage']='Invalid Resource Name:';
			foreach ($invalid_names as $i) {
				$array['errormessage'] .= ' ' . $i;
			}
			$array['resourcename']=$invalid_names;
			for ($i=0; $i<$tcount; $i++) {
				$array['usernames'][$techs[$i]->getID()]=$techs[$i]->getUnixName();
			}
			return $array;
		} else {
			//
			//  Begin inserting/updating the tasks
			//
			for ($i=0; $i<$count; $i++) {
				if ($was_error) {
					continue;
				}
				//no task_id so it must be new - create it
				if (!$tasks[$i]['id']) {
					if (!$tasks[$i]['notes']) {
						$tasks[$i]['notes']='None Provided';
					}
					//create the task
					$pt = new ProjectTask($pg);
					if (!$pt || !is_object($pt)) {
						$array['success']=false;
						$was_error=true;
						$array['errormessage']='Could Not Get ProjectTask';
					} elseif ($pt->isError()) {
						$array['success']=false;
						$was_error=true;
						$array['errormessage']='Could Not Get ProjectTask: '.$pt->getErrorMessage();
					} else {
						//remap priority names=>numbers
						$priority=$tasks[$i]['priority'];
						if (!$priority || $priority < 1 || $priority > 5) {
							$priority=3;
						}
						//map users
						$assignees=array();
						$resrc = $tasks[$i]['resources'];
						for ($ucount=0; $ucount< count($resrc); $ucount++) {
							//get their user_id from the $tarr we created earlier
							$assignees[]=$tarr[strtolower($resrc[$ucount]['user_name'])];
						}
						//don't do anything with dependencies yet - we may only have
						//the MSprojid from dependent items
						$hours = $tasks[$i]['work'];
						if (!$hours) {
							$hours='0.0';
						}
						$percent_complete= intval($tasks[$i]['percent_complete']);
						if (!$percent_complete) {
							$percent_complete=0;
						} elseif ($percent_complete > 100) {
							$percent_complete=100;
						}

						// Convert category name to category_id if given.
						if (isset($tasks[$i]['category'])) {
							if ($tasks[$i]['category'] == 'None') {
								$category_id = 100;
							} else {
								$res = db_query_params('SELECT category_id FROM project_category WHERE group_project_id=$1 AND category_name=$2',
									array($group_project_id, $tasks[$i]['category']));
								$category_id = db_result($res, 0, 'category_id');
								if (!$category_id) {
									$was_error=true;
									$array['errormessage']='Error No category named : '.$tasks[$i]['category'];
									break;
								}
							}
						} else {
							$category_id = $pt->getCategoryID();
						}

						if (!$pt->create(
							$tasks[$i]['name'],
							$tasks[$i]['notes'],
							$priority,
							$hours,
							strtotime($tasks[$i]['start_date']),
							strtotime($tasks[$i]['end_date']),
							$category_id,
							$percent_complete,
							$assignees,
							$deps = array(),
							$tasks[$i]['duration'],
							$tasks[$i]['parent_id'])) {
							$array['success']=false;
							$was_error=true;
							$array['errormessage']='Error Creating ProjectTask: '.$pt->getErrorMessage();
							break 1;
//							continue;
						} else {
//successful
							$tasks[$i]['id']  = $pt->getID();
							$tasks[$i]['obj'] = $pt;
							$pt->setExternalID($tasks[$i]['msproj_id']);
							$pt = null;
						}
					}

				} else {
					//update existing task
					//create the task
					$pt = projecttask_get_object($tasks[$i]['id']);
					if (!$pt || !is_object($pt)) {
					} elseif ($pt->isError()) {
						$array['success']=false;
						$was_error=true;
						$array['errormessage']='Could Not Get ProjectTask: '.$pt->getErrorMessage();
					} else {
						//remap priority names=>numbers
						$priority=$tasks[$i]['priority'];
						if (!$priority || $priority < 1 || $priority > 5) {
							$priority=3;
						}

						//map users
						$assignees=array();
						$resrc = $tasks[$i]['resources'];
						for ($ucount=0; $ucount<count($resrc); $ucount++) {
							//get their user_id from the $tarr we created earlier
							$assignees[]=$tarr[strtolower($resrc[$ucount]['user_name'])];
						}

						//don't do anything with dependencies yet - we may only have the
						//MSprojid from dependent items
						$hours = $tasks[$i]['work'];
						if (!$hours) {
							$hours='0.0';
						}
						$percent_complete= intval($tasks[$i]['percent_complete']);
						if (!$percent_complete) {
							$percent_complete=0;
						} elseif ($percent_complete > 100) {
							$percent_complete=100;
						}

						// Convert category name to category_id if given.
						if (isset($tasks[$i]['category'])) {
							if ($tasks[$i]['category'] == 'None') {
								$category_id = 100;
							} else {
								$res = db_query_params('SELECT category_id FROM project_category WHERE group_project_id=$1 AND category_name=$2',
									array($group_project_id, $tasks[$i]['category']));
								$category_id = db_result($res, 0, 'category_id');
								if (!$category_id) {
									$was_error=true;
									$array['errormessage']='Error No category named : '.$tasks[$i]['category'];
									break;
								}
							}
						} else {
							$category_id = $pt->getCategoryID();
						}

						if (!$pt->update(
							$tasks[$i]['name'],
							$tasks[$i]['notes'],
							$priority,
							$hours,
							strtotime($tasks[$i]['start_date']),
							strtotime($tasks[$i]['end_date']),
							$pt->getStatusID(),
							$category_id,
							$percent_complete,
							$assignees,
							$pt->getDependentOn(),
							$pg->getID(),
							$tasks[$i]['duration'],
							$tasks[$i]['parent_id'])) {
							$array['success']=false;
							$was_error=true;
							$array['errormessage']='Error Updating ProjectTask: '.$pt->getErrorMessage();
							break 1;
//							continue;

						} else {
//successful
							$tasks[$i]['id']  = $pt->getID();
							$tasks[$i]['obj'] = $pt;
							$pt->setExternalID($tasks[$i]['msproj_id']);
							$pt = null;

						}
					} //if task->iserror()
				} //if task_id
				//accumulate list of completed tasks
				//any task not in this list will be deleted
				$completed[$tasks[$i]['id']]=true;
			} //for i


			//
			//  Do task dependencies
			//

			if (!$was_error) {
				//iterate the tasks
				for ($i=0; $i<$count; $i++) {
					$darr=$tasks[$i]['dependenton'];

					$deps=array();
					//iterate each dependency in a task
					for ($dcount=0; $dcount<count($darr); $dcount++) {
						//get the id of the task we're dependent on -
						// may have to get it from msprojid linked list
						$id=$darr[$dcount]['task_id'];
						if ($id < 1) {
							$id=$msprojid[$darr[$dcount]['msproj_id']]['id'];
						}
						//prevent task from being dependent on itself
						if ($id == $tasks[$i]['id']) {
							continue;
						}
						$deps[$id]=$darr[$dcount]['link_type'];
					}
					if (isset($tasks[$i]['obj']) && is_object($tasks[$i]['obj'])) {
						if (!$tasks[$i]['obj']->setDependentOn($deps)) {
							$was_error=true;
							$array['success']=false;
						}
					} else {
		//				$was_error=true;
		//				$array['success']=false;
					}
					unset($deps);
				} //iterates tasks to do dependencies
			}


			//
			//	Delete unreferenced tasks
			//
			if (!$was_error) {
				$ptf = new ProjectTaskFactory($pg);
				$pt_arr=& $ptf->getTasks();
				for ($i=0; $i<count($pt_arr); $i++) {
					if (is_object($pt_arr[$i])) {
						if (!util_ifsetor($completed[$pt_arr[$i]->getID()])) {
							if (!$pt_arr[$i]->delete(true)) {
								echo $pt_arr[$i]->getErrorMessage();
							}
						}
					}
				}
			}
		} //invalid names
	} //get projectGroup

	if (!$was_error) {
		$array['success']=true;
	}

	return $array;
}

// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:
