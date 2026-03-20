<?php

declare (strict_types=1);
namespace Mpdf;

use Mpdf\File\Local_Content_Loader_Interface;
use Mpdf\File\Stream_Wrapper_Checker;
use Mpdf\Http\Client_Interface;
use Mpdf\Log\Context as LogContext;
use Mpdf\Psr_Http_Message_Shim\Request;
use Mpdf\Psr_Log_Aware_Trait\Psr_Log_Aware_Trait;
use Psr\Log\Logger_Interface;
class Asset_Fetcher implements \Psr\Log\Logger_Aware_Interface, \Mpdf\Asset_Fetcher_Interface
{
    use Psr_Log_Aware_Trait;
    private $mpdf;
    private $content_loader;
    private $http;
    public function __construct(Mpdf $mpdf, Local_Content_Loader_Interface $content_loader, Client_Interface $http, Logger_Interface $logger)
    {
        $this->mpdf = $mpdf;
        $this->content_loader = $content_loader;
        $this->http = $http;
        $this->logger = $logger;
    }
    public function fetch_data_from_path($path, $original_src = null)
    {
        /**
         * Prevents insecure PHP object injection through phar:// wrapper
         * @see https://github.com/mpdf/mpdf/issues/949
         * @see https://github.com/mpdf/mpdf/issues/1381
         */
        $wrapper_checker = new Stream_Wrapper_Checker($this->mpdf);
        if ($wrapper_checker->has_blacklisted_stream_wrapper($path)) {
            throw new \Mpdf\Exception\Asset_Fetching_Exception('File contains an invalid stream. Only ' . implode(', ', $wrapper_checker->get_whitelisted_stream_wrappers()) . ' streams are allowed.');
        }
        if ($original_src && $wrapper_checker->has_blacklisted_stream_wrapper($original_src)) {
            throw new \Mpdf\Exception\Asset_Fetching_Exception('File contains an invalid stream. Only ' . implode(', ', $wrapper_checker->get_whitelisted_stream_wrappers()) . ' streams are allowed.');
        }
        $this->mpdf->get_full_path($path);
        return $this->is_path_local($path) || $original_src !== null && $this->is_path_local($original_src) ? $this->fetch_local_content($path, $original_src) : $this->fetch_remote_content($path);
    }
    public function fetch_local_content($path, $original_src)
    {
        $data = '';
        if ($original_src && $this->mpdf->basepath_is_local && $check = @fopen($original_src, 'rb')) {
            fclose($check);
            // Block file:// URLs to prevent arbitrary local file disclosure via user-controlled HTML.
            // file:// is recognised as a local path by isPathLocal() but must not be fetched when
            // the source URL explicitly uses the file:// scheme (e.g. <img src="file:///etc/passwd">).
            if (stripos($original_src, 'file://') === 0) {
                $this->logger->warning(sprintf('Blocked file:// URL "%s" in local content fetch', $original_src), ['context' => Log_Context::REMOTE_CONTENT]);
                return $data;
            }
            $path = $original_src;
            $this->logger->debug(sprintf('Fetching content of file "%s" with local basepath', $path), ['context' => Log_Context::REMOTE_CONTENT]);
            return $this->content_loader->load($path);
        }
        if ($path && $check = @fopen($path, 'rb')) {
            fclose($check);
            // Same guard for the resolved $path.
            if (stripos($path, 'file://') === 0) {
                $this->logger->warning(sprintf('Blocked file:// URL "%s" in local content fetch', $path), ['context' => Log_Context::REMOTE_CONTENT]);
                return $data;
            }
            $this->logger->debug(sprintf('Fetching content of file "%s" with non-local basepath', $path), ['context' => Log_Context::REMOTE_CONTENT]);
            return $this->content_loader->load($path);
        }
        return $data;
    }
    public function fetch_remote_content($path)
    {
        $data = '';
        try {
            $this->logger->debug(sprintf('Fetching remote content of file "%s"', $path), ['context' => Log_Context::REMOTE_CONTENT]);
            /** @var \Mpdf\PsrHttpMessageShim\Response $response */
            $response = $this->http->send_request(new Request('GET', $path));
            if (!str_starts_with((string) $response->get_status_code(), '2')) {
                $message = sprintf('Non-OK HTTP response "%s" on fetching remote content "%s" because of an error', $response->get_status_code(), $path);
                if ($this->mpdf->debug) {
                    throw new \Mpdf\Mpdf_Exception($message);
                }
                $this->logger->info($message);
                return $data;
            }
            $data = $response->get_body()->get_contents();
        } catch (\InvalidArgumentException $e) {
            $message = sprintf('Unable to fetch remote content "%s" because of an error "%s"', $path, $e->get_message());
            if ($this->mpdf->debug) {
                throw new \Mpdf\Mpdf_Exception($message, 0, E_ERROR, null, null, $e);
            }
            $this->logger->warning($message);
        }
        return $data;
    }
    public function is_path_local($path)
    {
        return str_starts_with($path, 'file://') || strpos($path, '://') === false;
        // @todo More robust implementation
    }
}