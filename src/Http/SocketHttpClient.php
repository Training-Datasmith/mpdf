<?php

declare (strict_types=1);
namespace Mpdf\Http;

use Mpdf\Log\Context as LogContext;
use Mpdf\Psr_Http_Message_Shim\Response;
use Mpdf\Psr_Http_Message_Shim\Stream;
use Mpdf\Psr_Log_Aware_Trait\Psr_Log_Aware_Trait;
use Psr\Http\Message\Request_Interface;
use Psr\Log\Logger_Interface;
class Socket_Http_Client implements \Mpdf\Http\Client_Interface, \Psr\Log\Logger_Aware_Interface
{
    use Psr_Log_Aware_Trait;
    public function __construct(Logger_Interface $logger)
    {
        $this->logger = $logger;
    }
    public function send_request(Request_Interface $request)
    {
        if (null === $request->get_uri()) {
            return new Response();
            // @todo throw exception
        }
        $url = $request->get_uri();
        if (is_string($url)) {
            $url = new Uri($url);
        }
        $timeout = 1;
        $file = $url->get_path() ?: '/';
        $scheme = $url->get_scheme();
        $port = $url->get_port() ?: 80;
        $prefix = '';
        if ($scheme === 'https') {
            $prefix = 'ssl://';
            $port = $url->get_port() ?: 443;
        }
        $query = $url->get_query();
        if ($query) {
            $file .= '?' . $query;
        }
        $socket_path = $prefix . $url->get_host();
        $this->logger->debug(sprintf('Opening socket on %s:%s of URL "%s"', $socket_path, $port, $request->get_uri()), ['context' => Log_Context::REMOTE_CONTENT]);
        $response = new Response();
        if (!$fh = @fsockopen($socket_path, $port, $errno, $errstr, $timeout)) {
            $this->logger->error(sprintf('Socket error "%s": "%s"', $errno, $errstr), ['context' => Log_Context::REMOTE_CONTENT]);
            return $response;
        }
        $get_request = 'GET ' . $file . ' HTTP/1.1' . "\r\n" . 'Host: ' . $url->get_host() . " \r\n" . 'Connection: close' . "\r\n\r\n";
        fwrite($fh, $get_request);
        $http_header = fgets($fh, 1024);
        if (!$http_header) {
            return $response;
            // @todo throw exception
        }
        preg_match('@HTTP/(?P<protocolVersion>[\d\.]+) (?P<httpStatusCode>[\d]+) .*@', $http_header, $parsed_header);
        if (!$parsed_header) {
            return $response;
            // @todo throw exception
        }
        $response = $response->with_status($parsed_header['httpStatusCode']);
        while (!feof($fh)) {
            $s = fgets($fh, 1024);
            if ($s === "\r\n") {
                break;
            }
            preg_match('/^(?P<headerName>.*?): ?(?P<headerValue>.*)$/', $s, $parsed_header);
            if (!$parsed_header) {
                continue;
            }
            $response = $response->with_header($parsed_header['headerName'], trim($parsed_header['headerValue']));
        }
        $body = '';
        while (!feof($fh)) {
            $line = fgets($fh, 1024);
            $body .= $line;
        }
        fclose($fh);
        $stream = Stream::create($body);
        $stream->rewind();
        return $response->with_body($stream);
    }
}