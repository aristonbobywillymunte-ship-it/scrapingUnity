<?php
namespace App\Services;

class FakeOtpDeliveryService implements OtpDeliveryService {
    public array $sent = [];
    public function send(string $channel, string $address, string $otp): void {
        $this->sent[] = compact('channel', 'address', 'otp');
    }
}
