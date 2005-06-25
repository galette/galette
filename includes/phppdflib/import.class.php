<?php
/*
   php pdf generation library - import extension
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

class import
{
    var $pdf;       // reference to the parent class
    var $xref;      // Array holding the xref table data
    var $pdftolib;  // Array storing conversions from PDF to library OID
    var $d;         // store the PDF stream object here
    var $ob;        // Array of data on each PDF object

    // Actually append the specified PDF to the end of the current
    function append($data)
    {
        // Basic magic check
        if (substr($data, 0, 6) != '%PDF-1') {
            echo "Bad magic\n";
            return false;
        }
        $this->xref     =
        $this->pdftolib =
        $this->ob       = array();
        $this->d = new StreamHandler($data);
        if (!$this->capture_xref()) {
            echo "Couldn't find xref\n";
            return false;
        }
        foreach ($this->xref as $pdfid => $junk) {
            $this->extract($pdfid);
        }
        foreach ($this->ob as $id => $junk) {
            $this->import_ob($id);
        }
        $root = $this->find_root();
        $this->recursive_create($root);
        return true;
    }
    
    function capture_xref()
    {
        $data = &$this->d;
        // First we find the start of the xref
        $data->end();
        while ($data->previous_word() != 'startxref')
            ;
        // Now we capture where the xref starts
        $data->next_word(); // slurp 'startxref'
        $xref = $data->next_word();
        $data->l = (int)$xref;
        echo "found xref at $xref<br>\n";
        if ($data->next_word() != 'xref') {
            echo "Proposed xref location was bogus: $xref\n";
            return false;
        }
        $firstx = $data->next_word();
        $numx   = $data->next_word();
        echo "firstx=$firstx, numx=$numx<br>\n";
        for ($i = $firstx; $i < $firstx + $numx; $i++) {
            $loc = $data->next_word();
            $gen = $data->next_word();
            if ($data->next_word() == 'n') {
                $this->xref[$i] = $loc;
            }
        }
        return true;
    }
    
    function find_root()
    {
        // Find trailer
        $d = &$this->d;
        $d->end();
        while ($d->previous_word() != 'trailer')
            ;
        while ($d->next_word() != '/Root')
            ;
        return $d->next_word();
    }
    
    function recursive_create($id)
    {
        if (!isset($this->ob[$id]['/Type']))
            $this->ob[$id]['/Type'] = '';
        switch ($this->ob[$id]['/Type']) {
        case '/Info' :
            return false;
            break;
            
        case '/Root' :
            return 0;
            break;
        }
    }
    
    function extract($id)
    {
        if (!isset($this->ob[$id])) {
            $location = $this->xref[$id];
            $data = &$this->d;
            $data->l = $location;
            $id = $data->next_word();
            $gn = $data->next_word();
            echo "Found $id $gn R at $location<br>\n";
            flush();
            $this->extract_obj($id);
        }
        return $this->ob[$id];
    }
    
    function extract_obj($id)
    {
        $d = &$this->d;
        // Magic test
        if ($d->next_word() != 'obj') {
            echo "Didn't find an object here!<br>\n";
            return false;
        }
        $this->ob[$id]['value'] = '';
        while (true) {
            $d->skip_whitespace();
            if (substr($d->d, $d->l, 2) == '<<') {
                echo "Found a dictionary<br>\n";
                $this->ob[$id] = $this->extract_dictionary();
            } else {
                $w = $d->next_word();
                if ($w == 'endobj') break;
                if ($w == 'stream') {
                    echo "Found a stream: {$d->l}<br>\n";
                    $d->l -= 6;
                    $this->ob[$id]['stream'] =
                        $this->extract_stream($this->ob[$id]);
                } else {
                    // Must be a raw value
                    echo "Assuming a raw value in object<br>\n";
                    $this->ob[$id]['value'] .= $w . ' ';
                }
            }
        }
        echo "<pre>\n";
        print_r($this->ob[$id]);
        echo "</pre>\n";
    }
    
    function extract_dictionary()
    {
        $d = &$this->d;
        if (substr($d->d, $d->l, 2) != '<<') {
            echo "Didn't find a dictionary here!<br>\n";
            return array();
        }
        $d->l += 2; // Slurp the '<<'
        $r = array();
        $r['itype'] = 'dictionary';
        $label = false;
        $state = array();
        while (true) {
            $d->skip_whitespace();
            if (substr($d->d, $d->l, 2) == '>>') {
                echo "Found end of dictionary<br>\n";
                $d->l += 2;
                if (count($state) > 0) {
                    $r[$l] = '';
                    foreach ($state as $v) {
                        $r[$l] .= $v . ' ';
                        echo "Popping remainer of stack for [$l] = '{$r[$l]}'<br>\n";
                    }
                }
                break;
            }
            if (substr($d->d, $d->l, 2) == '<<') {
                echo "Found subdictionary<br>\n";
                $r[$l] = $this->extract_dictionary();
                $label = false;
                continue;
            }
            if (substr($d->d, $d->l, 1) == '[') {
                echo "Analyzing array '" . substr($d->d, $d->l, 15) .
                     "...' for [$l]<br>\n";
                $r[$l] = $d->get_array();
                $label = false;
                continue;
            }
            $w = $d->next_word();
            if (!$label) {
                echo "Making '$w' a label<br>\n";
                $label = true;
                $l = $w;
            } else {
                echo "Current character = '" . $d->cc() . "'<br>\n";
                if ($w{0} == '/') {
                    if (!isset($state[0])) {
                        echo "Assigning '$w' as value of [$l]<br>\n";
                        $r[$l] = $w;
                    } else {
                        $d->rewind();
                        echo "Popping the stack for [$l]<br>\n";
                        $r[$l] = $state[0];
                        $state = array();
                    }
                } else if ($w{0} == '(' && $w{strlen($w) - 1} == ')') {
                    // We've got a string
                    echo "Assigning string value '$w' to [$l]<br>\n";
                    $r[$l] = $w;
                } else if ($w == 'R') {
                    $ir = $state[0] . ' ' . $state[1] . ' R';
                    echo "Stored IR $ir<br>\n";
                    $r[$l] = $ir;
                    $state = array();
                } else {
                    // Push this on the stack
                    echo "pushing '$w' on the stack<br>\n";
                    $state[] = $w;
                    continue;
                }
                $label = false;
            }
        }
        return $r;
    }
    
    function extract_stream($meta)
    {
        $d = &$this->d;
        $t = $d->next_word();
        if ($t == 'stream') {
            if ($d->strpos($meta['/Length'], 'R')) {
                // We must resolve an indirect reference
                echo "Resolving IR for /Length<br>\n";
                $t = $d->l;
                $this->extract((int)$meta['/Length']);
                $d->l = $t;
                $length = $this->ob[(int)$meta['/Length']]['value'];
            } else {
                $length = (int)$meta['/Length'];
            }
            echo "Stream should be {$length}<br>\n";
            $stream = substr($d->d, $d->l + 1, $length);
            $d->l += 1 + $length;
            $d->next_word(); // Consume "endstream"
            if (!isset($meta['/Filter'])) $meta['/Filter'] = '';
            $r = new StreamHandler($stream, $meta['/Filter']);
            return $r;
        } else {
            echo "I didn't find a stream here: '$t'<br>\n";
            $d->rewind();
            return '';
        }
    }
}

class StreamHandler
{
    var $d;
    var $l;
    
    function StreamHandler($stream, $filter = '')
    {
        $this->l = 0;
        if ($this->strpos($filter, 'FlateDecode')) {
            $this->d = trim(gzuncompress($stream)) . "\n";
        } else {
            $this->d = trim($stream) . "\n";
        }
    }
    
    function get_array()
    {
        $s = new StreamHandler($this->read_array());
        echo "Got array of '" . $s->d . "'<br>\n";
        $r = array();
        $r['itype'] = 'array';
        $as = array();
        while ($s->l < strlen($s->d) - 1) {
            $c = $s->next_word();
            if ($c == 'R') {
                $t = $as[0] . ' ' . $as[1] . ' R';
                $r[] = $t;
                $as = array();
            } else {
                $as[] = $c;
            }
        }
        return array_merge($r, $as);
    }
    
    function read_array()
    {
        $this->back_to_array();
        if ($this->cc() != '[') {
            // No array here
            echo "I don't see an array: '" .
                 substr($this->d, $this->l, 15) . "...'<br>\n";
            return '';
        }
        $this->next();
        $r = '';
        do {
            $r .= $this->cc();
            $this->l++;
        } while ($this->cc() != ']');
        $this->next();
        return $r;
    }
    
    function back_to_array()
    {
        if ($this->cc() != '[') {
            $this->l--;
        }
    }
    
    function end()
    {
        $this->l = strlen($this->d) - 1;
    }
    
    function start()
    {
        $this->l = 0;
    }
    
    function next_word()
    {
        $this->skip_whitespace();
        $r = '';
        // Slurp a PDF string
        if ($this->cc() == '(') {
            while ($this->cc() != ')' || $this->cc($this->l - 1) == '\\') {
                $r .= $this->cc();
                $this->next();
            }
            $r .= $this->cc();
            $this->next();
            return $r;
        }
        do {
            $r .= $this->cc();
            $this->next();
        } while (!$this->is_whitespace() &&
                 !$this->boundry() &&
                 $this->l < strlen($this->d));
        return $r;
    }
    
    function previous_word()
    {
        $this->rewind();
        $r = $this->next_word();
        $this->rewind();
        return $r;
    }
    
    // Backs up 1 word
    function rewind()
    {
        $this->skip_whitespace(false);
        do {
            $this->l--;
        } while (!$this->is_whitespace() && !$this->boundry());
    }
    
    function skip_whitespace($forward = true)
    {
        while ($this->is_whitespace()) {
            if ($forward) {
                $this->next();
            } else {
                $this->l--;
            }
            if ($this->l == 0 || $this->l == (strlen($this->d) - 1)) break;
        }
        return $this->l;
    }
    
    function boundry($c = false)
    {
        if ($c === false) $c = $this->cc();
        if ($this->strpos('()/><[]', $c)) {
            return true;
        } else {
            return false;
        }
    }
    
    function is_whitespace($c = '')
    {
        if ($c == '') $c = $this->cc();
        if ($c == ' ' ||
            $c == "\x0a" ||
            $c == "\x0d" ||
            $c == "\t") {
             return true;
        } else {
             return false;
        }
    }
    
    // Returns current byte (character)
    function cc($l = false)
    {
        if ($l === false) $l = $this->l;
        return $this->d{$l};
    }
    
    function next()
    {
        $this->l++;
        if ($this->l >= strlen($this->d)) {
            echo "fell off the end!<br>\n";
            $this->end();
        }
    }
    
    function strpos($haystack, $needle)
    {
        if (strpos($haystack, $needle) === false) {
            return false;
        } else {
            return true;
        }
    }
}
?>