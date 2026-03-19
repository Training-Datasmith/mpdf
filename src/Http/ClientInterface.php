<?php

declare(strict_types=1);

namespace Mpdf\Http;

use Psr\Http\Message\RequestInterface;

interface ClientInterface
{
    public function sendRequest(RequestInterface $request);

}
