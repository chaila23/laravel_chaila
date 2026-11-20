<?php
namespace App\Http\Controllers\Api\Public;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RajaOngkirController extends Controller
{
    private function apiRequest()
    {
        $apiKey = config('rajaongkir.api_key');

        if (empty($apiKey)) {
            throw new \RuntimeException('RajaOngkir API Key belum dikonfigurasi.');
        }

        return Http::withOptions([
            'verify' => 'D:/laragon/etc/ssl/cacert.pem',
        ])
        ->withHeaders([
            'key' => $apiKey,
        ])
        ->timeout(30);
    }

    /**
     * Mencari tujuan pengiriman.
     */
    public function searchDestination(Request $request)
    {
        $request->validate([
            'search' => 'required|string|min:3',
        ]);

        try {
            $response = $this->apiRequest()
                ->get(
                    'https://rajaongkir.komerce.id/api/v1/destination/domestic-destination',
                    [
                        'search' => $request->search,
                        'limit' => 100,
                        'offset' => 0,
                    ]
                );

            if ($response->failed()) {
                return response()->json([
                    'message' => $response->json('meta.message')
                        ?? 'Gagal mengambil data tujuan.',
                ], $response->status());
            }

            return response()->json([
                'response' => [
                    'data' => $response->json('data') ?? [],
                ],
            ]);

        } catch (\Throwable $e) {
            Log::error('RajaOngkir Search Error: '.$e->getMessage());

            return response()->json([
                'message' => 'Terjadi kesalahan saat mencari tujuan.',
                'error' => config('app.debug')
                    ? $e->getMessage()
                    : null,
            ], 500);
        }
    }

    /**
     * Menghitung ongkos kirim.
     */
    public function checkOngkir(Request $request)
    {
        $validated = $request->validate([
            'destination' => 'required',
            'weight' => 'required|integer|min:1',
            'courier' => 'required|string',
        ]);

        try {
            $response = $this->apiRequest()
                ->asForm()
                ->post(
                    'https://rajaongkir.komerce.id/api/v1/calculate/domestic-cost',
                    [
                        'origin' => 46174,
                        'destination' => $validated['destination'],
                        'weight' => $validated['weight'],
                        'courier' => $validated['courier'],
                    ]
                );

            if ($response->failed()) {
                return response()->json([
                    'message' => $response->json('meta.message')
                        ?? 'Gagal menghitung ongkos kirim.',
                ], $response->status());
            }

            return response()->json(
                $response->json('data') ?? []
            );

        } catch (\Throwable $e) {
            Log::error('RajaOngkir Cost Error: '.$e->getMessage());

            return response()->json([
                'message' => 'Terjadi kesalahan saat menghitung ongkir.',
                'error' => config('app.debug')
                    ? $e->getMessage()
                    : null,
            ], 500);
        }
    }
}