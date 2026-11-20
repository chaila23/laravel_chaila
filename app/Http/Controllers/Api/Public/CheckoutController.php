<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Routing\Controllers\HasMiddleware;
use Midtrans\Snap;
use Midtrans\Config;

class CheckoutController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            'auth:api'
        ];
    }

    public function __construct()
    {
        Config::$serverKey = config('midtrans.server_key');
        Config::$isProduction = config('midtrans.is_production');
        Config::$isSanitized = config('midtrans.is_sanitized');
        Config::$is3ds = config('midtrans.is_3ds');
    }

    /**
     * Checkout dan membuat pembayaran Midtrans.
     */
    public function store(Request $request)
    {
        $request->validate([
            'province_name' => 'required|string',
            'city_name' => 'required|string',
            'district_name' => 'required|string',
            'subdistrict_name' => 'required|string',
            'zip_code' => 'required',
            'full_address' => 'required|string',
            'weight' => 'required|integer|min:1',
            'shipping_courier' => 'required|string',
            'shipping_service' => 'required|string',
            'shipping_cost' => 'required|numeric|min:0',
        ]);

        $user = auth()->guard('api')->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Silakan login terlebih dahulu.'
            ], 401);
        }

        try {

            $result = DB::transaction(function () use ($request, $user) {

                // Ambil keranjang pelanggan
                $carts = Cart::with('product')
                    ->where('customer_id', $user->id)
                    ->lockForUpdate()
                    ->get();

                if ($carts->isEmpty()) {
                    throw new \Exception('Keranjang belanja masih kosong.');
                }

                // Hitung subtotal dari database
                $subtotal = 0;
                $itemDetails = [];

                foreach ($carts as $cart) {

                    if (!$cart->product) {
                        throw new \Exception(
                            'Produk pada keranjang tidak ditemukan.'
                        );
                    }

                    $price = (int) $cart->product->price;
                    $qty = (int) $cart->qty;

                    if ($qty < 1 || $price < 0) {
                        throw new \Exception(
                            'Data produk atau jumlah pembelian tidak valid.'
                        );
                    }

                    $subtotal += $price * $qty;

                    // Detail item Midtrans
                    $itemDetails[] = [
                        'id' => (string) $cart->product_id,
                        'price' => $price,
                        'quantity' => $qty,
                        'name' => substr(
                            $cart->product->title,
                            0,
                            50
                        ),
                    ];
                }

                // Biaya pengiriman
                $shippingCost = (int) $request->shipping_cost;

                // Total pembayaran
                $grandTotal = $subtotal + $shippingCost;

                // Buat nomor invoice unik
                $invoice = 'INV-' . strtoupper(
                    Str::random(12)
                );

                // Simpan transaksi
                $transaction = Transaction::create([
                    'customer_id' => $user->id,
                    'invoice' => $invoice,

                    'province_name' => $request->province_name,
                    'city_name' => $request->city_name,
                    'district_name' => $request->district_name,
                    'subdistrict_name' => $request->subdistrict_name,
                    'zip_code' => $request->zip_code,
                    'full_address' => $request->full_address,

                    'weight' => $request->weight,
                    'total' => $grandTotal,
                    'status' => 'PENDING',
                ]);

                // Simpan informasi pengiriman
                $transaction->shipping()->create([
                    'transaction_id' => $transaction->id,
                    'shipping_courier' => $request->shipping_courier,
                    'shipping_service' => $request->shipping_service,
                    'shipping_cost' => $shippingCost,
                ]);

                // Simpan detail transaksi
                foreach ($carts as $cart) {

                    $transaction->transactionDetails()->create([
                        'transaction_id' => $transaction->id,
                        'product_id' => $cart->product_id,
                        'qty' => $cart->qty,
                        'price' => $cart->product->price * $cart->qty,
                    ]);
                }

                // Tambahkan biaya pengiriman ke Midtrans
                $itemDetails[] = [
                    'id' => 'SHIPPING',
                    'price' => $shippingCost,
                    'quantity' => 1,
                    'name' => 'Shipping Cost',
                ];

                // Data pembayaran Midtrans
                $payload = [
                    'transaction_details' => [
                        'order_id' => $transaction->invoice,
                        'gross_amount' => $grandTotal,
                    ],

                    'customer_details' => [
                        'first_name' => $user->name,
                        'email' => $user->email,

                        'shipping_address' => [
                            'first_name' => $user->name,
                            'address' => $request->full_address,
                            'city' => $request->city_name,
                            'postal_code' => (string) $request->zip_code,
                            'country_code' => 'IDN',
                        ],
                    ],

                    'item_details' => $itemDetails,
                ];

                // Buat Snap Token
                $snapToken = Snap::getSnapToken($payload);

                if (empty($snapToken)) {
                    throw new \Exception(
                        'Midtrans gagal membuat Snap Token.'
                    );
                }

                // Simpan Snap Token
                $transaction->snap_token = $snapToken;
                $transaction->save();

                // Kosongkan keranjang setelah berhasil
                Cart::where('customer_id', $user->id)->delete();

                return [
                    'snap_token' => $snapToken,
                    'invoice' => $transaction->invoice,
                    'total' => $grandTotal,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Checkout Berhasil',
                'data' => $result,
            ], 200);

        } catch (\Throwable $e) {

            Log::error('Checkout Error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Checkout gagal.',
                'error' => config('app.debug')
                    ? $e->getMessage()
                    : null,
            ], 500);
        }
    }
}