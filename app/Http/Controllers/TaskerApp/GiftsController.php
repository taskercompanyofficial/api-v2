<?php

namespace App\Http\Controllers\TaskerApp;

use App\Http\Controllers\Controller;
use App\Models\GiftStreak;
use App\Models\GiftVoucher;
use App\Models\CustomerWallet;
use App\Models\WalletTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GiftsController extends Controller
{
    protected array $percents = [2, 5, 7, 10, 12, 15, 20];

    public function unclaimed(Request $request)
    {
        $user = $request->user();
        $streak = GiftStreak::firstOrCreate(['customer_id' => $user->id], ['streak' => 0]);
        $gifts = GiftVoucher::where('customer_id', $user->id)->where('status', 'unclaimed')->orderByDesc('id')->get();
        return response()->json([
            'streak' => $streak->streak,
            'unclaimed' => $gifts,
        ]);
    }

    public function checkIn(Request $request)
    {
        $user = $request->user();
        return DB::transaction(function () use ($user) {
            $streak = GiftStreak::lockForUpdate()->firstOrCreate(['customer_id' => $user->id], ['streak' => 0]);
            $today = Carbon::today();
            if ($streak->last_check_in_date && $streak->last_check_in_date->isSameDay($today)) {
                $gifts = GiftVoucher::where('customer_id', $user->id)->where('status', 'unclaimed')->orderByDesc('id')->get();
                return response()->json(['created' => false, 'streak' => $streak->streak, 'unclaimed' => $gifts]);
            }
            $yesterday = Carbon::yesterday();
            if ($streak->last_check_in_date && $streak->last_check_in_date->isSameDay($yesterday)) {
                $newStreak = ($streak->streak % 7) + 1;
            } else {
                $newStreak = 1;
            }
            $percent = $this->percents[($newStreak - 1) % 7];
            $gift = GiftVoucher::create([
                'customer_id' => $user->id,
                'type' => 'daily_checkin',
                'title' => $percent . '% Off Voucher',
                'discount_percent' => $percent,
                'amount' => null,
                'status' => 'unclaimed',
            ]);
            $streak->last_check_in_date = $today;
            $streak->streak = $newStreak;
            $streak->save();
            $gifts = GiftVoucher::where('customer_id', $user->id)->where('status', 'unclaimed')->orderByDesc('id')->get();
            return response()->json(['created' => true, 'streak' => $newStreak, 'gift' => $gift, 'unclaimed' => $gifts], 201);
        });
    }

    public function claim(Request $request, int $id)
    {
        $user = $request->user();
        return DB::transaction(function () use ($user, $id) {
            $gift = GiftVoucher::lockForUpdate()->where('customer_id', $user->id)->where('id', $id)->first();
            if (!$gift || $gift->status !== 'unclaimed') {
                return response()->json(['message' => 'Gift not available'], 404);
            }
            $gift->status = 'claimed';
            $gift->claimed_at = now();
            $gift->save();
            if ($gift->amount && $gift->amount > 0) {
                $wallet = CustomerWallet::lockForUpdate()->firstOrCreate(['customer_id' => $user->id], ['balance' => 0]);
                $wallet->balance = bcadd($wallet->balance, $gift->amount, 2);
                $wallet->save();
                WalletTransaction::create([
                    'customer_id' => $user->id,
                    'type' => 'gift',
                    'title' => $gift->title,
                    'amount' => $gift->amount,
                    'status' => 'success',
                    'method' => null,
                    'meta' => ['gift_id' => $gift->id],
                ]);
            }
            return response()->json(['gift' => $gift]);
        });
    }

    public function vouchers(Request $request)
    {
        $user = $request->user();
        $vouchers = GiftVoucher::where('customer_id', $user->id)->orderByDesc('id')->get();
        return response()->json($vouchers);
    }
}
