<?php
/**
 * Search Engine
 *
 * Copyright 1999-2001 (c) VA Linux Systems
 * Copyright 2004 (c) Guillaume Smet / Open Wide
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

require_once $gfwww.'search/include/renderers/HtmlGroupSearchRenderer.class.php';
require_once $gfcommon.'search/ForumSearchQuery.class.php';

class ForumHtmlSearchRenderer extends HtmlGroupSearchRenderer {

	/**
	 * forum id
	 *
	 * @var int $groupId
	 */
	var $forumId;

	/**
	 * Constructor
	 *
	 * @param string $words words we are searching for
	 * @param int $offset offset
	 * @param boolean $isExact if we want to search for all the words or if only one matching the query is sufficient
	 * @param int $groupId group id
	 * @param int $forumId forum id
	 */
	function ForumHtmlSearchRenderer($words, $offset, $isExact, $groupId, $forumId) {
		$this->forumId = $forumId;

		$searchQuery = new ForumSearchQuery($words, $offset, $isExact, $groupId, $forumId);

		$this->HtmlGroupSearchRenderer(SEARCH__TYPE_IS_FORUM, $words, $isExact, $searchQuery, $groupId, 'forums');

		$this->tableHeaders = array(
			_('Thread'),
			_('Author'),
			_('Date')
		);
	}
	
	function getFilteredRows() {
		$rowsCount = $this->searchQuery->getRowsCount();
		$result =& $this->searchQuery->getResult();

		$fields = array ('group_forum_id',
				 'msg_id',
				 'subject',
				 'realname',
				 'post_date');

		$fd = array();
		for($i = 0; $i < $rowsCount; $i++) {
			if (forge_check_perm('forum',
					     db_result($result, $i, 'group_forum_id'),
					     'read')) {
				$r = array();
				foreach ($fields as $f) {
					$r[$f] = db_result($result, $i, $f);
				}
				$fd[] = $r;
			}
		}
		return $fd;
	}

	/**
	 * getRows - get the html output for result rows
	 *
	 * @return string html output
	 */
	function getRows() {
		$fd = $this->getFilteredRows();

		$dateFormat = _('Y-m-d H:i');

		$return = '';
		$i = 0;
		foreach ($fd as $row) {
			$return .= '<tr '. $GLOBALS['HTML']->boxGetAltRowStyle($i) .'><td class="halfwidth"><a href="'.util_make_url ('/forum/message.php?msg_id=' . $row['msg_id']).'">'
				. html_image('ic/msg.png', '10', '12')
				. ' '.$row['subject'].'</a></td>'
				. '<td width="30%">'.$row['realname'].'</td>'
				. '<td width="20%">'.date($dateFormat, $row['post_date']).'</td></tr>';
			$i++;
		}
		return $return;
	}

	/**
	 * getPreviousResultsUrl - get the url to go to see the previous results
	 *
	 * @return string url to previous results page
	 */
	function getPreviousResultsUrl() {
		return parent::getPreviousResultsUrl().'&amp;forum_id='.$this->forumId;
	}

	/**
	 * getNextResultsUrl - get the url to go to see the next results
	 *
	 * @return string url to next results page
	 */
	function getNextResultsUrl() {
		return parent::getNextResultsUrl().'&amp;forum_id='.$this->forumId;
	}

	/**
	 * redirectToResult - redirect the user  directly to the result when there is only one matching result
	 */
	function redirectToResult() {
		session_redirect('/forum/message.php?msg_id='.$this->getResultId('msg_id'));
	}
}
