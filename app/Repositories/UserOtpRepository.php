<?php
namespace App\Repositories;

use App\Models\User;
use App\Models\UserOtp;
class UserOtpRepository
{
    public function createOrUpdate(User $user, string $otp): UserOtp
    {
        return UserOtp::updateOrCreate(
            ['user_id' => $user->id],
            [
                'otp' => $otp,
                'expired_at' => now()->addMinutes(10),
                'verified_at' => null,
            ]
        );
    }

    public function findValid(User $user, string $otp): ?UserOtp
    {
        return UserOtp::where('user_id', $user->id)
            ->where('otp', $otp)
            ->whereNull('verified_at')
            ->where('expired_at', '>', now())
            ->first();
    }

    public function verify(UserOtp $otp): void
    {
        $otp->update([
            'verified_at' => now()
        ]);
    }
}
