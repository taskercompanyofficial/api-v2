<?php

namespace App\Http\Controllers\TaskerApp;

use App\Http\Controllers\Controller;
use App\Models\CustomerWallet;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WalletController extends Controller
{
    public function me(Request $request)
    {
        $user = $request->user();
        $wallet = CustomerWallet::firstOrCreate(['customer_id' => $user->id], ['balance' => 0]);
        return response()->json([
            'balance' => (float) $wallet->balance,
        ]);
    }

    public function transactions(Request $request)
    {
        $user = $request->user();
        $txns = WalletTransaction::where('customer_id', $user->id)
            ->orderByDesc('id')
            ->paginate(20);
        return response()->json($txns);
    }

    public function recharge(Request $request)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'method' => ['nullable', 'string', 'max:100'],
        ]);
        $user = $request->user();
        return DB::transaction(function () use ($user, $data) {
            $wallet = CustomerWallet::lockForUpdate()->firstOrCreate(['customer_id' => $user->id], ['balance' => 0]);
            $wallet->balance = bcadd($wallet->balance, $data['amount'], 2);
            $wallet->save();
            $txn = WalletTransaction::create([
                'customer_id' => $user->id,
                'type' => 'recharge',
                'title' => 'Wallet Recharge',
                'amount' => $data['amount'],
                'status' => 'success',
                'method' => $data['method'] ?? null,
                'meta' => null,
            ]);
            return response()->json(['balance' => (float) $wallet->balance, 'transaction' => $txn], 201);
        });
    }

    public function refund(Request $request)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'title' => ['nullable', 'string', 'max:150'],
        ]);
        $user = $request->user();
        return DB::transaction(function () use ($user, $data) {
            $wallet = CustomerWallet::lockForUpdate()->firstOrCreate(['customer_id' => $user->id], ['balance' => 0]);
            $wallet->balance = bcadd($wallet->balance, $data['amount'], 2);
            $wallet->save();
            $txn = WalletTransaction::create([
                'customer_id' => $user->id,
                'type' => 'refund',
                'title' => $data['title'] ?? 'Refund',
                'amount' => $data['amount'],
                'status' => 'success',
                'method' => null,
                'meta' => null,
            ]);
            return response()->json(['balance' => (float) $wallet->balance, 'transaction' => $txn], 201);
        });
    }

    public function debit(Request $request)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'title' => ['required', 'string', 'max:150'],
        ]);
        $user = $request->user();
        return DB::transaction(function () use ($user, $data) {
            $wallet = CustomerWallet::lockForUpdate()->firstOrCreate(['customer_id' => $user->id], ['balance' => 0]);
            if (bccomp($wallet->balance, $data['amount'], 2) < 0) {
                return response()->json(['message' => 'Insufficient balance'], 422);
            }
            $wallet->balance = bcsub($wallet->balance, $data['amount'], 2);
            $wallet->save();
            $txn = WalletTransaction::create([
                'customer_id' => $user->id,
                'type' => 'debit',
                'title' => $data['title'],
                'amount' => $data['amount'],
                'status' => 'success',
                'method' => null,
                'meta' => null,
            ]);
            return response()->json(['balance' => (float) $wallet->balance, 'transaction' => $txn], 201);
        });
    }
}
