<?php
namespace App\Services;

interface OtpDeliveryService {
    public function send(string $channel, string $address, string $otp): void;
}
