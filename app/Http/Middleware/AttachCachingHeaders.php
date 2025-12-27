<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachCachingHeaders
{
    private const JSON_MAX_AGE = 60;
    private const IMAGE_MAX_AGE = 604800;

    private const CACHE_TTL_SHORT = 60;
    private const CACHE_TTL_MEDIUM = 300;
    private const CACHE_TTL_LONG = 3600;

    private const CACHE_RULES = [
        // Public cache (shared)
        ['pattern' => 'api/areas', 'ttl' => self::CACHE_TTL_LONG, 'visibility' => 'public'],
        ['pattern' => 'api/cities', 'ttl' => self::CACHE_TTL_LONG, 'visibility' => 'public'],
        ['pattern' => 'api/countries', 'ttl' => self::CACHE_TTL_LONG, 'visibility' => 'public'],
        ['pattern' => 'api/states', 'ttl' => self::CACHE_TTL_LONG, 'visibility' => 'public'],
        ['pattern' => 'api/get-languages', 'ttl' => self::CACHE_TTL_LONG, 'visibility' => 'public'],
        ['pattern' => 'api/get-categories', 'ttl' => self::CACHE_TTL_LONG, 'visibility' => 'public'],
        ['pattern' => 'api/get-parent-categories', 'ttl' => self::CACHE_TTL_LONG, 'visibility' => 'public'],
        ['pattern' => 'api/get-customfields', 'ttl' => self::CACHE_TTL_LONG, 'visibility' => 'public'],
        ['pattern' => 'api/get-limits', 'ttl' => self::CACHE_TTL_LONG, 'visibility' => 'public'],
        ['pattern' => 'api/get-report-reasons', 'ttl' => self::CACHE_TTL_LONG, 'visibility' => 'public'],
        ['pattern' => 'api/get-system-settings', 'ttl' => self::CACHE_TTL_LONG, 'visibility' => 'public'],
        ['pattern' => 'api/get-payment-settings', 'ttl' => self::CACHE_TTL_LONG, 'visibility' => 'public'],
        ['pattern' => 'api/seo-settings', 'ttl' => self::CACHE_TTL_LONG, 'visibility' => 'public'],
        ['pattern' => 'api/faq', 'ttl' => self::CACHE_TTL_LONG, 'visibility' => 'public'],
        ['pattern' => 'api/blogs', 'ttl' => self::CACHE_TTL_MEDIUM, 'visibility' => 'public'],
        ['pattern' => 'api/blog-tags', 'ttl' => self::CACHE_TTL_LONG, 'visibility' => 'public'],
        ['pattern' => 'api/tips', 'ttl' => self::CACHE_TTL_LONG, 'visibility' => 'public'],
        ['pattern' => 'api/topics', 'ttl' => self::CACHE_TTL_LONG, 'visibility' => 'public'],
        ['pattern' => 'api/featured-ads-configs', 'ttl' => self::CACHE_TTL_LONG, 'visibility' => 'public'],
        ['pattern' => 'api/featured-ads-configs/*', 'ttl' => self::CACHE_TTL_LONG, 'visibility' => 'public'],
        ['pattern' => 'api/get-slider', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'public'],
        ['pattern' => 'api/get-featured-section', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'public'],
        ['pattern' => 'api/ads/featured/count', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'public'],
        ['pattern' => 'api/metal-rates', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'public'],
        ['pattern' => 'api/currency-rates', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'public'],
        ['pattern' => 'api/currency-rates/history', 'ttl' => self::CACHE_TTL_MEDIUM, 'visibility' => 'public'],
        ['pattern' => 'api/manual-banks', 'ttl' => self::CACHE_TTL_LONG, 'visibility' => 'public'],
        ['pattern' => 'api/manual-payments/banks', 'ttl' => self::CACHE_TTL_LONG, 'visibility' => 'public'],
        ['pattern' => 'api/get-services', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'public'],
        ['pattern' => 'api/services/*', 'ttl' => self::CACHE_TTL_MEDIUM, 'visibility' => 'public'],
        ['pattern' => 'api/service-reviews', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'public'],
        ['pattern' => 'api/get-item', 'ttl' => self::CACHE_TTL_MEDIUM, 'visibility' => 'public'],
        ['pattern' => 'api/items/search', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'public'],
        ['pattern' => 'api/get-seller', 'ttl' => self::CACHE_TTL_MEDIUM, 'visibility' => 'public'],
        ['pattern' => 'api/get-package', 'ttl' => self::CACHE_TTL_LONG, 'visibility' => 'public'],
        ['pattern' => 'api/storefront/ui-config', 'ttl' => self::CACHE_TTL_LONG, 'visibility' => 'public'],
        ['pattern' => 'api/storefront/stores', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'public'],
        ['pattern' => 'api/storefront/stores/*/products', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'public'],
        ['pattern' => 'api/storefront/stores/*/reviews', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'public'],
        ['pattern' => 'api/storefront/stores/*', 'ttl' => self::CACHE_TTL_MEDIUM, 'visibility' => 'public'],
        ['pattern' => 'api/products/*/purchase-options', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'public'],
        ['pattern' => 'api/verification/metadata', 'ttl' => self::CACHE_TTL_LONG, 'visibility' => 'public'],
        ['pattern' => 'api/verification-fields', 'ttl' => self::CACHE_TTL_LONG, 'visibility' => 'public'],
        ['pattern' => 'api/web/experience', 'ttl' => self::CACHE_TTL_LONG, 'visibility' => 'public'],
        ['pattern' => 'api/challenges', 'ttl' => self::CACHE_TTL_MEDIUM, 'visibility' => 'public'],
        ['pattern' => 'api/delivery-prices', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'public'],

        // Private cache (per-user)
        ['pattern' => 'api/action-requests/*', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'private'],
        ['pattern' => 'api/ad-drafts/*', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'private'],
        ['pattern' => 'api/addresses', 'ttl' => self::CACHE_TTL_MEDIUM, 'visibility' => 'private'],
        ['pattern' => 'api/addresses/*', 'ttl' => self::CACHE_TTL_MEDIUM, 'visibility' => 'private'],
        ['pattern' => 'api/blocked-users', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'private'],
        ['pattern' => 'api/cart', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'private'],
        ['pattern' => 'api/cart/delivery-payment-timing', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'private'],
        ['pattern' => 'api/checkout-info', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'private'],
        ['pattern' => 'api/chat-list', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'private'],
        ['pattern' => 'api/chat-messages', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'private'],
        ['pattern' => 'api/get-favourite-item', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'private'],
        ['pattern' => 'api/get-notification-list', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'private'],
        ['pattern' => 'api/notification-preferences', 'ttl' => self::CACHE_TTL_LONG, 'visibility' => 'private'],
        ['pattern' => 'api/notifications/unread-count', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'private'],
        ['pattern' => 'api/notifications', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'private'],
        ['pattern' => 'api/orders/*/invoice.pdf', 'ttl' => self::CACHE_TTL_MEDIUM, 'visibility' => 'private'],
        ['pattern' => 'api/orders/*', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'private'],
        ['pattern' => 'api/orders', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'private'],
        ['pattern' => 'api/payment-transactions', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'private'],
        ['pattern' => 'api/user/preferences', 'ttl' => self::CACHE_TTL_LONG, 'visibility' => 'private'],
        ['pattern' => 'api/user-profile-stats', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'private'],
        ['pattern' => 'api/user-referral-points', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'private'],
        ['pattern' => 'api/user-orders', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'private'],
        ['pattern' => 'api/my-items', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'private'],
        ['pattern' => 'api/my-services', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'private'],
        ['pattern' => 'api/my-review', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'private'],
        ['pattern' => 'api/my-service-reviews', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'private'],
        ['pattern' => 'api/item-buyer-list', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'private'],
        ['pattern' => 'api/service-requests/*/purchase-options', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'private'],
        ['pattern' => 'api/service-requests/*', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'private'],
        ['pattern' => 'api/service-requests', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'private'],
        ['pattern' => 'api/store/dashboard/summary', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'private'],
        ['pattern' => 'api/store/dashboard/followers', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'private'],
        ['pattern' => 'api/store/orders', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'private'],
        ['pattern' => 'api/store/manual-payments/*', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'private'],
        ['pattern' => 'api/store/manual-payments', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'private'],
        ['pattern' => 'api/store/onboarding', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'private'],
        ['pattern' => 'api/storefront/stores/*/follow-status', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'private'],
        ['pattern' => 'api/store-gateway-accounts', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'private'],
        ['pattern' => 'api/store-gateways', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'private'],
        ['pattern' => 'api/stores/*/gateways', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'private'],
        ['pattern' => 'api/manual-payment-requests/*', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'private'],
        ['pattern' => 'api/manual-payment-requests', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'private'],
        ['pattern' => 'api/verification-request', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'private'],
        ['pattern' => 'api/wifi/admin/networks', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'private'],
        ['pattern' => 'api/wifi/admin/reports', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'private'],
        ['pattern' => 'api/wifi/admin/reputation-counters', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'private'],
        ['pattern' => 'api/wifi/networks', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'private'],
        ['pattern' => 'api/wifi/orders/*/code', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'private'],
        ['pattern' => 'api/wifi/owner/batches/*', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'private'],
        ['pattern' => 'api/wifi/owner/networks/*/codes', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'private'],
        ['pattern' => 'api/wifi/owner/networks/*/plans', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'private'],
        ['pattern' => 'api/wifi/owner/networks/*/stats', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'private'],
        ['pattern' => 'api/wifi/owner/networks/*', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'private'],
        ['pattern' => 'api/wifi/owner/networks', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'private'],
        ['pattern' => 'api/wifi/owner/plans/*/batches', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'private'],
        ['pattern' => 'api/wifi/owner/plans/*', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'private'],
        ['pattern' => 'api/wifi/plans', 'ttl' => self::CACHE_TTL_SHORT, 'visibility' => 'private'],
    ];

    public function handle(Request $request, Closure $next)
    {
        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $next($request);

        $cacheRule = $this->cacheRule($request);

        if (! $this->shouldProcess($request, $response, $cacheRule)) {
            return $response;
        }

        $etag = $response->headers->get('ETag');

        if ($etag === null) {
            if ($response->getStatusCode() === 304) {
                return $response;
            }

            $hash = $this->generateHash($response);

            if ($hash === null) {
                return $response;
            }

            $etag = '"' . $hash . '"';
            $response->headers->set('ETag', $etag);
        }

        $maxAge = $this->determineMaxAge($request, $response, $cacheRule);

        if ($maxAge !== null) {
            $isPrivate = $this->shouldUsePrivateCache($request, $cacheRule);
            $response->headers->set('Cache-Control', $this->cacheControlValue($response, $maxAge, $isPrivate));
            $response->headers->set('Expires', $this->formatHttpDate(Carbon::now('UTC')->addSeconds($maxAge)));
        }

        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            $ifNoneMatch = $request->headers->get('If-None-Match');

            if ($ifNoneMatch !== null) {
                if (trim($ifNoneMatch) === '*') {
                    $response->setNotModified();

                    return $response;
                }

                foreach (array_map('trim', explode(',', $ifNoneMatch)) as $candidate) {
                    if ($candidate === $etag || $candidate === 'W/' . $etag) {
                        $response->setNotModified();

                        return $response;
                    }
                }
            }
        }

        return $response;
    }

    private function shouldProcess(Request $request, Response $response, ?array $cacheRule): bool
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return false;
        }

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 400) {
            return false;
        }

        if ($response instanceof StreamedResponse && ! $response instanceof BinaryFileResponse) {
            return false;
        }

        if ($cacheRule === null) {
            return false;
        }

        return $this->isJsonResponse($response) ||
            $this->isImageResponse($response) ||
            $response instanceof BinaryFileResponse;
    }

    private function generateHash(Response $response): ?string
    {
        if ($response instanceof BinaryFileResponse) {
            $file = $response->getFile();

            if ($file !== null && $file->isFile()) {
                return sha1_file($file->getPathname()) ?: null;
            }

            return null;
        }

        if (! method_exists($response, 'getContent')) {
            return null;
        }

        $content = $response->getContent();

        return is_string($content) ? sha1($content) : null;
    }

    private function determineMaxAge(Request $request, Response $response, ?array $cacheRule): ?int
    {
        if ($cacheRule !== null && isset($cacheRule['ttl'])) {
            return (int) $cacheRule['ttl'];
        }

        if ($this->isJsonResponse($response)) {
            return self::JSON_MAX_AGE;
        }

        if ($this->isImageResponse($response)) {
            return self::IMAGE_MAX_AGE;
        }

        return null;
    }

    private function cacheControlValue(Response $response, int $maxAge, bool $isPrivate): string
    {
        if ($this->isImageResponse($response)) {
            return sprintf('public, max-age=%d, immutable', $maxAge);
        }

        $visibility = $isPrivate ? 'private' : 'public';

        return sprintf('%s, max-age=%d, must-revalidate', $visibility, $maxAge);
    }

    private function shouldUsePrivateCache(Request $request, ?array $cacheRule = null): bool
    {
        $rule = $cacheRule ?? $this->cacheRule($request);

        return $rule !== null && ($rule['visibility'] ?? 'public') === 'private';
    }

    private function cacheRule(Request $request): ?array
    {
        $path = ltrim($request->path(), '/');

        foreach (self::CACHE_RULES as $rule) {
            if (Str::is($rule['pattern'], $path)) {
                return $rule;
            }
        }

        return null;
    }

    private function isJsonResponse(Response $response): bool
    {
        $contentType = strtolower((string) $response->headers->get('Content-Type', ''));

        return str_contains($contentType, 'application/json');
    }

    private function isImageResponse(Response $response): bool
    {
        $contentType = strtolower((string) $response->headers->get('Content-Type', ''));

        return str_starts_with($contentType, 'image/');
    }

    private function formatHttpDate(Carbon $date): string
    {
        return $date->setTimezone('GMT')->format('D, d M Y H:i:s') . ' GMT';
    }
}
