<?php

declare (strict_types=1);
namespace Mpdf\Http;

use Mpdf\Log\Context as LogContext;
use Mpdf\Mpdf;
use Mpdf\Psr_Http_Message_Shim\Response;
use Mpdf\Psr_Http_Message_Shim\Stream;
use Mpdf\Psr_Log_Aware_Trait\Psr_Log_Aware_Trait;
use Psr\Http\Message\Request_Interface;
use Psr\Log\Logger_Interface;
class Curl_Http_Client implements \Mpdf\Http\Client_Interface, \Psr\Log\Logger_Aware_Interface
{
    use Psr_Log_Aware_Trait;
    private $mpdf;
    public function __construct(Mpdf $mpdf, Logger_Interface $logger)
    {
        $this->mpdf = $mpdf;
        $this->logger = $logger;
    }
    public function send_request(Request_Interface $request)
    {
        if (null === $request->get_uri()) {
            return new Response();
        }
        $url = $request->get_uri();
        $this->logger->debug(sprintf('Fetching (cURL) content of remote URL "%s"', $url), ['context' => Log_Context::REMOTE_CONTENT]);
        $response = new Response();
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->mpdf->curl_user_agent);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_NOBODY, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->mpdf->curl_timeout);
        if ($this->mpdf->curl_execution_timeout) {
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->mpdf->curl_execution_timeout);
        }
        if ($this->mpdf->curl_follow_location) {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        }
        if ($this->mpdf->curl_allow_unsafe_ssl_requests) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }
        if ($this->mpdf->curl_ca_certificate && is_file($this->mpdf->curl_ca_certificate)) {
            curl_setopt($ch, CURLOPT_CAINFO, $this->mpdf->curl_ca_certificate);
        }
        if ($this->mpdf->curl_proxy) {
            curl_setopt($ch, CURLOPT_PROXY, $this->mpdf->curl_proxy);
            if ($this->mpdf->curl_proxy_auth) {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $this->mpdf->curl_proxy_auth);
            }
        }
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, static function ($curl, $header) use (&$response) {
            $len = strlen($header);
            $header = explode(':', $header, 2);
            if (count($header) < 2) {
                // ignore invalid headers
                return $len;
            }
            $response = $response->with_header(trim($header[0]), trim($header[1]));
            return $len;
        });
        $data = curl_exec($ch);
        if (curl_error($ch)) {
            $message = sprintf('cURL error: "%s"', curl_error($ch));
            $this->logger->error($message, ['context' => Log_Context::REMOTE_CONTENT]);
            if ($this->mpdf->debug) {
                throw new \Mpdf\Mpdf_Exception($message);
            }
            $this->close_curl($ch);
            return $response;
        }
        $info = curl_getinfo($ch);
        if (isset($info['http_code']) && !str_starts_with((string) $info['http_code'], '2')) {
            $message = sprintf('HTTP error: %d', $info['http_code']);
            $this->logger->error($message, ['context' => Log_Context::REMOTE_CONTENT]);
            if ($this->mpdf->debug) {
                throw new \Mpdf\Mpdf_Exception($message);
            }
            $this->close_curl($ch);
            return $response->with_status($info['http_code']);
        }
        $this->close_curl($ch);
        return $response->with_status($info['http_code'])->with_body(Stream::create($data));
    }
    private function close_curl($ch)
    {
        if (PHP_VERSION_ID < 80000) {
            curl_close($ch);
        }
    }
}