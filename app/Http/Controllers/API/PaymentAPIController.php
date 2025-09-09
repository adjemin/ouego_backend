<?php

namespace App\Http\Controllers\API;

use App\Http\Requests\API\CreatePaymentAPIRequest;
use App\Http\Requests\API\UpdatePaymentAPIRequest;
use App\Models\Payment;
use App\Models\Order;
use App\Models\Invoice;
use App\Models\Driver;
use App\Models\Customer;
use App\Models\Transaction;
use App\Repositories\PaymentRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\AppBaseController;

/**
 * Class PaymentAPIController
 */
class PaymentAPIController extends AppBaseController
{
    private PaymentRepository $paymentRepository;

    public function __construct(PaymentRepository $paymentRepo)
    {
        $this->paymentRepository = $paymentRepo;
    }

    /**
     * Display a listing of the Payments.
     * GET|HEAD /payments
     */
    public function index(Request $request): JsonResponse
    {
        $payments = $this->paymentRepository->all(
            $request->except(['skip', 'limit']),
            $request->get('skip'),
            $request->get('limit')
        );

        return $this->sendResponse($payments->toArray(), 'Payments retrieved successfully');
    }

    /**
     * Store a newly created Payment in storage.
     * POST /payments
     */
    public function store(CreatePaymentAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        $payment = $this->paymentRepository->create($input);

        return $this->sendResponse($payment->toArray(), 'Payment saved successfully');
    }

    /**
     * Display the specified Payment.
     * GET|HEAD /payments/{id}
     */
    public function show($id): JsonResponse
    {
        /** @var Payment $payment */
        $payment = $this->paymentRepository->find($id);

        if (empty($payment)) {
            return $this->sendError('Payment not found');
        }

        return $this->sendResponse($payment->toArray(), 'Payment retrieved successfully');
    }

    /**
     * Update the specified Payment in storage.
     * PUT/PATCH /payments/{id}
     */
    public function update($id, UpdatePaymentAPIRequest $request): JsonResponse
    {
        $input = $request->all();

        /** @var Payment $payment */
        $payment = $this->paymentRepository->find($id);

        if (empty($payment)) {
            return $this->sendError('Payment not found');
        }

        $payment = $this->paymentRepository->update($input, $id);

        return $this->sendResponse($payment->toArray(), 'Payment updated successfully');
    }

    /**
     * Remove the specified Payment from storage.
     * DELETE /payments/{id}
     *
     * @throws \Exception
     */
    public function destroy($id): JsonResponse
    {
        /** @var Payment $payment */
        $payment = $this->paymentRepository->find($id);

        if (empty($payment)) {
            return $this->sendError('Payment not found');
        }

        $payment->delete();

        return $this->sendSuccess('Payment deleted successfully');
    }

    public function webhook(Request $request){

        $input = $request->all();

        $transaction_reference = $input['transaction_id'];
        $status = $input['status'];

        if (empty($transaction_reference)) {
            return response()->json([
                'error' => [
                    'message' => "Transaction not found",
                    'transaction_reference' => $transaction_reference
                ],
            ]);
        }

        // Recuperation de la ligne de la transaction dans votre base de données
        /** @var Payment $transaction */
        $transaction = Payment::where(['payment_reference' => $transaction_reference])->first();

        if (empty($transaction)) {
            return response()->json([
                'error' => [
                    'message' => "Transaction not found",
                    'transaction_reference' => $transaction_reference
                ],
            ]);
        }

        $invoice = Invoice::where([
            'id' =>  $transaction->invoice_id
        ])->first();

        if ($transaction->is_waiting && $invoice != null && $invoice->status == Invoice::UNPAID) {
            $transaction->payment_gateway_trans_id = array_key_exists('payment_gateway_trans_id', $input)?$input['payment_gateway_trans_id']:null;
            $transaction->payment_gateway_payment_method = array_key_exists('payment_gateway_payment_method', $input)?$input['payment_gateway_payment_method']:null;

            $transaction->payment_gateway_buyer_reference = array_key_exists('payment_gateway_buyer_reference', $input)?$input['payment_gateway_buyer_reference']:null;
            $transaction->save();

            switch ($status) {
                case Payment::STATUS_SUCCESSFUL :
                    $transaction->status = Payment::STATUS_SUCCESSFUL;
                    $transaction->is_waiting = false;
                    $transaction->is_completed = true;
                    $transaction->save();

                    $this->validateTransaction($transaction_reference);
                    break;
                case Payment::STATUS_FAILED :
                    $transaction->status = Payment::STATUS_FAILED;
                    $transaction->is_waiting = false;
                    $transaction->is_completed = true;
                    $transaction->save();

                    $this->cancelTransaction($transaction_reference);
                    break;
                case Payment::STATUS_CANCELLED :
                    $transaction->status = Payment::STATUS_CANCELLED;
                    $transaction->is_waiting = false;
                    $transaction->is_completed = true;
                    $transaction->save();

                    $this->cancelTransaction($transaction_reference);

                    break;
                case Payment::STATUS_EXPIRED :
                    $transaction->status = Payment::STATUS_FAILED;
                    $transaction->is_waiting = false;
                    $transaction->is_completed = true;
                    $transaction->save();

                    $this->cancelTransaction($transaction_reference);
                    break;
                default:
                    return response()->json([
                        'error' => [
                            'message' => 'MISSING_TRANSACTION_STATUS'
                        ],
                    ]);
                    break;


            }

                     // operation
            return response()->json([
                'status' => "OK",
                'message' => "received",
            ], 200);

        }

         // operation
         return response()->json([
            'status' => "KO",
            'message' => "nothing",
        ], 200);

    }

    public function  validateTransaction($ref){

        /** @var Payment $payment */
        $payment = Payment::where(['payment_reference' => $ref])->first();

        /** @var Invoice $invoice */
        $invoice = Invoice::where(['id' => $payment?->invoice_id ])->first();

        $invoice->status = Invoice::PAID;
        $invoice->save();

        if($invoice->order_source == "transactions"){
            /** @var Transaction $transaction */
            $transaction = Transaction::where([
                'id' => $invoice->order_id
                ])->first();

            if(!empty($transaction)){

                $transaction->status = Transaction::SUCCESSFUL;
                $transaction->save();

                if($transaction->user_source == "drivers"){
                    $user = Driver::where([
                        'id' => $invoice->customer_id
                    ])->first();

                    switch ($transaction->type) {
                        case Transaction::TYPE_DEPOSIT :
                            $user->creditBalance($transaction->amount);
                            break;
                        case Transaction::TYPE_WITHDRAWAL :
                            $user->debitBalance($transaction->amount);
                            break;
                    }
                }else{
                    $user = Customer::where([
                        'id' => $invoice->customer_id
                    ])->first();

                    switch ($transaction->type) {
                        case Transaction::TYPE_DEPOSIT :
                            $user->creditBalance($transaction->amount);
                            break;
                        case     Transaction::TYPE_WITHDRAWAL :
                            $user->debitBalance($transaction->amount);
                            break;
                    }
                }
            }
        }

    }

    public function cancelTransaction($ref){
        /** @var Payment $payment */
        $payment = Payment::where(['payment_reference' => $ref])->first();

        /** @var Invoice $invoice */
        $invoice = Invoice::where(['id' => $payment?->invoice_id ])->first();

        $payment?->forceDelete();

    }
}
