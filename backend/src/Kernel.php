<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /**
     * Behind Traefik (TLS termination), the backend container receives plain
     * HTTP, so $request->isSecure()/getScheme() would report "http" unless we
     * trust Traefik's X-Forwarded-* headers. Required so Google OAuth builds
     * the redirect_uri as https:// (Google rejects an http:// redirect_uri).
     * Only the private Docker networks can reach this container.
     */
    public function boot(): void
    {
        parent::boot();
        Request::setTrustedProxies(
            ['10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', '127.0.0.1', '::1'],
            Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO
            | Request::HEADER_X_FORWARDED_HOST,
        );
    }
}
