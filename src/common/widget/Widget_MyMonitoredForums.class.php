<?php
/**
 * Copyright (c) Xerox Corporation, Codendi Team, 2001-2009. All rights reserved
 * Copyright 2012-2013, Franck Villaume - TrivialDev
 *
 * This file is a part of FusionForge.
 *
 * FusionForge is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * FusionForge is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with FusionForge. If not, see <http://www.gnu.org/licenses/>.
 */

require_once 'Widget.class.php';
require_once $gfwww.'include/my_utils.php';

/**
 * Widget_MyMonitoredForums
 *
 * Forums that are actively monitored
 */

class Widget_MyMonitoredForums extends Widget {
	function __construct() {
		$this->Widget('mymonitoredforums');
	}

	function getTitle() {
		return _("Monitored Forums");
	}

	function getContent() {
		$html_my_monitored_forums = '';
		$sql="SELECT DISTINCT groups.group_id, groups.group_name, forum_group_list.group_forum_id ".
		"FROM groups,forum_group_list,forum_monitored_forums ".
		"WHERE groups.group_id=forum_group_list.group_id ".
		"AND groups.status = 'A' ".
		"AND forum_group_list.group_forum_id=forum_monitored_forums.forum_id ".
		"AND forum_monitored_forums.user_id=$1 ";
		$um = UserManager::instance();
		$current_user = $um->getCurrentUser();
		if ($current_user->getStatus()=='S') {
			$projects = $current_user->getProjects();
			$sql .= "AND groups.group_id IN (". implode(',', $projects) .") ";
		}
		//$sql .= "GROUP BY groups.group_id ORDER BY groups.group_id ASC LIMIT 100";
		$sql .= "ORDER BY groups.group_id ASC LIMIT 100";

		$result=db_query_params($sql,array(user_getid()));
		$glist = array();
		while ($r = db_fetch_array($result)) {
			if (forge_check_perm('project', $r['group_id'], 'read')
					&& forge_check_perm('forum', $r['group_forum_id'], 'read')) {
				$glist[] = $r;
			}
		}
		$rows=count($glist);
		if (!$result || $rows < 1) {
			$html_my_monitored_forums .= '<div class="warning">' . _("You are not monitoring any forums.") . '</div><p>' . _("If you monitor forums, you will be sent new posts in the form of an email, with a link to the new message.") . '</p><p>' . _("You can monitor forums by clicking on the appropriate menu item in the discussion forum itself.") . '</p>';
		} else {
			$request =& HTTPRequest::instance();
			$html_my_monitored_forums .= '<table style="width:100%">';
			for ($j=0; $j<$rows; $j++) {
				$group_id = $glist[$j]['group_id'];

				$sql2="SELECT forum_group_list.group_forum_id,forum_group_list.forum_name ".
					"FROM groups,forum_group_list,forum_monitored_forums ".
					"WHERE groups.group_id=forum_group_list.group_id ".
					"AND groups.group_id=$1".
					"AND forum_group_list.group_forum_id=forum_monitored_forums.forum_id ".
					"AND forum_monitored_forums.user_id=$2 LIMIT 100";

				$result2 = db_query_params($sql2, array($group_id, user_getid()));
				$flist = array();
				while ($r = db_fetch_array($result2)) {
					if (forge_check_perm('forum', $r['group_forum_id'], 'read')) {
						$flist[] = $r;
					}
				}

				$rows2 = count($flist);

				$vItemId = new Valid_UInt('hide_item_id');
				$vItemId->required();
				if ($request->valid($vItemId)) {
					$hide_item_id = $request->get('hide_item_id');
				} else {
					$hide_item_id = null;
				}

				$vForum = new Valid_WhiteList('hide_forum', array(0, 1));
				$vForum->required();
				if ($request->valid($vForum)) {
					$hide_forum = $request->get('hide_forum');
				} else {
					$hide_forum = null;
				}

				list($hide_now,$count_diff,$hide_url) = my_hide_url('forum',$group_id,$hide_item_id,$rows2,$hide_forum);

				$html_hdr = '<tr class="boxitem"><td colspan="2">'.
				$hide_url.'<a href="/forum/?group_id='.$group_id.'">'.
				$glist[$j]['group_name'].'</a>    ';

				$html = '';
				$count_new = max(0, $count_diff);
				for ($i=0; $i<$rows2; $i++) {
					if (!$hide_now) {
						$group_forum_id = $flist[$i]['group_forum_id'];
						$html .= '
					<tr '. $GLOBALS['HTML']->boxGetAltRowStyle($i) .'"><td width="99%">'.
					'&nbsp;&nbsp;&nbsp;-&nbsp;<a href="/forum/forum.php?forum_id='.$group_forum_id.'">'.
						$flist[$i]['forum_name'].'</a></td>'.
					'<td class="align-center"><a href="/forum/monitor.php?forum_id='.$group_forum_id.'&group_id='.$group_id.'&stop=1'.
					'" onClick="return confirm(\''._("Stop monitoring this Forum?").'\')">'.
					'<img src="'.$GLOBALS['HTML']->imgroot.'ic/trash.png" height="16" width="16" '.
					'border="0" alt="'._("Stop Monitoring").'" /></a></td></tr>';
					}
				}

				$html_hdr .= '['.$rows2.($count_new ? ", <b>".sprintf(_('%s new'), $count_new)."</b>]" : ']').'</td></tr>';
				$html_my_monitored_forums .= $html_hdr.$html;
			}
			$html_my_monitored_forums .= '</table>';
		}
		return $html_my_monitored_forums;
	}

	function getCategory() {
		return 'Forums';
	}

	function getDescription() {
		return _("List forums that you are currently monitoring, by project.")
             . "<br />"
             . _("To cancel any of the monitored items just click on the trash icon next to the item label.");
	}

	function isAjax() {
		return true;
	}

	function getAjaxUrl($owner_id, $owner_type) {
		$request =& HTTPRequest::instance();
		$ajax_url = parent::getAjaxUrl($owner_id, $owner_type);
		if ($request->exist('hide_item_id') || $request->exist('hide_forum')) {
			$ajax_url .= '&amp;hide_item_id=' . $request->get('hide_item_id') . '&amp;hide_forum=' . $request->get('hide_forum');
		}
		return $ajax_url;
	}

	function isAvailable() {
		if (!forge_get_config('use_forum')) {
			return false;
		}
		foreach (UserManager::instance()->getCurrentUser()->getGroups(false) as $p) {
			if ($p->usesForum()) {
				return true;
			}
		}
	}
}
