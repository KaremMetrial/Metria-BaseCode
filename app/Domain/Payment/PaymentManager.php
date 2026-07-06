<?php

declare(strict_types=1);

namespace App\Domain\Payment;

use App\Domain\Payment\Contracts\PaymentGateway;
use App\Domain\Payment\Gateways\FawryGateway;
use App\Domain\Payment\Gateways\PaymobGateway;
use App\Domain\Payment\Gateways\PaytabsGateway;
use App\Domain\Payment\Gateways\StripeGateway;
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
        return $this->config->get('payments.default', 'stripe');
    }

    protected function createStripeDriver(): PaymentGateway
    {
        return new StripeGateway($this->config->get('payments.gateways.stripe', []));
    }

    protected function createPaymobDriver(): PaymentGateway
    {
        return new PaymobGateway($this->config->get('payments.gateways.paymob', []));
    }

    protected function createFawryDriver(): PaymentGateway
    {
        return new FawryGateway($this->config->get('payments.gateways.fawry', []));
    }

    protected function createPaytabsDriver(): PaymentGateway
    {
        return new PaytabsGateway($this->config->get('payments.gateways.paytabs', []));
    }
}
