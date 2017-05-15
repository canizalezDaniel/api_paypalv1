<?php

namespace App\Http\Controllers;

use Anouar\Paypalpayment\Facades\PaypalPayment;
use Illuminate\Http\Request;
use App\Http\Requests;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\URL;
use PayPal\Api\RedirectUrls;

class PaymentController extends Controller
{

    private $_apiContext;

    public function __construct()
    {

        // ### Api Context
        // Pass in a `ApiContext` object to authenticate
        // the call. You can also send a unique request id
        // (that ensures idempotency). The SDK generates
        // a request id if you do not pass one explicitly.

        $this->_apiContext = PaypalPayment::ApiContext(config('paypal_payment.Account.ClientId'), config('paypal_payment.Account.ClientSecret'));

    }


    public function postPayment(Request $request)
    {


        //dd($request->all());
        $payer = Paypalpayment::payer();
        $payer->setPaymentMethod("paypal");

        $items = array();

        //$productos=$request->json('items');

        /*foreach($productos as $item_producto){


            $item = Paypalpayment::item();
            $item->setName($item_producto['name'])
                ->setDescription($item_producto['description'])
                ->setCurrency('USD')
                ->setQuantity($item_producto['quantity'])
                ->setTax(0.3)
                ->setPrice($item_producto['price']);
            dd($item);
            $items[] = $item;

        }*/

        $inputs = Input::all();
        foreach ($inputs['name'] as $index => $value) {
            $item1 = Paypalpayment::item();
            $item1->setName($inputs['name'][$index])
                ->setDescription($inputs['description'][$index])
                ->setCurrency('USD')
                ->setQuantity($inputs['quantity'][$index])
                ->setTax(0.3)
                ->setPrice($inputs['price'][$index]);
            $items[] = $item1;
        }

       /* $item1 = Paypalpayment::item();
        $item1->setName($request->get('name'))
            ->setDescription($request->get('description'))
            ->setCurrency('USD')
            ->setQuantity($request->get('quantity'))
            ->setTax(0.3)
            ->setPrice($request->get('price'));*/




        /*$item1 = Paypalpayment::item();
        $item1->setName("iphone 7")
            ->setDescription("Iphone 9")
            ->setCurrency('USD')
            ->setQuantity(1)
            ->setTax(0.3)
            ->setPrice(7.50);



                $item2 = Paypalpayment::item();
                $item2->setName('Granola bars')
                    ->setDescription('Granola Bars with Peanuts')
                    ->setCurrency('USD')
                    ->setQuantity(5)
                    ->setTax(0.2)
                    ->setPrice(2);
*/

        $itemList = Paypalpayment::itemList();
        //$itemList->setItems(array($item1));
        $itemList->setItems($items);
        $details = Paypalpayment::details();
        $details
            //total of items prices
            ->setSubtotal($request->get('subtotal'));

        //Payment Amount
        $amount = Paypalpayment::amount();
        $amount->setCurrency('USD')
            // the total is $17.8 = (16 + 0.6) * 1 ( of quantity) + 1.2 ( of Shipping).
            ->setTotal($request->get('total'))
            ->setDetails($details);

        // ### Transaction
        // A transaction defines the contract of a
        // payment - what is the payment for and who
        // is fulfilling it. Transaction is created with
        // a `Payee` and `Amount` types

        $transaction = Paypalpayment::transaction();
        $transaction->setAmount($amount)
            ->setItemList($itemList)
            ->setDescription("Pedido de prueba en Laravel API")
            ->setInvoiceNumber(uniqid());

        // ### Redirect urls
        // Set the urls that the buyer must be redirected to after
        // payment approval/ cancellation.
        $redirect_urls = new RedirectUrls();
        $redirect_urls->setReturnUrl(URL::route('payment.status'))
            ->setCancelUrl(URL::route('payment.status'));



        // ### Payment
        // A Payment Resource; create one using
        // the above types and intent as 'sale'

        $payment = Paypalpayment::payment();

        $payment->setIntent("sale")
            ->setPayer($payer)
            ->setRedirectUrls($redirect_urls)
            ->setTransactions(array($transaction));

        try {
            // ### Create Payment
            // Create a payment by posting to the APIService
            // using a valid ApiContext
            // The return object contains the status;
            $payment->create($this->_apiContext);
        } catch (\PPConnectionException $ex) {
            return  "Exception: " . $ex->getMessage() . PHP_EOL;
            exit(1);
        }

        foreach($payment->getLinks() as $link) {
            if($link->getRel() == 'approval_url') {
                $redirect_url = $link->getHref();
                break;
            }
        }
        // añadir Paypal ID a variable session para procesarla en el estatus.
        Session::put('paypal_payment_id', $payment->getId());

        if(isset($redirect_url)) {
            // Redirección a paypal

            return Redirect::away($redirect_url);
        }

        //return $value = Session::get('paypal_payment_id');
    }

    public function getPaymentStatus()
    {
        // Get the payment ID before session clear
        $payment_id = Session::get('paypal_payment_id');

        // clear the session payment ID
        Session::forget('paypal_payment_id');

        $payerId = \Input::get('PayerID');
        $token = \Input::get('token');

        if (empty($payerId) || empty($token)) {
            return \Redirect::route('home')
                ->with('message', 'Hubo un problema al intentar pagar con Paypal');
        }

        $payment = Payment::get($payment_id, $this->_api_context);

        $execution = new PaymentExecution();
        $execution->setPayerId(\Input::get('PayerID'));

        $result = $payment->execute($execution, $this->_api_context);


        if ($result->getState() == 'approved') {

            $this->saveOrder();

            Session::forget('cart');

            return Redirect::route('home')
                ->with('message', 'Compra realizada de forma correcta');
        }
        return Redirect::route('home')
            ->with('message', 'La compra fue cancelada');
    }
}
