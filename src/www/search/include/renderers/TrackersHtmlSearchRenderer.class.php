<?php
/**
 * Search Engine
 *
 * Copyright 2004 (c) Dominik Haas, GForge Team
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
require_once $gfcommon.'search/TrackersSearchQuery.class.php';

class TrackersHtmlSearchRenderer extends HtmlGroupSearchRenderer {

	/**
	 * Constructor
	 *
	 * @param string $words words we are searching for
	 * @param int $offset offset
	 * @param boolean $isExact if we want to search for all the words or if only one matching the query is sufficient
	 * @param int $groupId group id
	 * @param array $sections array of all sections to search in (array of strings)
	 *
	 */
	function TrackersHtmlSearchRenderer($words, $offset, $isExact, $groupId, $sections=SEARCH__ALL_SECTIONS) {
		$userIsGroupMember = $this->isGroupMember($groupId);

		$searchQuery = new TrackersSearchQuery($words, $offset, $isExact, $groupId, $sections, $userIsGroupMember);

		$this->HtmlGroupSearchRenderer(SEARCH__TYPE_IS_TRACKERS, $words, $isExact, $searchQuery, $groupId, 'tracker');

		$this->tableHeaders = array(
			'&nbsp;',
			_('#'),
			_('Summary'),
			_('Submitted by'),
			_('Date')
		);
	}

	function getFilteredRows() {
		$rowsCount = $this->searchQuery->getRowsCount();
		$result =& $this->searchQuery->getResult();

		$fields = array ('group_artifact_id',
				 'artifact_id',
				 'name',
				 'summary',
				 'realname',
				 'open_date');

		$fd = array();
		for($i = 0; $i < $rowsCount; $i++) {
			if (forge_check_perm('tracker',
					     db_result($result, $i, 'group_artifact_id'),
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

		$return = '';
		$rowColor = 0;
		$lastTracker = null;
		
		foreach ($fd as $row) {
			//section changed
			$currentTracker = $row['name'];
			if ($lastTracker != $currentTracker) {
				$return .= '<tr><td colspan="5">'.$currentTracker.'</td></tr>';
				$lastTracker = $currentTracker;
				$rowColor = 0;
			}
			$return .= '<tr '. $GLOBALS['HTML']->boxGetAltRowStyle($rowColor) .'>'
						. '<td width="5%">&nbsp;</td>'
						. '<td>'.$row['artifact_id'].'</td>'
						. '<td>'
							. '<a href="'.util_make_url ('/tracker/?func=detail&amp;group_id='.$this->groupId.'&amp;aid='.$row['artifact_id'] . '&amp;atid='.$row['group_artifact_id']).'">'
							. html_image('ic/tracker20g.png').' '.$row['summary']
							. '</a></td>'		
						. '<td width="15%">'.$row['realname'].'</td>'
						. '<td width="15%">'.relative_date($row['open_date']).'</td></tr>';
			$rowColor ++;
		}
		return $return;
	}

	/**
	 * getSections - get the array of possible sections to search in
	 *
  	 * @return array sections
	 */
	static function getSections($groupId) {
		$userIsGroupMember = TrackersHtmlSearchRenderer::isGroupMember($groupId);

		return TrackersSearchQuery::getSections($groupId, $userIsGroupMember);
	}

	/**
	 * redirectToResult - redirect the user  directly to the result when there is only one matching result
	 */
	function redirectToResult() {
		session_redirect('/tracker/?group_id='.$this->groupId.'&atid='.$this->getResultId('group_artifact_id').'&func=detail&aid='.$this->getResultId('artifact_id'));
	}
}

// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:
