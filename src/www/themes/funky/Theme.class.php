<?php
/**
 * FusionForge Funky Theme
 *
 * Copyright 2010, Antoine Mercadal - Capgemini
 * Copyright 2010, Marc-Etienne Vargenau, Alcatel-Lucent
 * Copyright 2011, Franck Villaume - Capgemini
 * Copyright 2011-2013, Franck Villaume - TrivialDev
 * Copyright (C) 2011 Alain Peyrat - Alcatel-Lucent
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

require_once $gfwww.'include/Layout.class.php';

define('TOP_TAB_HEIGHT', 30);
define('BOTTOM_TAB_HEIGHT', 22);

class Theme extends Layout {

	function Theme() {
		// Parent constructor
		$this->Layout();
		$this->themeurl = util_make_url('themes/funky/');
		$this->imgbaseurl = $this->themeurl . 'images/';
		$this->imgroot = $this->imgbaseurl;

		$this->addStylesheet('/themes/funky/css/theme.css');
		$this->addStylesheet('/themes/funky/css/theme-pages.css');
	}

	function quicknewsbutton() {
		return "<div class='quicknews-toggle'><a href=# onclick='jQuery(\".quicknews\").slideToggle()'>news</a></div>";
	}

	function quicknews() {
		$ret = "<div class='quicknews'>";
		$ret .= "<ul>";
		$ret .= "<li><h1>news de ouf</h1>hello world</li>";
		$ret .= "<li><h1>news de ouf</h1>hello world</li>";
		$ret .= "<li><h1>news de ouf</h1>hello world</li>";
		$ret .= "<li><h1>news de ouf</h1>hello world</li>";
		$ret .= "</ul>";
		$ret .= "</div>";
		return $ret;
	}

	function bodyHeader($params) {

		if (!isset($params['h1']) && isset($params['title'])) {
			$params['h1'] = $params['title'];
		}

		if (!isset($params['title'])) {
			$params['title'] = forge_get_config('forge_name');
		} else {
			$params['title'] = $params['title'] . " - ".forge_get_config('forge_name');
		}

		echo '<table id="header" class="fullwidth">' . "\n";
		echo '<tr>' . "\n";
		echo '<td id="header-col1">' . "\n";
		echo util_make_link('/', html_image('/header/top-logo.png', null, null, array('alt'=>'FusionForge Home'))) . "\n";

		echo '</td>' . "\n";
		echo '<td id="header-col2">' . "\n";

		$items = $this->navigation->getUserLinks();
		for ($j = 0; $j < count($items['titles']); $j++) {
			$links[] = util_make_link($items['urls'][$j], $items['titles'][$j], array('class'=>'userlink'), true);
		}
		echo implode(' | ', $links);
		plugin_hook('headermenu', $params);

		echo '</td>' . "\n";
		echo '</tr>' . "\n";
		echo '<tr>' . "\n";
		echo '<td colspan="2" id="header-line2">' . "\n";
		// echo $this->quicknewsbutton();
		$this->quickNav();
		$this->searchBox();

		echo '</td></tr>' . "\n";
		echo '<tr><td colspan="2" id="header-news">' . "\n";
		//echo $this->quicknews();
		echo'</td></tr></table><!-- outer tabs -->' . "\n";
		$this->outerTabs($params);
		echo '<!-- inner tabs -->' . "\n";
		echo '<div class="innertabs">' . "\n";
		if (isset($params['group']) && $params['group']) {
			echo $this->projectTabs($params['toptab'], $params['group']);
		}

		echo '</div>' . "\n";
		echo '<div id="maindiv">' . "\n";

		plugin_hook('message', array());

		if(isset($GLOBALS['error_msg']) && $GLOBALS['error_msg']) {
			echo $this->error_msg($GLOBALS['error_msg']);
		}
		if(isset($GLOBALS['warning_msg']) && $GLOBALS['warning_msg']) {
			echo $this->warning_msg($GLOBALS['warning_msg']);
		}
		if(isset($GLOBALS['feedback']) && $GLOBALS['feedback']) {
			echo $this->feedback($GLOBALS['feedback']);
		}

		if (isset($params['h1'])) {
			echo '<h1>'.$params['h1'].'</h1>';
		} elseif (isset($params['title'])) {
			echo '<h1 class="hide">'.$params['title'].'</h1>';
		}
		if (isset($params['submenu']))
			echo $params['submenu'];
	}

	function bodyFooter($params) {
		echo '</div><!-- id="maindiv" -->' . "\n";
	}

	function footer($params) {
		$this->bodyFooter($params);
		echo '<div class="footer">' . "\n";
		echo $this->navigation->getPoweredBy();
		echo $this->navigation->getShowSource();
		echo '<div style="clear:both"></div></div>';
		plugin_hook('webanalytics_url', array());
		echo '</body></html>' . "\n";
	}

	/**
	 * boxTop() - Top HTML box
	 *
	 * @param	string	Box title
	 * @param	bool		Whether to echo or return the results
	 * @param	string	The box background color
	 */
	function boxTop($title, $id = '') {
		if ($id) {
			$id = $this->toSlug($id);
			$idid = ' id="' . $id . '"';
			$idtitle = ' id="' . $id . '-title"';
			$idtcont = ' id="' . $id . '-title-content"';
		} else {
			$idid = "";
			$idtitle = "";
			$idtcont = "";
		}

		$t_result = '';
		$t_result .= '<div' . $idid . ' class="box-surround">';
		$t_result .= '<div' . $idtitle . ' class="box-title">';
		$t_result .= '<div' . $idtcont . ' class="box-title-content">'. $title .'</div>';
		$t_result .= '</div> <!-- class="box-title" -->';

		return $t_result;
	}

	/**
	 * boxMiddle() - Middle HTML box
	 *
	 * @param	string	Box title
	 * @param	string	The box background color
	 */
	function boxMiddle($title, $id = '') {
		if ($id) {
			$id = $this->toSlug($id);
			$idtitle = ' id="' . $id . '-title"';
		} else {
			$idtitle = "";
		}

		$t_result ='<div' . $idtitle . ' class="box-middle">'.$title.'</div>';

		return $t_result;
	}

	/**
	 * boxContent() - Content HTML box
	 *
	 * @param	string	Box content
	 */
	function boxContent($content, $id = '') {
		if ($id) {
			$id = $this->toSlug($id);
			$idcont = ' id="' . $id . '-content"';
		} else {
			$idcont = "";
		}

		$t_result ='<div' . $idcont . ' class="box-content">'.$content.'</div>';
		return $t_result;
	}

	/**
	 * boxBottom() - Bottom HTML box
	 *
	 */
	function boxBottom() {
		$t_result='</div><!-- class="box-surround" -->';

		return $t_result;
	}

	/**
	 * boxGetAltRowStyle() - Get an alternating row style for tables
	 *
	 * @param	int	Row number
	 */
	function boxGetAltRowStyle($i) 	{
		if ($i % 2 == 0)
			return 'class="bgcolor-white"';
		else
			return 'class="bgcolor-grey"';
	}

	function tabGenerator($TABS_DIRS, $TABS_TITLES, $TABS_TOOLTIPS, $nested=false,  $selected=false, $sel_tab_bgcolor='WHITE',  $total_width='100%') {
		$count = count($TABS_DIRS);

		if ($count < 1) {
			return '';
		}

		global $use_tooltips;

		if ($use_tooltips) {
			?>
			<script type="text/javascript">//<![CDATA[
				if (typeof(jQuery(window).tipsy) == 'function') {
					jQuery(document).ready(
						function() {
							jQuery('.tabtitle').tipsy({delayIn: 500, delayOut: 0, fade: true});
							jQuery('.tabtitle-nw').tipsy({gravity: 'nw', delayIn: 500, delayOut: 0, fade: true});
							jQuery('.tabtitle-ne').tipsy({gravity: 'ne', delayIn: 500, delayOut: 0, fade: true});
							jQuery('.tabtitle-w').tipsy({gravity: 'w', delayIn: 500, delayOut: 0, fade: true});
							jQuery('.tabtitle-e').tipsy({gravity: 'e', delayIn: 500, delayOut: 0, fade: true});
							jQuery('.tabtitle-sw').tipsy({gravity: 'sw', delayIn: 500, delayOut: 0, fade: true});
							jQuery('.tabtitle-se').tipsy({gravity: 'se', delayIn: 500, delayOut: 0, fade: true});
						}
					);
				}
			//]]></script>
			<?php
		}

		$return = '<!-- start tabs -->';
		$return .= '<table class="tabGenerator fullwidth" ';

		if ($total_width != '100%')
			$return .= 'style="width:' . $total_width . ';"';

		$return .= ">\n";
		$return .= '<tr>';

		$accumulated_width = 0;

		for ($i=0; $i<$count; $i++) {
			$tabwidth = intval(ceil(($i+1)*100/$count)) - $accumulated_width ;
			$accumulated_width += $tabwidth ;

			$return .= "\n";

			// middle part
			$return .= '<td class="tg-middle" style="width:'.$tabwidth.'%;"><a ';
			$return .= 'id="'.md5($TABS_DIRS[$i]).'" ';
			if ($use_tooltips && isset($TABS_TOOLTIPS[$i]))
				$return .= 'class="tabtitle" title="'.$TABS_TOOLTIPS[$i].'" ';
			$return .= 'href="'.$TABS_DIRS[$i].'">' . "\n";
			$return .= '<span';

			if ($selected == $i)
				$return .= ' class="selected"';

			$return .= '>';
			$return .= '<span';

			if ($nested)
				$return .= ' class="nested"';

			$return .= '>' . "\n";
			$return .= ''.$TABS_TITLES[$i].'' . "\n";
			$return .= '</span>';
			$return .= '</span>' . "\n";
			$return .= '</a></td>' . "\n";

		}

		$return .= '</tr></table><!-- end tabs -->';

		return $return;
	}

	/**
	 * beginSubMenu() - Opening a submenu.
	 *
	 * @return	string	Html to start a submenu.
	 */
	function beginSubMenu() {
		$return = '<ul class="submenu">';
		return $return;
	}

	/**
	 * endSubMenu() - Closing a submenu.
	 *
	 * @return	string	Html to end a submenu.
	 */
	function endSubMenu() {
		$return = '</ul>';
		return $return;
	}

	/**
	 * printSubMenu() - Takes two array of titles and links and builds the contents of a menu.
	 *
	 * @param	array	The array of titles.
	 * @param	array	The array of title links.
	 * @param	array	The array of attributs by link
	 * @return	string	Html to build a submenu.
	 */
	function printSubMenu($title_arr, $links_arr, $attr_arr) {
		$count  = count($title_arr) - 1;
		$return = '';

		for ($i=0; $i<$count; $i++)
			$return .= "<li><span>" . util_make_link($links_arr[$i], $title_arr[$i], $attr_arr[$i]) . "</span></li>";

		$return .= "<li><span>" . util_make_link($links_arr[$i], $title_arr[$i], $attr_arr[$i]) . "</span></li>";
		return $return;
	}

	/**
	 * subMenu() - Takes two array of titles and links and build a menu.
	 *
	 * @param	array	The array of titles.
	 * @param	array	The array of title links.
	 * @param	array	The array of attributs by link
	 * @return	string	Html to build a submenu.
	 */
	function subMenu($title_arr, $links_arr, $attr_arr = false) {
		$return  = $this->beginSubMenu();
		$return .= $this->printSubMenu($title_arr, $links_arr, $attr_arr);
		$return .= $this->endSubMenu();
		return $return;
	}

	/**
	 * multiTableRow() - create a mutlilevel row in a table
	 *
	 * @param	string	the row attributes
	 * @param	array	the array of cell data, each element is an array,
	 *					the first item being the text,
	 *					the subsequent items are attributes (dont include
	 *					the bgcolor for the title here, that will be
	 *					handled by $istitle
	 * @param	boolean	is this row part of the title ?
	 *
	 * @return string
	 */
	function multiTableRow($row_attr, $cell_data, $istitle)
	{
		$return= '<tr class="ff" '.$row_attr;
		if ( $istitle )
			$return .=' align="center"';

		$return .= '>';
		for ( $c = 0; $c < count($cell_data); $c++ ) {
			$return .='<td class="ff" ';
			for ( $a=1; $a < count($cell_data[$c]); $a++)
				$return .= $cell_data[$c][$a].' ';

			$return .= '>';
			if ( $istitle )
				$return .='<strong>';

			$return .= $cell_data[$c][0];
			if ( $istitle )
				$return .='</strong>';

			$return .= '</td>';
		}
		$return .= '</tr>';
		return $return;
	}

	/**
	 * getThemeIdFromName()
	 *
	 * @param	string	the dirname of the theme
	 * @return	integer	the theme id
	 */
	function getThemeIdFromName($dirname)
	{
		$res = db_query_params ('SELECT theme_id FROM themes WHERE dirname=$1', array($dirname));
		return db_result($res, 0, 'theme_id');
	}

	/**
	 * headerJS() - creates the JS headers and calls the plugin javascript hook
	 * @todo generalize this
	 */
	function headerJS()
	{
		echo '<script type="text/javascript" src="'. util_make_uri('/js/common.js') .'"></script>';
		echo '<script type="text/javascript" src="/scripts/codendi/LayoutManager.js"></script>';
		echo '<script type="text/javascript" src="/scripts/codendi/ReorderColumns.js"></script>';
		echo '<script type="text/javascript" src="/scripts/codendi/codendi-1236793993.js"></script>';
		echo '<script type="text/javascript" src="/scripts/codendi/validate.js"></script>';
		echo '<script type="text/javascript" src="/scripts/codendi/Tooltip.js"></script>';

		plugin_hook("javascript_file", false);

		// invoke the 'javascript' hook for custom javascript addition
		$params = array('return' => false);
		plugin_hook("javascript", $params);
		$javascript = $params['return'];
		if($javascript) {
			echo '<script type="text/javascript">//<![CDATA['."\n";
			echo $javascript;
			echo "\n//]]></script>\n";
		}
		html_use_tooltips();
		html_use_storage();
		html_use_simplemenu();
		html_use_coolfieldset();
		html_use_jqueryui();
		echo $this->getJavascripts();
		echo $this->getStylesheets();
		?>
		<script type="text/javascript">//<![CDATA[
		jQuery.noConflict();
		jQuery(window).load(function(){
			jQuery(".quicknews").hide();
			setTimeout("jQuery('.feedback').hide('slow')", 5000);
			setInterval(function() {
					setTimeout("jQuery('.feedback').hide('slow')", 5000);
				}, 5000);
		});
		//]]></script>
		<?php
	}
}

// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:
