<?php
/**
 * Copyright (C) 2009-2012 Ulteo SAS
 * http://www.ulteo.com
 * Author Laurent CLOUET <laurent@ulteo.com> 2009
 * Author Julien LANGLOIS <julien@ulteo.com> 2012
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; version 2
 * of the License.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 **/

class ConfigElement_password extends ConfigElement {  // input password (rw)
	public function toHTML($readonly=false) {
		$html_id = $this->htmlID();
		$disabled = '';
		if ($readonly) {
			$disabled = 'disabled="disabled"';
		}

		return '<input type="password" '.$disabled.' id="'.$html_id.'" name="'.$html_id.'" value="'.$this->content.'" size="25" />';
	}
}
