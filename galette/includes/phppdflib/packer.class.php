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

/* The packer class should allow automatic placement of objects
 * and automatic creation of new pages.  (wish me luck)
 */

/* This is a class for the field object itself
 */

class packer
{
    var $pdf,               // reference to the parent class
        $curpage = false,   // Current page ID
        $fields = array();  // Array of available fields

	function packer()
    {
    	// Initialize the extension
    }
    
    /* This is a wrapper around the pdffile class' ->new_page()
     * that saves the relevent information for packer operation
     */
    function new_page()
    {
        $this->curpage = $this->pdf->new_page();
        $right = $this->pdf->objects[$this->curpage]['width'] -
                 $this->pdf->default['margin-right'];
        $top = $this->pdf->objects[$this->curpage]['height'] -
               $this->pdf->default['margin-top'];
        $this->fields[] = $this->newfield($this->pdf->default['margin-left'],
                                          $right,
                                          $this->pdf->default['margin-bottom'],
                                          $top);
        foreach (array('margin-left',
                       'margin-right',
                       'margin-top',
                       'margin-bottom') as $margin) {
            $this->pdf->objects[$this->curpage][$margin] = 0;
        }
        return $this->curpage;
    }
    
    /* Fill text into the remaining space.
     * padding is in ems
     * minwidth is in ems, current defaults are used for all parameters
     */
    function fill_text($text, $padding = 1, $minwidth = 10)
    {
        $ignore = array();
        $m = $this->pdf->strlen('M');
        $w = $minwidth * $m;
        $p = $m * $padding;
        while (strlen($text)) {
            $r = $this->find_upper_left($ignore);
            if ($r === false) break;
            $f = $this->fields[$r];
            if (($f->r - $f->l - (2 * $p)) <= $w) {
                $ignore[] = $r;
                continue;
            }
            $text = $this->pdf->draw_paragraph($f->t - $p, $f->l + $p,
                                               $f->b + $p, $f->r - $p,
                                               $text, $this->curpage);
            if (is_string($text)) {
                unset($this->fields[$r]);
                $this->fields_cleanup();
            } else {
                $this->fields[$r]->t = $text;
                $this->fields_cleanup();
                break;
            }
        }
        if (is_string($text))
            return $text;
        else
            return true;
    }
    
    /* Here we manually allocate a set region from the fields
     * This is likely to be the foundation of everything else that's
     * done in this class.  $space is a 'field' object.
     */
    function allocate($space)
    {
        foreach ($this->fields as $fid => $field) {
            if (!$space->intersects($field)) continue;
            if ($space->obliterates($field)) {
                unset($this->fields[$fid]);
                $this->fields_cleanup();
                continue;
            }
            if ($space->punches($field)) {
                $this->punch($space, $fid);
                continue;
            }
            if (($n = $space->notches($field)) != 0) {
                $this->notch($space, $fid, $n);
            }
        }
        $this->merge();
    }

    // Punch a hole in $target
    function punch($hole, $target) {
        $t = $this->fields[$target];
        $this->newfield($hole->l, $hole->r, $t->t, $hole->t);   // top
        $this->newfield($hole->l, $hole->r, $t->b, $hole->b);   // bottom
        $this->newfield($t->l, $hole->l, $t->t, $t->b);         // left
        $this->fields[$target]->l = $hole->r;                   // right
        $this->fields_cleanup();
    }
    
    /* Don't call this unless you've already established the operation to
     * be a notch, behaviour is undefined otherwise.
     */
    function notch($hole, $target, $how) {
        $t = &$this->fields[$target];
        // 3 scenerios
        if ($how == 1 || $how == 2 || $how == 4 || $how == 8) {
            // Side notch
            switch ($how) {
            case 1 : // Bottom
                $this->newfield($t->l, $hole->l, $t->t, $t->b);
                $this->newfield($t->r, $hole->r, $t->t, $t->b);
                $t->l = $hole->l;
                $t->r = $hole->r;
                $t->b = $hole->t;
                break;
            case 4 : // Top
                $this->newfield($t->l, $hole->l, $t->t, $t->b);
                $this->newfield($t->r, $hole->r, $t->t, $t->b);
                $t->l = $hole->l;
                $t->r = $hole->r;
                $t->t = $hole->b;
                break;
            case 2 : // Left
                $this->newfield($t->l, $hole->r, $t->t, $hole->t);
                $this->newfield($t->l, $hole->r, $hole->b, $t->b);
                $t->l = $hole->r;
                break;
            case 8 : // Right
                $this->newfield($t->r, $hole->l, $t->t, $hole->t);
                $this->newfield($t->r, $hole->l, $hole->b, $t->b);
                $t->r = $hole->l;
                break;
            }
        } else if ($how == 3 || $how == 6 || $how == 12 || $how == 9) {
            // Corner notch
            if (($how & 2) == 2) { // Notching left side
                if (($how & 4) == 4) { // left top
                    $this->newfield($t->l, $hole->r, $hole->b, $t->b);
                } else { // left bottom
                    $this->newfield($t->l, $hole->r, $hole->t, $t->t);
                }
                $t->l = $hole->r;
            } else { // notching right side
                if (($how & 4) == 4) { // right top
                    $this->newfield($t->r, $hole->l, $hole->b, $t->b);
                } else { // right bottom
                    $this->newfield($t->r, $hole->l, $hole->t, $t->t);
                }
                $t->r = $hole->l;
            }
        } else {
            // We assume it must be a chop
            switch ($how) {
            case 11 : // bottom
                $t->b = $hole->t; break;
                
            case 7 : // left
                $t->l = $hole->r; break;
                
            case 14 : // top
                $t->t = $hole->b; break;
                
            case 13 : // right
                $t->r = $hold->l; break;
            
            default : // error
                $this->pdf->push_error(666, 'notch encountered invalid chop');
            }
        }
    }
    
    function find_upper_left($ignore = array())
    {
        if (!count($this->fields)) return false;
        $r = false;
        $top = $this->pdf->objects[$this->curpage]['height'];
        $dist = $this->dist($top, $this->pdf->objects[$this->curpage]['width']);
        foreach ($this->fields as $fid => $f) {
            if (in_array($fid, $ignore)) continue;
            $tdist = $this->dist($top - $f->t, $f->l);
            if ($tdist < $dist) {
                $r = $fid;
                $dist = $tdist;
            }
        }
        return $r;
    }
    
    function dist($x, $y)
    {
        return pow(pow($x, 2) + pow($y, 2), 0.5);
    }
    
    /* Scan the array of regions for regions that share an exactly equal border
     * and can thus be joined without altering the function of things.
     */
    function merge()
    {
        foreach ($this->fields as $fid1 => $f1) {
            foreach ($this->fields as $fid2 => $f2) {
                if ($f1->l == $f2->r && $f1->t == $f2->t && $f1->b == $f2->b) {
                    $this->fields[$fid1]->l = $f2->l;
                    unset($this->fields[$fid2]);
                }
                if ($f1->r == $f2->l && $f1->t == $f2->t && $f1->b == $f2->b) {
                    $this->fields[$fid1]->r = $f2->r;
                    unset($this->fields[$fid2]);
                }
                if ($f1->t == $f2->b && $f1->l == $f2->l && $f1->l == $f2->l) {
                    $this->fields[$fid1]->t = $f2->t;
                    unset($this->fields[$fid2]);
                }
                if ($f1->b == $f2->t && $f1->l == $f2->l && $f1->l == $f2->l) {
                    $this->fields[$fid1]->b = $f2->b;
                    unset($this->fields[$fid2]);
                }
            }
        }
        $this->fields_cleanup();
    }
    
    function newfield($l, $r, $t, $b)
    {
        $temp = new field($l, $r, $t, $b);
        $this->fields[] = $temp;
    }
    
    /* PHP seems to have a lot of trouble not messing up its arrays.  Some
     * operations leave gaps in the array that I wouldn't have expected to do
     * so. In lieu of having to contstantly check for gaps in the array prior
     * to processing, I call this to remove them whenever I identify an
     * operation that can cause gaps. Hopefully I'll find a better way to
     * handle this, as this seems like a hack to me.
     */
    function fields_cleanup()
    {
        $t = array();
        foreach ($this->fields as $f) {
            if (is_object($f)) {
                $t[] = $f;
            }
        }
        $this->fields = $t;
        reset($this->fields);
    }
}

class field
{
    var $l, $r, $t, $b;     // left, right, top, bottom
    
    function field($x1, $x2, $y1, $y2)
    {
        if ($x1 < $x2) {
            $this->l = $x1;
            $this->r = $x2;
        } else {
            $this->l = $x2;
            $this->r = $x1;
        }
        if ($y1 < $y2) {
            $this->t = $y2;
            $this->b = $y1;
        } else {
            $this->t = $y1;
            $this->b = $y2;
        }
    }
    
    // Return true if any part of me includes $target
    function intersects($target)
    {
        if ($this->t <= $target->b) return false;
        if ($this->b >= $target->t) return false;
        if ($this->l >= $target->r) return false;
        if ($this->r <= $target->l) return false;
        return true;
    }
    
    // Returns true if this field completely covers $target
    function obliterates($target)
    {
        if ($this->l <= $target->l &&
            $this->r >= $target->r &&
            $this->t >= $target->t &&
            $this->b <= $target->b) {
                return true;
        } else {
            return false;
        }
    }
    
    // Returns true if this field punches a hole in $target
    function punches($target) {
        if ($this->l > $target->l &&
            $this->r < $target->r &&
            $this->t < $target->t &&
            $this->b > $target->b) {
                return true;
        } else {
            return false;
        }
    }
    
    /* Returns a bitmap of notches in $target
     * 0 = no notches
     * 1 = notches bottom
     * 2 = notches left
     * 4 = notches top
     * 8 = notches right
     */
    function notches($target) {
        $notch = 0;
        if ($this->t > $target->b && $this->b <= $target->b) $notch = 1;
        if ($this->r > $target->l && $this->l <= $target->l) $notch += 2;
        if ($this->b < $target->t && $this->t >= $target->t) $notch += 4;
        if ($this->l < $target->r && $this->r >= $target->r) $notch += 8;
        return $notch;
    }
}

?>