<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

// Production sits behind an AWS load balancer: without this, $request->ip() is the balancer's private address
// (172.31.x.x) for EVERY visitor, so the IP in the logs (community / municipalities / elections pulls) and every
// per-IP counter (app-signature per-IP cap, Talent guest limits) saw one single "client".
//
// Only the load balancer's own network is trusted (a visitor connecting directly, or sending his own X-Forwarded-For,
// can NOT choose the IP we record: a header is believed only when it arrives from a trusted address) and ONLY the
// client-IP header is used - X-Forwarded-Proto / Host are deliberately NOT trusted, so URLs and the "is this https"
// answer behave exactly as before. Override the list with TRUSTED_PROXIES in .env (comma separated, e.g. a new VPC range).
class TrustProxies extends Middleware
{
    protected $headers = Request::HEADER_X_FORWARDED_FOR;

    protected function proxies()
    {
        $configured = env('TRUSTED_PROXIES');

        return $configured !== null && $configured !== '' ? array_map('trim', explode(',', $configured)) : ['172.31.0.0/16'];
    }
}
