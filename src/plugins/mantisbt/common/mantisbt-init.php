<?php
/**
 *
 * Copyright 2009, Fabien Dubois - Capgemini
 * Copyright 2010, Franck Villaume - Capgemini
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

global $gfplugins;
require_once $gfplugins.'mantisbt/common/MantisBTPlugin.class.php';

$MantisBTPluginObject = new MantisBTPlugin;

register_plugin($MantisBTPluginObject);

// Local Variables:
// mode: php
// c-file-style: "bsd"
// End:
