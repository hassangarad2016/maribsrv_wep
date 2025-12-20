<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

class TrustProxies extends Middleware
{
    protected $proxies = '*';

    protected $headers;

    public function __construct()
    {
        // استخدم AWS_ELB إن وجد، وإلا فالأعلام الأساسية فقط.
        $this->headers = \defined(Request::class . '::HEADER_X_FORWARDED_AWS_ELB')
            ? Request::HEADER_X_FORWARDED_AWS_ELB
            : (
                SymfonyRequest::HEADER_X_FORWARDED_FOR |
                SymfonyRequest::HEADER_X_FORWARDED_HOST |
                SymfonyRequest::HEADER_X_FORWARDED_PORT |
                SymfonyRequest::HEADER_X_FORWARDED_PROTO
            );
    }
}
