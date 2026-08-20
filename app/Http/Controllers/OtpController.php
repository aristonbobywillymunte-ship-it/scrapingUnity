<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\OtpRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\AuthSession;
use Illuminate\Support\Facades\DB;
use App\Services\OtpDeliveryService;
use App\Services\AuditSecurityService;
use Illuminate\Database\QueryException;

class OtpController extends Controller {
    public function request(Request $request, OtpDeliveryService $delivery) {
        $request->validate(['email' => 'required', 'channel' => 'required|in:EMAIL,WHATSAPP']);
        if ($request->channel === 'TELEGRAM') return response()->json(['error' => 'Telegram not supported'], 422);
        
        $user = User::where('email', $request->email)->first();
        if (!$user) return response()->json(['message' => 'If account exists, OTP sent']);
        
        $date = now()->toDateString();
        
        try {
            DB::table('otp_rate_buckets')->upsert(
                ['user_id' => $user->id, 'channel' => $request->channel, 'bucket_date' => $date, 'request_count' => 1],
                ['user_id', 'channel', 'bucket_date'],
                ['request_count' => DB::raw('otp_rate_buckets.request_count + 1'), 'updated_at' => now()]
            );
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'otp_rate_buckets_request_count_check') || str_contains($e->getMessage(), 'Check violation')) {
                AuditSecurityService::log('OTP_RATE_LIMIT', $user->id, null, ['channel' => $request->channel]);
                return response()->json(['error' => 'Rate limit exceeded'], 429);
            }
            throw $e;
        }

        $otp = sprintf('%06d', random_int(0, 999999));
        OtpRequest::create([
            'id' => Str::uuid(), 'user_id' => $user->id, 'channel' => $request->channel,
            'otp_hash' => hash('sha256', $otp), 'purpose' => 'PASSWORD_RESET',
            'expires_at' => now()->addMinutes(5), 'attempt_count' => 0
        ]);
        
        $delivery->send($request->channel, $user->email, $otp);
        return response()->json(['message' => 'If account exists, OTP sent']);
    }

    public function complete(Request $request) {
        $request->validate(['email' => 'required', 'otp' => 'required', 'password' => 'required', 'channel' => 'required']);
        $user = User::where('email', $request->email)->first();
        if (!$user) return response()->json(['error' => 'Invalid'], 400);

        DB::beginTransaction();
        try {
            $otpReq = OtpRequest::where('user_id', $user->id)->where('channel', $request->channel)
                ->where('purpose', 'PASSWORD_RESET')->whereNull('used_at')->latest('created_at')->lockForUpdate()->first();
                
            if (!$otpReq || $otpReq->expires_at < now() || $otpReq->attempt_count >= 5) {
                DB::rollBack();
                return response()->json(['error' => 'Invalid'], 400);
            }
            
            if (hash('sha256', $request->otp) !== $otpReq->otp_hash) {
                $otpReq->increment('attempt_count');
                if ($otpReq->attempt_count >= 5) {
                    AuditSecurityService::log('OTP_REPEATED_FAILURE', $user->id, null, ['channel' => $request->channel]);
                }
                DB::commit();
                return response()->json(['error' => 'Invalid'], 400);
            }

            $otpReq->update(['used_at' => now()]);
            $user->update(['password_hash' => Hash::make($request->password)]);
            AuthSession::where('user_id', $user->id)->update(['revoked_at' => now()]);
            
            AuditSecurityService::log('PASSWORD_RESET', $user->id, null, ['channel' => $request->channel]);
            DB::commit();
            return response()->json(['message' => 'Password reset complete']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
