<?php
/**
 * @name AutoFixImageSize
 * @version 1.0.4
 * @author servingpixels April 2025
 * @author Based on Gerrit van Aaken <gerrit@praegnanz.de> April 2011 – January 2013
 * @license GPLv2
 *
 * Fixes img elements with wrong width/height attributes.
 * Uses pThumb for generating correctly sized physical image files.
 * For MODX 3.x
 *
 * Must be executed at "OnWebPagePrerender"
 */

$output = &$modx->resource->_output;

$config = $modx->getConfig();

preg_match_all('|<img.*?src=["\'](.*?)["\'].*?>|i', $output, $filenames);

foreach ($filenames[1] as $i => $filename) {
    $img_old = $filenames[0][$i];
    $allowcaching = false;

    if (strpos($filename, "?") === false || strpos($filename, "/phpthumbof") === false) {
        if (stripos($filename, "http://") === 0 || stripos($filename, "https://") === 0) {
            $pre = "";
            if ($config['phpthumb_nohotlink_enabled']) {
                foreach (explode(",", $config['phpthumb_nohotlink_valid_domains']) as $alldomain) {
                    if (stripos($filename, trim($alldomain)) !== false) {
                        $allowcaching = true;
                    }
                }
            } else {
                $allowcaching = true;
            }
        } else {
            $pre = MODX_BASE_PATH;
            $allowcaching = true;
        }
    }

    $mypath = $pre . str_replace('%20', ' ', $filename);
    if ($allowcaching && $dimensions = @getimagesize($mypath)) {
        preg_match('|width=["\']([0-9]+)["\']|i', $filenames[0][$i], $widths);
        $width = $widths[1] ?? false;
        if (!$width) {
            preg_match('|width:\s*([0-9]+)px|i', $filenames[0][$i], $widths);
            $width = $widths[1] ?? false;
        }

        preg_match('|height=["\']([0-9]+)["\']|i', $filenames[0][$i], $heights);
        $height = $heights[1] ?? false;
        if (!$height) {
            preg_match('|height:\s*([0-9]+)px|i', $filenames[0][$i], $heights);
            $height = $heights[1] ?? false;
        }

        if (($width && $width != $dimensions[0]) || ($height && $height != $dimensions[1])) {
            $filetype = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $options = [
                'input' => $filename,
                'options' => "f={$filetype}&h={$height}&w={$width}&iar=1"
            ];

            $cacheurl = $modx->runSnippet('pThumb', $options);
            $img_new = str_replace($filename, $cacheurl, $img_old);
            $output = str_replace($img_old, $img_new, $output);
        }
    }
}

$modx->resource->_output = &$output;
