<?php

namespace Mollie\Laravel\Tests;

use GrahamCampbell\TestBenchCore\ServiceProviderTrait;
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
use Mollie\Laravel\MollieManager;
use Mollie\Laravel\Wrappers\MollieApiWrapper;

/**
 * This is the service provider test class.
 */
class MollieServiceProviderTest extends TestCase
{
    use ServiceProviderTrait;

    public function testMollieManagerIsInjectable()
    {
        $this->assertIsInjectable(MollieManager::class);
    }

    public function testMollieApiWrapperIsInjectable()
    {
        $this->assertIsInjectable(MollieApiWrapper::class);
    }

    public function testMollieApiClientIsInjectable()
    {
        $this->assertIsInjectable(MollieApiClient::class);
    }

    public function testMollieApiEndpointsAreBound()
    {
        collect([
            ChargebackEndpoint::class,
            CustomerEndpoint::class,
            CustomerPaymentsEndpoint::class,
            InvoiceEndpoint::class,
            MandateEndpoint::class,
            MethodEndpoint::class,
            OrderEndpoint::class,
            OrderLineEndpoint::class,
            OrderRefundEndpoint::class,
            OrganizationEndpoint::class,
            PaymentCaptureEndpoint::class,
            PaymentChargebackEndpoint::class,
            PaymentEndpoint::class,
            PaymentRefundEndpoint::class,
            PermissionEndpoint::class,
            ProfileEndpoint::class,
            RefundEndpoint::class,
            SettlementsEndpoint::class,
            ShipmentEndpoint::class,
            SubscriptionEndpoint::class,
        ])->each(function ($endpoint) {
            $this->assertIsBound($endpoint);
        });
    }

    /**
     * Assert that the abstract is listed in the Laravel Container.
     *
     * @param string $abstract
     */
    protected function assertIsBound(string $abstract)
    {
        $this->assertTrue(
            app()->bound($abstract),
            $abstract . ' is not bound into the Laravel Container.'
        );
        $this->assertIsInjectable($abstract);
    }
}
