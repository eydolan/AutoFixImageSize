<?php
/**
 * @name AutoFixImageSize
 * @version 1.0.2 pl
 * @author Gerrit van Aaken <gerrit@praegnanz.de> April 2011 – January 2013
 *
 * @license GPLv2
 *
 * Fixes img elements with wrong width/height attributes. 
 * Uses phpThumbOf for generating correctly sized physical image files.
 *
 * Must be executed at "OnWebPagePrerender"
 */

// get parsed document as string
$str = $modx->resource->_output;

// get configuration from global object
$config = $modx->getConfig();

// find all img elements with a src attribute
preg_match_all('|\<img.*?src=[",\'](.*?)[",\'].*?[^>]+\>|i', $str, $filenames);

// loop through all found img elements
foreach($filenames[1] as $i => $filename) {

  $img_old = $filenames[0][$i];
  $allowcaching = false; // pessimistic

  // is file already cached?
  if (strpos($filename,"?") == false || strpos($filename,"/phpthumb") == false) {

    // check if external caching is allowed
    if (substr($filename,0,7) == "http://" || substr($filename,0,8) == "https://") {
      $pre = "";
      if ($config['phpthumb_nohotlink_enabled']) {
        foreach (explode(",", $config['phpthumb_nohotlink_valid_domains']) as $alldomain) {
          if ( strpos(strtolower($filename), strtolower(trim($alldomain))) != false) {
            $allowcaching = true;
          }
        } 
      } else {
        $allowcaching = true;
      }
    } else {
      $pre = $config['base_path'];
      $allowcaching = true;
    }
  }
  
  // do we have physical access to the file?

$mypath = $pre.str_replace('%20', ' ', $filename);
  if ($allowcaching && $dimensions = @getimagesize($mypath, $info)) {

    // find width and height attribut and save value
    preg_match_all('|width=[",\']([0-9]+?)[",\']|i', $filenames[0][$i], $widths);
    if (isset($widths[1][0])) {
      $width = $widths[1][0];
    } else {
      preg_match_all('|width:\s*([0-9]+?)px|i', $filenames[0][$i], $widths);
        if (isset($widths[1][0])) {
          $width = $widths[1][0];
        } else {
          $width = false;
        }
    }
    preg_match_all('|height=[",\']([0-9]+?)[",\']|i', $filenames[0][$i], $heights);
    if (isset($heights[1][0])) {
      $height = $heights[1][0];
    } else {
      preg_match_all('|height:\s*([0-9]+?)px|i', $filenames[0][$i], $heights);
        if (isset($heights[1][0])) {
          $height = $heights[1][0];
        } else {
          $height = false;
        }
    }

    // if resizing needed...
    if (($width && $width != $dimensions[0]) || ($height && $height != $dimensions[1])) {

      // prepare resizing metadata
      $filetype = strtolower(substr($filename, strrpos($filename,".")+1));
      $image = array();
      $image['input'] = $filename;
      $image['options'] = "f=".$filetype."&h=".$height."&w=".$width."&iar=1"; 

      // perform physical resizing and caching via phpthumbof
      $cacheurl = $modx->runSnippet('phpthumbof',$image);

      // set freshly cached image file location into old src attribute
      $img_new = str_replace($filename, $cacheurl, $img_old);  

      // replace old image element with new one on whole page content
      $str = str_replace($img_old, $img_new, $str);  
    }
  }
}

// exchange the output string with the replaced one
$modx->resource->_output = $str;
