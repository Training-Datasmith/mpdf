<?php

namespace Mpdf\Utils;

class Path
{
    /**
     * Convert a relative path to an absolute path
     *
     * @param string $relPath
     * @param string $basePath The absolute path to prefix to the relative path (can be a path or URL)
     * @return string
     */
    public static function relative_to_absolute_path($rel_path, $base_path)
    {
        // Fix Windows paths
        $rel_path = str_replace("\\", '/', $rel_path);
        // mPDF 5.7.2
        if (strpos($rel_path, '//') === 0) {
            $scheme = parse_url($base_path, PHP_URL_SCHEME);
            $scheme = $scheme ?: 'http';
            $rel_path = $scheme . ':' . $rel_path;
        }
        // Inadvertently corrects "./path/etc" and "//www.domain.com/etc"
        $rel_path = preg_replace('|^./|', '', $rel_path);
        if (strpos($rel_path, '#') === 0) {
            return $rel_path;
        }
        // Skip schemes not supported by installed stream wrappers
        $wrappers = stream_get_wrappers();
        $pattern = sprintf('@^(?!%s)[a-z0-9\.\-+]+:.*@i', implode('|', $wrappers));
        if (preg_match($pattern, $rel_path)) {
            return $rel_path;
        }
        // It is a relative link
        if (strpos($rel_path, '../') === 0) {
            $backtrackamount = substr_count($rel_path, '../');
            $maxbacktrack = substr_count($base_path, '/') - 3;
            $filepath = str_replace('../', '', $rel_path);
            $rel_path = $base_path;
            // If it is an invalid relative link, then make it go to directory root
            if ($backtrackamount > $maxbacktrack) {
                $backtrackamount = $maxbacktrack;
            }
            // Backtrack some directories
            for ($i = 0; $i < $backtrackamount + 1; $i++) {
                $rel_path = substr($rel_path, 0, strrpos($rel_path, "/"));
            }
            // Make it an absolute path
            $rel_path .= '/' . $filepath;
            return $rel_path;
        }
        // It is a local link. Ignore potential file errors
        if ((strpos($rel_path, ":/") === false || strpos($rel_path, ":/") > 10) && !@is_file($rel_path)) {
            if (strpos($rel_path, '/') !== 0) {
                return $base_path . $rel_path;
            }
            $tr = parse_url($base_path);
            // mPDF 5.7.2
            $root = '';
            if (!empty($tr['scheme'])) {
                $root .= $tr['scheme'] . '://';
            }
            $root .= !empty($tr['host']) ? $tr['host'] : '';
            $root .= !empty($tr['port']) ? ':' . $tr['port'] : '';
            // mPDF 5.7.3
            $rel_path = $root . $rel_path;
        }
        return $rel_path;
    }
    /**
     * Normalize file path for local file system access.
     *
     * Converts URLs to local file paths when the base path is local.
     * Handles DOCUMENT_ROOT and relative paths.
     *
     * @param string $path File path or URL
     * @return string Normalized path
     */
    public static function normalize_local_file_path($path)
    {
        $tr = parse_url($path);
        $lp = __FILE__;
        $ap = realpath($lp);
        $ap = str_replace("\\", '/', $ap);
        $docroot = substr($ap, 0, strpos($ap, $lp));
        // WriteHTML parses all paths to full URLs; may be local file name
        // DOCUMENT_ROOT is not returned on IIS
        if (!empty($tr['scheme']) && !empty($tr['host']) && !empty($_SERVER['DOCUMENT_ROOT'])) {
            return $_SERVER['DOCUMENT_ROOT'] . $tr['path'];
        }
        if ($docroot) {
            return $docroot . $tr['path'];
        }
        return $path;
    }
}