<?php
namespace App\Services;

use App\Models\User;
use App\Models\OtpRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\AuthSession;
use Illuminate\Support\Facades\DB;
use Exception;

class OtpService {
    public function __construct(private OtpDeliveryService $delivery) {}

    public function requestOtp(string $email, string $channel) {
        if ($channel === 'TELEGRAM') throw new Exception('Telegram not supported');
        
        $user = User::where('email', $email)->first();
        if (!$user) return; // Silent return for security
        
        $date = now()->toDateString();
        
        $bucket = DB::table('otp_rate_buckets')->where('user_id', $user->id)->where('channel', $channel)->where('bucket_date', $date)->first();
        if ($bucket && $bucket->request_count >= 3) {
            AuditSecurityService::log('OTP_RATE_LIMIT', $user->id, null, ['channel' => $channel]);
            throw new Exception('Rate limit exceeded');
        }
        
        DB::table('otp_rate_buckets')->upsert(
            ['user_id' => $user->id, 'channel' => $channel, 'bucket_date' => $date, 'request_count' => 1],
            ['user_id', 'channel', 'bucket_date'],
            ['request_count' => DB::raw('otp_rate_buckets.request_count + 1'), 'updated_at' => now()]
        );

        $otp = sprintf('%06d', random_int(0, 999999));
        OtpRequest::create([
            'id' => Str::uuid(), 'user_id' => $user->id, 'channel' => $channel,
            'otp_hash' => hash('sha256', $otp), 'purpose' => 'PASSWORD_RESET',
            'expires_at' => now()->addMinutes(5), 'attempt_count' => 0
        ]);
        
        $this->delivery->send($channel, $user->email, $otp);
    }

    public function completeReset(string $email, string $otp, string $password, string $channel) {
        $user = User::where('email', $email)->first();
        if (!$user) throw new Exception('Invalid');

        DB::beginTransaction();
        try {
            $otpReq = OtpRequest::where('user_id', $user->id)->where('channel', $channel)
                ->where('purpose', 'PASSWORD_RESET')->whereNull('used_at')->latest('created_at')->lockForUpdate()->first();
                
            if (!$otpReq || $otpReq->expires_at < now() || $otpReq->attempt_count >= 5) {
                DB::rollBack();
                throw new Exception('Invalid');
            }
            
            if (hash('sha256', $otp) !== $otpReq->otp_hash) {
                $otpReq->increment('attempt_count');
                if ($otpReq->attempt_count >= 5) {
                    AuditSecurityService::log('OTP_REPEATED_FAILURE', $user->id, null, ['channel' => $channel]);
                }
                DB::commit();
                throw new Exception('Invalid');
            }

            $otpReq->update(['used_at' => now()]);
            $user->update(['password_hash' => Hash::make($password)]);
            AuthSession::where('user_id', $user->id)->update(['revoked_at' => now()]);
            
            AuditSecurityService::log('PASSWORD_RESET', $user->id, null, ['channel' => $channel]);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
