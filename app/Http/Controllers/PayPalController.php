<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Setting;

use PaypalServerSdkLib\Authentication\ClientCredentialsAuthCredentialsBuilder;

use PaypalServerSdkLib\Environment;

use PaypalServerSdkLib\PaypalServerSdkClientBuilder;

use PaypalServerSdkLib\Models\Builders\MoneyBuilder;

use PaypalServerSdkLib\Models\Builders\OrderRequestBuilder;

use PaypalServerSdkLib\Models\Builders\PurchaseUnitRequestBuilder;

use PaypalServerSdkLib\Models\Builders\AmountWithBreakdownBuilder;

use PaypalServerSdkLib\Models\Builders\ShippingDetailsBuilder;

use PaypalServerSdkLib\Models\Builders\ShippingOptionBuilder;

use PaypalServerSdkLib\Models\ShippingType;

use PaypalServerSdkLib\Models\CheckoutPaymentIntent;


use Exception;

class PayPalController extends Controller
{
    private $client;
    private $ordersController;
    private $paymentsController;

    public function __construct()
    {
        try {
            $PAYPAL_CLIENT_ID = Setting::where('key', 'paypal_api_client_id')->value('value');
            $PAYPAL_CLIENT_SECRET = Setting::where('key', 'paypal_api')->value('value');
            $this->client = PaypalServerSdkClientBuilder::init()
                ->clientCredentialsAuthCredentials(
                    ClientCredentialsAuthCredentialsBuilder::init(
                        $PAYPAL_CLIENT_ID,
                        $PAYPAL_CLIENT_SECRET
                    )
                )
                ->environment(Environment::PRODUCTION)
                ->build();

            $this->ordersController = $this->client->getOrdersController();
            $this->paymentsController = $this->client->getPaymentsController();

        } catch (Exception $e) {
            \Log::error('Error in PayPal SDK:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'An error occurred.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Helper function to handle API responses.
     */
    private function handleResponse($response)
    {
        $jsonResponse = json_decode($response->getBody(), true);
        return [
            "jsonResponse" => $jsonResponse,
            "httpStatusCode" => $response->getStatusCode(),
        ];
    }

    /**
     * Create a new PayPal order.
     */
    public function createOrder(Request $request)
    {
        \Log::info('createOrder()');
        try {
            
            $orderBody = [
                'body' => OrderRequestBuilder::init(
                    CheckoutPaymentIntent::AUTHORIZE,
                    [
                        PurchaseUnitRequestBuilder::init(
                            AmountWithBreakdownBuilder::init(
                                'EUR',
                                $request->totalPrice
                            )->build()
                        )->build()
                    ]
                )->build()
            ];
        
            \Log::info('Order Body:', $orderBody);
            $response = $this->ordersController->ordersCreate($orderBody);
            $result = $this->handleResponse($response);
            \Log::info('after ordersCreate result', $result);

            return response()->json($result["jsonResponse"], $result["httpStatusCode"]);
        } catch (Exception $e) {
            \Log::error('Error in PayPal SDK:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => 'An error occurred.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Authorize an existing order.
     */
    public function authorizeOrder($orderID)
    {
        \Log::info('authorizeOrder():');
        \Log::info($orderID);
        try {
            // Erstelle die Anfrage für die Autorisierung
            $authorizationBody = [
                "id" => $orderID, // Die ID der Bestellung, die autorisiert werden soll
            ];
    
            // Anfrage an PayPal senden, um die Bestellung zu autorisieren
            $response = $this->ordersController->ordersAuthorize($authorizationBody);
            $result = $this->handleResponse($response);

            return response()->json($result["jsonResponse"], $result["httpStatusCode"]);
        } catch (Exception $e) {
            // Fehlerbehandlung
            return response()->json([
                'error' => 'Failed to authorize order.',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Capture an authorized payment.
     */
    public function captureAuthorize($authorizationId)
    {
        \Log::info('captureAuthorize():');
        \Log::info($authorizationId);
        $captureAuthorizeBody = [
            "authorizationId" => $authorizationId,
        ];
        $apiResponse = $this->paymentsController->authorizationsCapture($captureAuthorizeBody);
        $result = $this->handleResponse($apiResponse);
        return response()->json($result["jsonResponse"], $result["httpStatusCode"]);
    }
}
