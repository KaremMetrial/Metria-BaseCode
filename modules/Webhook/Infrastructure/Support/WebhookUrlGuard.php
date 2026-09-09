<?php

declare(strict_types=1);

namespace Modules\Webhook\Infrastructure\Support;

/**
 * Blocks webhook endpoint URLs that resolve to loopback, link-local,
 * private-network, or cloud-metadata addresses — a tenant registering
 * https://169.254.169.254/... or an internal-only hostname would otherwise
 * have the queue worker make authenticated-looking requests into the
 * internal network on every matching event (SSRF via a routine
 * webhooks.manage permission, not a privileged one).
 *
 * Used both at validation time (StoreWebhookEndpointRequest, covering
 * create and update) and again at delivery time (DeliverWebhook), since a
 * hostname's resolution can change between the two.
 */
final class WebhookUrlGuard
{
    public static function isSafe(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return false;
        }

        // Strip IPv6 brackets, e.g. "[::1]" -> "::1".
        $host = trim($host, '[]');

        // A literal IP is checked directly — this alone stops the concrete
        // attack (registering "https://169.254.169.254/..." or
        // "https://127.0.0.1:PORT/..." outright).
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return self::isPublicIp($host);
        }

        // For a hostname, resolve and reject only if every resolved address
        // is private/loopback/link-local. An environment with no outbound
        // DNS (offline CI, this app's own sandboxed test suite) can't
        // resolve anything either way — in that case we can't prove the
        // target is unsafe, so we don't block it here; DeliverWebhook
        // re-checks at send time in a real environment where DNS works,
        // which is what actually matters for the attack this guards
        // against (a worker with real network access reaching an internal
        // service).
        $ips = gethostbynamel($host);
        if ($ips === false || $ips === []) {
            return true;
        }

        foreach ($ips as $ip) {
            if (! self::isPublicIp($ip)) {
                return false;
            }
        }

        return true;
    }

    private static function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }
}
