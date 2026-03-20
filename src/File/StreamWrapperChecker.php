<?php

declare (strict_types=1);
namespace Mpdf\File;

use Mpdf\Mpdf;
final class Stream_Wrapper_Checker
{
    private $mpdf;
    public function __construct(Mpdf $mpdf)
    {
        $this->mpdf = $mpdf;
    }
    /**
     * @param string $filename
     * @return bool
     * @since 7.1.8
     */
    public function has_blacklisted_stream_wrapper($filename)
    {
        if (strpos($filename, '://') > 0) {
            $wrappers = stream_get_wrappers();
            $whitelist_stream_wrappers = $this->get_whitelisted_stream_wrappers();
            foreach ($wrappers as $wrapper) {
                if (in_array($wrapper, $whitelist_stream_wrappers)) {
                    continue;
                }
                if (stripos($filename, $wrapper . '://') === 0) {
                    return true;
                }
            }
        }
        return false;
    }
    public function get_whitelisted_stream_wrappers()
    {
        return array_diff($this->mpdf->whitelist_stream_wrappers, ['phar']);
        // remove 'phar' (security issue)
    }
}