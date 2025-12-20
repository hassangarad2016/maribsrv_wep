<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    protected $proxies = '*';

    // متوافق مع نسخة Symfony/Laravel على الاستضافة
    protected $headers = Request::HEADER_X_FORWARDED_AWS_ELB;
}
