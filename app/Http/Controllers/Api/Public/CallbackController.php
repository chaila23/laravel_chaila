<?php

namespace App\Http\Controllers\Api\Public;

use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class CallbackController extends Controller
{
    public function __invoke(Request $request)
    {
        $payload = $request->getContent();
        $notification = json_decode($payload);

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid notification'
            ], 400);
        }

        $validSignatureKey = hash(
            'sha512',
            $notification->order_id .
            $notification->status_code .
            $notification->gross_amount .
            config('midtrans.server_key')
        );

        if ($notification->signature_key !== $validSignatureKey) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid signature'
            ], 403);
        }

        $transaction = $notification->transaction_status;
        $type = $notification->payment_type;
        $orderId = $notification->order_id;

        $data_transaction = Transaction::where(
            'invoice',
            $orderId
        )->first();

        if (!$data_transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found'
            ], 404);
        }

        if ($transaction == 'capture') {

            if ($type == 'credit_card') {

                if (
                    isset($notification->fraud_status) &&
                    $notification->fraud_status == 'challenge'
                ) {
                    $data_transaction->update([
                        'status' => 'CHALLENGE'
                    ]);
                } else {
                    $data_transaction->update([
                        'status' => 'SUCCESS'
                    ]);
                }

            } else {
                $data_transaction->update([
                    'status' => 'SUCCESS'
                ]);
            }

        } elseif ($transaction == 'settlement') {

            $data_transaction->update([
                'status' => 'SUCCESS'
            ]);

        } elseif ($transaction == 'pending') {

            $data_transaction->update([
                'status' => 'PENDING'
            ]);

        } elseif ($transaction == 'deny') {

            $data_transaction->update([
                'status' => 'FAILED'
            ]);

        } elseif ($transaction == 'expire') {

            $data_transaction->update([
                'status' => 'EXPIRED'
            ]);

        } elseif ($transaction == 'cancel') {

            $data_transaction->update([
                'status' => 'FAILED'
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification Handled Successfully'
        ], 200);
    }
}