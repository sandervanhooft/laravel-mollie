<?php
/**
 * Copyright (c) 2016, Mollie B.V.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * - Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 * - Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR AND CONTRIBUTORS ``AS IS'' AND ANY
 * EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE AUTHOR OR CONTRIBUTORS BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY
 * OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH
 * DAMAGE.
 *
 * @license     Berkeley Software Distribution License (BSD-License 2) http://www.opensource.org/licenses/bsd-license.php
 * @author      Mollie B.V. <info@mollie.com>
 * @copyright   Mollie B.V.
 * @link        https://www.mollie.com
 */
namespace Mollie\Laravel;

use Illuminate\Contracts\Container\Container;
use Illuminate\Foundation\Application as LaravelApplication;
use Illuminate\Support\ServiceProvider;
use Laravel\Lumen\Application as LumenApplication;
use Mollie\Api\Endpoints\ChargebackEndpoint;
use Mollie\Api\Endpoints\CustomerEndpoint;
use Mollie\Api\Endpoints\CustomerPaymentsEndpoint;
use Mollie\Api\Endpoints\InvoiceEndpoint;
use Mollie\Api\Endpoints\MandateEndpoint;
use Mollie\Api\Endpoints\MethodEndpoint;
use Mollie\Api\Endpoints\OrderEndpoint;
use Mollie\Api\Endpoints\OrderLineEndpoint;
use Mollie\Api\Endpoints\OrderRefundEndpoint;
use Mollie\Api\Endpoints\OrganizationEndpoint;
use Mollie\Api\Endpoints\PaymentCaptureEndpoint;
use Mollie\Api\Endpoints\PaymentChargebackEndpoint;
use Mollie\Api\Endpoints\PaymentEndpoint;
use Mollie\Api\Endpoints\PaymentRefundEndpoint;
use Mollie\Api\Endpoints\PermissionEndpoint;
use Mollie\Api\Endpoints\ProfileEndpoint;
use Mollie\Api\Endpoints\RefundEndpoint;
use Mollie\Api\Endpoints\SettlementsEndpoint;
use Mollie\Api\Endpoints\ShipmentEndpoint;
use Mollie\Api\Endpoints\SubscriptionEndpoint;
use Mollie\Api\MollieApiClient;
use Mollie\Laravel\Wrappers\MollieApiWrapper;

/**
 * Class MollieServiceProvider.
 */
class MollieServiceProvider extends ServiceProvider
{
    /**
     * Boot the service provider.
     *
     * @return void
     */
    public function boot()
    {
        $this->setupConfig();
        $this->extendSocialite();
    }

    /**
     * Setup the config.
     *
     * @return void
     */
    protected function setupConfig()
    {
        $source = realpath(__DIR__ . '/../config/mollie.php');

        // Check if the application is a Laravel OR Lumen instance to properly merge the configuration file.
        if ($this->app instanceof LaravelApplication && $this->app->runningInConsole()) {
            $this->publishes([$source => config_path('mollie.php')]);
        } elseif ($this->app instanceof LumenApplication) {
            $this->app->configure('mollie');
        }

        $this->mergeConfigFrom($source, 'mollie');
    }

    /**
     * Extend the Laravel Socialite factory class, if available.
     *
     * @return void
     */
    protected function extendSocialite()
    {
        if (interface_exists('Laravel\Socialite\Contracts\Factory')) {
            $socialite = $this->app->make('Laravel\Socialite\Contracts\Factory');

            $socialite->extend('mollie', function (Container $app) use ($socialite) {
                $config = $app['config']['services.mollie'];

                return $socialite->buildProvider(MollieConnectProvider::class, $config);
            });
        }
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerApiClient();
        $this->registerApiAdapter();
        $this->registerManager();
        $this->registerEndpoints();
    }

    /**
     * Register the Mollie API adapter class.
     *
     * @return void
     */
    protected function registerApiAdapter()
    {
        $this->app->singleton('mollie.api', function (Container $app) {
            $config = $app['config'];

            return new MollieApiWrapper($config, $app['mollie.api.client']);
        });

        $this->app->alias('mollie.api', MollieApiWrapper::class);
    }

    /**
     * Register the Mollie API Client.
     *
     * @return void
     */
    protected function registerApiClient()
    {
        $this->app->singleton('mollie.api.client', function () {
            return new MollieApiClient();
        });

        $this->app->alias('mollie.api.client', MollieApiClient::class);
    }

    /**
     * Register the manager class.
     *
     * @return void
     */
    public function registerManager()
    {
        $this->app->singleton('mollie', function (Container $app) {
            return new MollieManager($app);
        });

        $this->app->alias('mollie', MollieManager::class);
    }

    /**
     * Register each Endpoint class
     */
    protected function registerEndpoints()
    {
        $endpoints = [
            [ ChargebackEndpoint::class, 'chargebacks' ],
            [ CustomerEndpoint::class, 'customers' ],
            [ CustomerPaymentsEndpoint::class, 'customerPayments' ],
            [ InvoiceEndpoint::class, 'invoices' ],
            [ MandateEndpoint::class, 'mandates' ],
            [ MethodEndpoint::class, 'methods' ],
            [ OrderEndpoint::class, 'orders' ],
            [ OrderLineEndpoint::class, 'orderLines' ],
            [ OrderRefundEndpoint::class, 'orderRefunds' ],
            [ OrganizationEndpoint::class, 'organizations' ],
            [ PaymentEndpoint::class, 'payments' ],
            [ PaymentCaptureEndpoint::class, 'paymentCaptures' ],
            [ PaymentChargebackEndpoint::class, 'paymentChargebacks' ],
            [ PaymentRefundEndpoint::class, 'paymentRefunds' ],
            [ PermissionEndpoint::class, 'permissions' ],
            [ ProfileEndpoint::class, 'profiles' ],
            [ RefundEndpoint::class, 'refunds' ],
            [ ShipmentEndpoint::class, 'shipments' ],
            [ SettlementsEndpoint::class, 'settlements' ],
            [ SubscriptionEndpoint::class, 'subscriptions' ],
        ];

        foreach ($endpoints as $endpoint) {
            $this->registerEndpoint($endpoint[0], $endpoint[1]);
        }
    }

    protected function registerEndpoint($abstract, $method)
    {
        $this->app->singleton($abstract, function (Container $app) use ($method) {
            return $app[MollieApiWrapper::class]->$method();
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            'mollie',
            'mollie.api',
            'mollie.api.client',
        ];
    }
}
