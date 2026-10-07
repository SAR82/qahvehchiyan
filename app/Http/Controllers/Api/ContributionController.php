<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contribution;
use App\Services\ZarinpalService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use App\Services\FinancialLedgerService;

class ContributionController extends Controller
{
    public function __construct(protected ZarinpalService $zarinpal)
    {
    }

    public function initiate(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['donation', 'investment'])],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'amount' => ['required', 'numeric', 'min:1000'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        $contribution = Contribution::create([
            'type' => $data['type'],
            'name' => $data['name'],
            'phone' => $data['phone'],
            'amount' => $data['amount'],
            'message' => $data['message'] ?? null,
            'status' => 'pending',
        ]);

        $label = $data['type'] === 'donation' ? 'کمک به کسب‌وکار' : 'سرمایه‌گذاری در قرض‌الحسنه';

        $callbackUrl = url("/api/contributions/{$contribution->id}/verify");

        $result = $this->zarinpal->request(
            amountToman: (int) $data['amount'],
            description: "{$label} - {$data['name']}",
            callbackUrl: $callbackUrl,
            mobile: $data['phone'],
        );

        if (! $result['success']) {
            $contribution->update(['status' => 'failed']);

            return response()->json(['message' => $result['message']], 422);
        }

        $contribution->update(['gateway_ref' => $result['authority']]);

        return response()->json([
            'contribution_id' => $contribution->id,
            'pay_url' => $result['pay_url'],
        ]);
    }

    public function verify(Request $request, Contribution $contribution)
    {
        $frontendUrl = config('app.frontend_url');
        $suffix = '&type=contribution';
        $authority = (string) $request->query('Authority', '');
    
        $redirectTo = DB::transaction(function () use ($contribution, $request, $authority, $frontendUrl, $suffix) {
            $c = Contribution::lockForUpdate()->findOrFail($contribution->id);
    
            if (! $c->gateway_ref || ! hash_equals($c->gateway_ref, $authority)) {
                return $frontendUrl . '/payment-result.html?status=failed' . $suffix;
            }
    
            if ($c->status !== 'pending') {
                return $frontendUrl . '/payment-result.html?status=already_processed' . $suffix;
            }
    
            if ($request->query('Status') !== 'OK') {
                $c->update(['status' => 'failed']);
                return $frontendUrl . '/payment-result.html?status=cancelled' . $suffix;
            }
    
            $result = $this->zarinpal->verify(
                amountToman: (int) $c->amount,
                authority: $authority,
            );
    
            if (! $result['success']) {
                $c->update(['status' => 'failed']);
                return $frontendUrl . '/payment-result.html?status=failed' . $suffix;
            }
    
            $c->update(['status' => 'success', 'paid_at' => now()]);
    
            app(FinancialLedgerService::class)->recordContribution($c);
    
            return $frontendUrl . '/payment-result.html?status=success&ref_id=' . ($result['ref_id'] ?? '') . $suffix;
        });
    
        return redirect($redirectTo);
    }
}