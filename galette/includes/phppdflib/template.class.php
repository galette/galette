<?php
/*
   php pdf generation library - template extension
   Copyright (C) Potential Technologies 2002 - 2003
   http://www.potentialtech.com

   This program is free software; you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation; either version 2 of the License, or
   (at your option) any later version.

   This program is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License
   along with this program; if not, write to the Free Software
   Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.

   $Id$
*/

class template
{

	var $nexttid, $templ;
    var $pdf; // reference to the parent class

    function template()
    {
    	$this->tid = 0;
    }

    function create()
    {
        $temp = $this->nexttid;
        // Stores the next object ID within this template
        $this->templ[$temp]['next'] = 0;
        $this->nexttid ++;
        return $temp;
    }

    function size($tid, $width, $height)
    {
        $this->templ[$tid]["height"] = $height;
        $this->templ[$tid]["width"]  = $width;
        return true;
    }

    function rectangle($tid, $bottom, $left, $top, $right, $attrib = array())
    {
        $temp = $this->pdf->_resolve_param($attrib);
        $temp["type"] = "rectangle";
        $temp["top"] = $top;
        $temp["left"] = $left;
        $temp["bottom"] = $bottom;
        $temp["right"] = $right;
        return $this->_add_object($temp, $tid);
    }

    function circle($tid, $cenx, $ceny, $radius, $attrib = array())
    {
		$temp = $this->pdf->_resolve_param($attrib);
        $temp["type"] = "circle";
        $temp["x"] = $cenx;
        $temp["y"] = $ceny;
        $temp["radius"] = $radius;
        return $this->_add_object($temp, $tid);
    }

    function line($tid, $x, $y, $attrib = array())
    {
		$temp = $this->pdf->_resolve_param($attrib);
        $temp["type"] = "line";
        $temp["x"] = $x;
        $temp["y"] = $y;
        return $this->_add_object($temp, $tid);
    }

    function image($tid, $left, $bottom, $width, $height, $image, $attrib = array())
    {
    	$this->ifield($tid, $left, $bottom, $width, $height, false, $image, $attrib);
    }

    function ifield($tid, $left, $bottom, $width, $height, $name, $default = false, $attrib = array())
    {
		$temp = $this->pdf->_resolve_param($attrib);
        $temp['type'] = "ifield";
        $temp['left'] = $left;
        $temp['bottom'] = $bottom;
        $temp['name'] = $name;
        $temp['default'] = $default;
        $temp['width'] = $width;
        $temp['height'] = $height;
        return $this->_add_object($temp, $tid);
    }

    function text($tid, $left, $bottom, $text, $attrib = array())
    {
    	return $this->field($tid, $left, $bottom, false, $text, $attrib);
    }

    function field($tid, $left, $bottom, $name, $default = '', $attrib = array())
    {
    	$temp = $this->pdf->_resolve_param($attrib);
        $temp["type"] = "field";
        $temp["left"] = $left;
        $temp["bottom"] = $bottom;
        $temp["name"] = $name;
        $temp["default"] = $default;
        return $this->_add_object($temp, $tid);
    }

    function paragraph($tid, $bottom, $left, $top, $right, $text, $attrib = array())
    {
		return $this->pfield($tid, $bottom, $left, $top, $right, false, $text, $attrib);
    }

    function pfield($tid, $bottom, $left, $top, $right, $name, $default = '', $attrib = array())
    {
    	$temp = $this->pdf->_resolve_param($attrib);
        $temp['type'] = 'pfield';
        $temp['left'] = $left;
        $temp['bottom'] = $bottom;
        $temp['top'] = $top;
        $temp['right'] = $right;
        $temp['name'] = $name;
        $temp['default'] = $default;
        return $this->_add_object($temp, $tid);
    }

    function place($tid, $page, $left, $bottom, $data = array())
    {
    	$ok = true;
        foreach( $this->templ[$tid]["objects"] as $o ) {
            switch ($o['type']) {
            case 'rectangle' :
                $ok = $ok && $this->pdf->draw_rectangle($bottom + $o["top"],
                                                        $left + $o["left"],
                                                        $bottom + $o["bottom"],
                                                        $left + $o["right"],
                                                        $page,
                                                        $o);
                break;

            case 'circle' :
            	$ok = $ok && $this->pdf->draw_circle($left + $o['x'],
                						             $bottom + $o['y'],
                                                     $o['radius'],
                                                     $page,
                                                     $o);
                break;

            case 'line' :
            	foreach ($o['x'] as $key => $value) {
                	$o['x'][$key] += $left;
                    $o['y'][$key] += $bottom;
                }
                $ok = $ok && $this->pdf->draw_line($o['x'],
                					               $o['y'],
                                                   $page,
                                                   $o);
                break;

            case 'field' :
                $temp = ($o['name'] === false) || !isset($data[$o['name']]) || !strlen($data[$o['name']]) ? $o['default'] : $data[$o['name']];
                $ok = $ok && $this->pdf->draw_text($left + $o['left'],
                                                   $bottom + $o['bottom'],
                                                   $temp,
                                                   $page,
                                                   $o);
                break;

            case 'pfield' :
                $temp = ($o['name'] === false) || !isset($data[$o['name']]) || !strlen($data[$o['name']]) ? $o['default'] : $data[$o['name']];
                $t = $this->pdf->draw_paragraph($bottom + $o['top'],
                                                $left + $o['left'],
                                                $bottom + $o['bottom'],
                                                $left + $o['right'],
                                                $temp,
                                                $page,
                                                $o);
                if (is_string($t)) {
                	$ok = false;
                    $this->pdf->_push_error(6013, "Text overflowed available area: $t");
                }
            	break;

            case 'ifield' :
            	$temp = ($o['name'] === false) || empty($data[$o['name']]) ? $o['default'] : $data[$o['name']];
                if ($temp === false) {
                	break;
                }
                $id = $this->pdf->get_image_size($temp);
                unset($o['scale']);
                $o['scale']['x'] = $o['width'] / $id['width'];
                $o['scale']['y'] = $o['height'] / $id['height'];
                $ok = $ok && $this->pdf->image_place($temp,
                						             $o['bottom'] + $bottom,
                                                     $o['left'] + $left,
                                                     $page,
                                                     $o);
                break;
            }
        }
        return $ok;
    }

	/* Private methods
     */

    function _add_object($objarray, $tid)
    {
    	$oid = $this->templ[$tid]["next"];
        $this->templ[$tid]["next"] ++;
        $this->templ[$tid]["objects"][$oid] = $objarray;
        return $oid;
    }

}
?>
