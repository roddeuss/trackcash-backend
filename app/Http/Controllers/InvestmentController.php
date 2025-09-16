<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Investment;
use App\Models\InvestmentTransaction;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class InvestmentController extends Controller
{
    public function index()
    {
        try {
            $investments = Investment::with('asset')
                ->where('user_id', Auth::id())
                ->where('deleted', false)
                ->get();

            return response()->json([
                'status' => true,
                'data' => $investments,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching investments', ['err' => $e]);
            return response()->json($this->errorPayload('Failed to fetch investments', $e), 500);
        }
    }

    /**
     * BUY
     */
    public function store(Request $request)
    {
        $request->validate([
            'asset_id'         => 'required|exists:assets,id',
            'units'            => 'required|numeric|min:0',
            'price_per_unit'   => 'required|numeric|min:0',
            'transaction_date' => 'sometimes|date', // opsional, kalau kosong -> now()
            'bank_id'          => 'required|exists:banks,id',
            'category_id'      => 'required|exists:categories,id',
        ]);

        DB::beginTransaction();
        try {
            // Normalisasi tanggal → string agar tidak ada masalah serialisasi
            $txDate = $request->filled('transaction_date')
                ? Carbon::parse($request->transaction_date)
                : now();
            $txDateString = $txDate->toDateTimeString();

            // Ambil asset sekali untuk description
            $asset = Asset::findOrFail($request->asset_id);

            // Ringkasan investasi (firstOrNew)
            $investment = Investment::firstOrNew([
                'user_id'  => Auth::id(),
                'asset_id' => $request->asset_id,
                'deleted'  => false,
            ]);

            $currentUnits = (float) ($investment->units ?? 0.0);
            $currentAvg   = (float) ($investment->average_buy_price ?? 0.0);
            $buyUnits     = (float) $request->units;
            $buyPrice     = (float) $request->price_per_unit;

            $totalCost    = ($currentUnits * $currentAvg) + ($buyUnits * $buyPrice);
            $newUnits     = $currentUnits + $buyUnits;
            $newAvg       = $newUnits > 0 ? $totalCost / $newUnits : 0.0;

            $investment->units = $newUnits;
            $investment->average_buy_price = $newAvg;
            if (!$investment->exists) {
                $investment->created_by = Auth::id();
            }
            $investment->updated_by = Auth::id();
            $investment->save();

            // Transaksi cashflow (negatif = uang keluar)
            $transaction = Transaction::create([
                'user_id'          => Auth::id(),
                'bank_id'          => $request->bank_id,
                'asset_id'         => $request->asset_id,
                'category_id'      => $request->category_id,
                'amount'           => ($buyUnits * $buyPrice),
                'transaction_date' => $txDateString, // kirim string
                'description'      => 'Buy Investment: ' . $asset->asset_code,
                'created_by'       => Auth::id(),
            ]);

            InvestmentTransaction::create([
                'investment_id'    => $investment->id,
                'transaction_id'   => $transaction->id,
                'type'             => 'buy',
                'units'            => $buyUnits,
                'price_per_unit'   => $buyPrice,
                'transaction_date' => $txDateString, // kirim string
                'created_by'       => Auth::id(),
            ]);

            DB::commit();

            return response()->json([
                'status' => true,
                'data'   => $investment->load('asset'),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating investment', ['err' => $e]);
            return response()->json($this->errorPayload('Failed to create investment', $e), 500);
        }
    }

    /**
     * SELL
     */
    public function sell(Request $request, $id)
    {
        $request->validate([
            'units'            => 'required|numeric|min:0',
            'price_per_unit'   => 'required|numeric|min:0',
            'transaction_date' => 'sometimes|date',
            'bank_id'          => 'required|exists:banks,id',
            'category_id'      => 'required|exists:categories,id',
        ]);

        DB::beginTransaction();
        try {
            $txDate = $request->filled('transaction_date')
                ? Carbon::parse($request->transaction_date)
                : now();
            $txDateString = $txDate->toDateTimeString();

            $investment = Investment::where('user_id', Auth::id())
                ->where('deleted', false)
                ->findOrFail($id);

            $sellUnits = (float) $request->units;
            $sellPrice = (float) $request->price_per_unit;

            if ($sellUnits > (float) $investment->units) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Not enough units to sell',
                ], 400);
            }

            $investment->units = (float) $investment->units - $sellUnits;
            $investment->updated_by = Auth::id();
            $investment->save();

            $transaction = Transaction::create([
                'user_id'          => Auth::id(),
                'bank_id'          => $request->bank_id,
                'asset_id'         => $investment->asset_id,
                'category_id'      => $request->category_id,
                'amount'           => $sellUnits * $sellPrice,
                'transaction_date' => $txDateString,
                'description'      => 'Sell Investment: ' . optional($investment->asset)->asset_code,
                'created_by'       => Auth::id(),
            ]);

            InvestmentTransaction::create([
                'investment_id'    => $investment->id,
                'transaction_id'   => $transaction->id,
                'type'             => 'sell',
                'units'            => $sellUnits,
                'price_per_unit'   => $sellPrice,
                'transaction_date' => $txDateString,
                'created_by'       => Auth::id(),
            ]);

            $profitLoss = ($sellPrice - (float) $investment->average_buy_price) * $sellUnits;

            DB::commit();

            return response()->json([
                'status'      => true,
                'data'        => $investment->load('asset'),
                'profit_loss' => $profitLoss,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error selling investment', ['err' => $e]);
            return response()->json($this->errorPayload('Failed to sell investment', $e), 500);
        }
    }

    public function destroy($id)
    {
        try {
            $investment = Investment::where('user_id', Auth::id())->findOrFail($id);
            $investment->update([
                'deleted'    => true,
                'updated_by' => Auth::id(),
            ]);

            return response()->json([
                'status'  => true,
                'message' => 'Investment deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error deleting investment', ['err' => $e]);
            return response()->json($this->errorPayload('Failed to delete investment', $e), 500);
        }
    }

    /**
     * Helper: payload error yang ramah debug saat non-production
     */
    private function errorPayload(string $fallback, \Throwable $e): array
    {
        $payload = ['status' => false, 'message' => $fallback];

        // Kalau bukan production, sertakan detail biar kelihatan di Network tab
        if (!app()->environment('production')) {
            $payload['error'] = [
                'type'    => get_class($e),
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ];
        }
        return $payload;
    }
}
