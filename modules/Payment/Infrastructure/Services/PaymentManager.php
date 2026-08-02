<?php

declare(strict_types=1);

namespace Modules\Payment\Infrastructure\Services;

use Modules\Payment\Domain\Contracts\PaymentGateway;
use Modules\Payment\Infrastructure\Gateways\FawryGateway;
use Modules\Payment\Infrastructure\Gateways\PaymobGateway;
use Modules\Payment\Infrastructure\Gateways\PaytabsGateway;
use Modules\Payment\Infrastructure\Gateways\StripeGateway;
use Illuminate\Support\Manager;

/**
 * Manager/Driver pattern: resolves the configured gateway by name.
 *
 *   app(PaymentManager::class)->driver('paymob')->createPayment(...)
 *
 * Add a custom provider from any service provider:
 *
 *   app(PaymentManager::class)->extend('mygateway', fn () => new MyGateway(
 *       config('payments.gateways.mygateway'),
 *   ));
 *
 * @method PaymentGateway driver(string|null $driver = null)
 */
class PaymentManager extends Manager
{
    public function getDefaultDriver(): string
    {
        $default = $this->config->get('payments.default', 'stripe');
        return is_string($default) ? $default : 'stripe';
    }

    protected function createStripeDriver(): PaymentGateway
    {
        $config = $this->config->get('payments.gateways.stripe', []);
        return new StripeGateway(is_array($config) ? $config : []);
    }

    protected function createPaymobDriver(): PaymentGateway
    {
        $config = $this->config->get('payments.gateways.paymob', []);
        return new PaymobGateway(is_array($config) ? $config : []);
    }

    protected function createFawryDriver(): PaymentGateway
    {
        $config = $this->config->get('payments.gateways.fawry', []);
        return new FawryGateway(is_array($config) ? $config : []);
    }

    protected function createPaytabsDriver(): PaymentGateway
    {
        $config = $this->config->get('payments.gateways.paytabs', []);
        return new PaytabsGateway(is_array($config) ? $config : []);
    }
}
