<?php
/*
   php pdf chart generation library
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

class chart
{

    var $colors;        // global colors
    var $series;        // Array of series
    var $pdf;           // reference to the parent class
    
    function chart()
    {
        $this->clearchart();
    }

    function clearchart()
    {
        // Default colors
        unset($this->colors);
        // This oughta make things more readible
        $white['red'] = $white['green'] = $white['blue'] = 1;
        $black['red'] = $black['green'] = $black['blue'] = 0;
        $this->colors['background'] = $white;
        $this->colors['border']     = $black;
        $this->colors['hlabel']     = $black;
        $this->colors['vlabel']     = $black;
        $this->colors['vgrade']     = $black;
        $this->colors['hgrade']     = $black;
        unset($this->series);
    }

    /* Set up default colors to use globally on the chart
     */
    function setcolor($name, $red, $green, $blue)
    {
        $this->colors[$name]['red']   = $red;
        $this->colors[$name]['green'] = $green;
        $this->colors[$name]['blue']  = $blue;
    }

    function add_series($name, $points, $color = 'black', $width = 1, $style = 'default')
    {
        $t['points'] = $points;
        $t['color'] = $color;
        $t['width'] = $width;
        $t['style'] = $style;
        $this->series[$name] = $t;
    }

    function place_chart($page, $left, $bottom, $width, $height, $type = 'line')
    {
        switch (strtolower($type)) {
            case 'pie' :
            case '3dpie' :
            case 'bar' :
            case '3dbar' :
            case '3dline' :
            case 'line' :
            default :
                $this->_place_line_chart($page, $left, $bottom, $width, $height);
        }
    }

    function _place_line_chart($page, $left, $bottom, $width, $height)
    {
        // First a filled rectangle to set background color
        $this->_fill_background($page, $left, $bottom, $width, $height);
        // caclulate a scale
        $low = $high = $numx = 0;
        foreach($this->series as $data) {
            foreach($data['points'] as $value) {
                if ($value < $low) $low = $value;
                if ($value > $high) $high = $value;
            }
            if (count($data['points']) > $numx) $numx = count($data);
        }
        if (($high - $low) <= 0) return false;
        $xscale = $width / $numx;
        $yscale = $height / ($high - $low);
        foreach($this->series as $data) {
            $a['strokecolor'] = $this->pdf->get_color($data['color']);
            $a['width'] = $data['width'];
            $c = 0;
            unset($x);
            unset($y);
            foreach ($data['points'] as $value) {
                $x[$c] = ($c * $xscale) + $left;
                //echo $x[$c] . " ";
                $y[$c] = (($value - $low) * $yscale) + $bottom;
                //echo $y[$c] . "<br>\n";
                $c++;
            }
            $this->pdf->draw_line($x, $y, $page, $a);
        }
    }

    function _fill_background($page, $left, $bottom, $width, $height)
    {
        $a['fillcolor'] = $this->colors['background'];
        $a['mode'] = 'fill';
        $this->pdf->draw_rectangle($bottom + $height,
                             $left,
                             $bottom,
                             $left + $width,
                             $page,
                             $a);
    }
}

?>
