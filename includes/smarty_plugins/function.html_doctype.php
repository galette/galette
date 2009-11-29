<?php
/*
 * Smarty plugin
 * -------------------------------------------------------------
 * Type:     function
 * Name:     html_doctype
 * Purpose:  Adds a (X)HTML doctype to the current document.
 *           This plugin function needs to be called at the
 *           top of your template in the very first line.
 *           Default doctype is XHTML 1.0 Transitional.
 * Params:   xhtml             FALSE to use HTML doctype,
 *                             TRUE to use XHTML doctype
 *           version           HTML/XHTML version. For HTML 4.01, 4.0, 3.2 and 2.0 is supported.
 *                             For XHTML versions 1.0 and 1.1 are accepted.
 *           type              frameset, transitional, strict etc.
 *           dtd               TRUE to use default DTD, FALSE to not use a DTD or
 *                             path to DTD (e.g. "http://www.w3.org/TR/html4/Frameset.dtd")
 *           omitxml           if FALSE <?xml version="1.0" ... ?> header will be outputted
 *                             (only suitable if xhtml is set to TRUE)
 *           encoding          default is UTF-8
 *                             (only suitable if xhtml is set to TRUE)
 *           sendheaders       TRUE (default) or FALSE
 *           force_opera_html  Forces an html header and doctype if opera is detected
 *                             (only suitable if xhtml is set to TRUE). This is because the
 *                             script tag is not recognised by opera 7.23 and below in XHTML.
 *                             default is false.
 *                             
 * 
 * Changes:  Version 1.5 (S. Shah)
 *           - Implemented a switch to force an html header and doctype when using
 *             opera (force_opera_html). This will give a html 4.01 doctype, allowing scripts
 *             to work.
 *           - Default type is now XHTML 1.0 Transitional
 *
 *           Version 1.4
 *           - Implemented better HTTP_ACCEPT header checks
 *           - Distinguish between HTML 4.0 and 4.01
 *           - minor fixes / clean ups
 * 
 *           Version 1.3 (S. Shah)
 *           - Added support for XHTML1.1
 *
 *           Version 1.2b
 *           - fixed bug with incorrect xml doctype
 *
 *           Version 1.2
 *           - Now html_doctype can automatically send Content-Type headers to the
 *             browser. To disable set parameter "sendheaders" to FALSE.
 * 
 * Author:   André Rabold
 * Contribs: S. Shah
 * Idea:     Peter Turcan
 *           Monte Ohrt
 *           Matthias Mohr
 * Modified: 2004/04/23
 * Version:  1.5
 * -------------------------------------------------------------
 */
function smarty_function_html_doctype($params, &$smarty)
{
  // Default values:
  $xhtml            = true;
  $type             = "Transitional";
  $dtd              = true;
  $omitxml          = false;
  $encoding         = "UTF-8";
  $sendheaders      = true;
  $force_opera_html = false;
  
  extract($params);
  
  $type = ucfirst( strtolower($type) ); // standardise $type's case
  if (headers_sent())
    $sendheaders = false;

  // DOCTYPE Header  
  $header = "";

  // Impliment opera check and overide
  if($force_opera_html && $xhtml)
  {
      if (stristr($_SERVER['HTTP_USER_AGENT'],"Opera 7")||stristr($_SERVER['HTTP_USER_AGENT'],"Opera/7"))
      {
          $xhtml = false;
          $version = 4.01;
      }
  }
  
  
  // XHTML
  if ($xhtml) {
    if ($sendheaders) {
      // first check if browser accepts application/xhtml+xml content type
      if (stristr($_SERVER['HTTP_ACCEPT'],"application/xhtml+xml")) {
        header("Content-Type: application/xhtml+xml; charset=$encoding");
        $GLOBALS["HTML_DOCTYPE"] = "xml";//******
      }
      elseif (stristr($_SERVER['HTTP_ACCEPT'],"application/xml")) {
        header("Content-Type: application/xml; charset=$encoding");
        $GLOBALS["HTML_DOCTYPE"] = "xml";//******
      }
      elseif (stristr($_SERVER['HTTP_ACCEPT'],"text/xml")) {
        header("Content-Type: text/xml; charset=$encoding");
        $GLOBALS["HTML_DOCTYPE"] = "xml";//******
      }
      //Send Opera 7.0 application/xhtml+xml
      elseif (stristr($_SERVER['HTTP_USER_AGENT'],"Opera 7")||stristr($_SERVER['HTTP_USER_AGENT'],"Opera/7")) {
        header("Content-Type: application/xhtml+xml; charset=$encoding");
        $GLOBALS["HTML_DOCTYPE"] = "xml";//******
      }
      //Send everyone else text/html
      else {
        header("Content-Type: text/html; charset=$encoding");
        $GLOBALS["HTML_DOCTYPE"] = "html";//******
      }
    }
   
    if (!isset($version) || $version > 1.1) {
      $version = "1.0";  // default version for XHMTL
	}
    if ($dtd === true) {
      // Add default DTD
      if ($version == "1.0" && strtolower($type) == "strict")
        $dtd = "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd";
      else if ($version == "1.0" && strtolower($type) == "transitional")
        $dtd = "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd";
      else if ($version == "1.0" && strtolower($type) == "frameset")
        $dtd = "http://www.w3.org/TR/xhtml1/DTD/xhtml1-frameset.dtd";
      else if ($version == "1.1")
        $dtd = "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd";
      else
        $dtd = ""; // unknown XHTML version and/or type
    }

    if (!$omitxml) {
      if (trim($encoding) != "")
        $header .= "<?xml version=\"1.0\" encoding=\"$encoding\"?>\n";
      else
        $header .= "<?xml version=\"1.0\"?>\n";
    }
    
    // For some reasons "html" is written in lowercase here (check w3.org)
    if ($version == "1.1")
      $header .= "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.1//EN\"";
    else //default
      $header .= "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML $version $type//EN\"";

    if ($dtd != "" && $dtd !== false)
      $header .= " \"$dtd\"";
    $header .= ">\n";
  }
  // HTML
  else {
    if ($sendheaders) {
      header("Content-Type: text/html; charset=$encoding");
        $GLOBALS["HTML_DOCTYPE"] = "html";
    }
    
    if ($version == "2.0" || intval($version) == 2) {
      // does anybody still uses this?
      $header .= "<!DOCTYPE HTML PUBLIC \"-//IETF//DTD HTML//EN\">\n";
    }
    else if ($version == "3.2" || intval($version) == 3) {
      // does anybody still uses this?
      $header .= "<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 3.2 Final//EN\">\n";
    }
    else {
      if (!isset($version))
        $version = "4.01"; // default version for HTML
      elseif ($version == "4.0" || $version == "4.00")
        $version = "4.0";
      
      if ($dtd === true) {
        // Add default DTD
        if ($version === "4.01" && (strtolower($type) == "strict" || empty($type)))
          $dtd = "http://www.w3.org/TR/html4/strict.dtd";
        elseif ($version === "4.01" && strtolower($type) == "transitional") 
          $dtd = "http://www.w3.org/TR/html4/loose.dtd";
        elseif ($version === "4.01" && strtolower($type) == "frameset")
          $dtd = "http://www.w3.org/TR/html4/frameset.dtd";
        elseif ($version === "4.0" && (strtolower($type) == "strict" || empty($type)))
          $dtd = "http://www.w3.org/TR/REC-html40/strict.dtd";
        elseif ($version === "4.0" && strtolower($type) == "transitional")
          $dtd = "http://www.w3.org/TR/REC-html40/loose.dtd";
        elseif ($version === "4.0" && strtolower($type) == "frameset")
          $dtd = "http://www.w3.org/TR/REC-html40/frameset.dtd";
        else
          $dtd = ""; // no default DTD (e.g. version 3.2 and 2.0 doesn't use a DTD)
      }
      
      if (empty($type) || strtolower($type) == "strict")
        $header .= "<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML $version//EN\"";
      else
        $header .= "<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML $version $type//EN\"";
      if ($dtd != "" && $dtd !== false)
        $header .= " \"$dtd\"";
      $header .= ">\n";
    }
  }
    
  return $header;
}
?>
