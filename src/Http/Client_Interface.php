<?php

declare (strict_types=1);
namespace Mpdf\Http;

use Psr\Http\Message\Request_Interface;
interface Client_Interface
{
    public function send_request(Request_Interface $request);
}