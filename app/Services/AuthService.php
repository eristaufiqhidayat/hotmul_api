<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepository;
use App\Repositories\UserOtpRepository;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tymon\JWTAuth\Facades\JWTAuth;
use Exception;

class AuthService
{
    protected UserRepository $userRepo;
    protected UserOtpRepository $otpRepo;

    public function __construct(
        UserRepository $userRepo,
        UserOtpRepository $otpRepo
    ) {
        $this->userRepo = $userRepo;
        $this->otpRepo  = $otpRepo;
    }

    /**
     * REGISTER + OTP
     */
    public function curl_ngirimwa_meta_templateBak($to, $message)
    {
        $appkey = 'https://graph.facebook.com/v18.0/244876815379879/messages';
        $authkey = "EAA0ZBuZBvbqusBO0sTcLaUlcQaKTkR5we2PbAlRL4lR3ByZA09WW99yUma3hxHChIsinczey1xxeKQG79DUKuZCh6636E9uyMuzHtxdvXHTEvVM7M6soGr3LSpFnZCuNbCizmFTPZAi9njo75joZBuHOTZAmmZC0dnYAs0XZCkhZADAA6tfZCsulmiP3ljoZA08ptSqqY";
        //$to = "+628118684222";
        //curl -i -X POST https://graph.facebook.com/v13.0/<YOUR PHONE NUMBER ID>/messages -H 'Authorization: Bearer <YOUR ACCESS TOKEN>' -H 'Content-Type: application/json' -d '{ "messaging_product": "whatsapp", "to": "<PHONE NUMBER TO MESSAGE>", "type": "template", "template": { "name": "hello_world", \"language\": { \"code\": \"en_US\" } } }'
        $text = array(
            'preview_url' => 'true', //your your own or any default template. The names and samples are listed under message templates
            'body' => $message //you can use yours
        );
        $number = $to; //you can use POST, I tried GET for testing
        $type = "text";
        $endpoint = $appkey;
        $params = array('messaging_product' => 'whatsapp', 'to' => $number,  'access_token' => $authkey, 'type' => $type, 'text' => json_encode($text));
        $headers = array('Authorization' => $authkey, 'Content-Type' => 'application/json',  'User-Agent' => '(Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/68.0.3440.106 Safari/537.36');
        $url = $endpoint . '?' . http_build_query($params);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        $result = curl_exec($ch);
        echo $result;
        curl_close($ch);
    }
    public function curl_ngirimwa_meta_template($to, $message)
    {
        $url = 'https://webhook.lembaharafah.com/webhook.php';
        $data = [
            'terimawa' =>'ginougi',
            'nohp' => $to,
            'message' => $message
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        echo $response;
    }
    public function register(array $data): User
    {
        if ($this->userRepo->findByEmail($data['email'])) {
            throw new Exception('Email sudah terdaftar', 400);
        }

        $user = $this->userRepo->create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
            'role'  => $data['role'] ?? null,
            'group_id'    => $data['group_id'] ?? null,
        ]);

        // $otp = rand(100000, 999999);

        // $this->otpRepo->createOrUpdate($user, $otp);

        // Mail::raw(
        //     "Kode OTP Anda: {$otp}\nBerlaku 10 menit.",
        //     fn($m) =>
        //     $m->to($user->email)
        //         ->subject('Verifikasi Email - PT. Ginougi Indonesia')
        // );
        // $this->curl_ngirimwa_meta_template(
        //     //$to,
        //     $user->no_hp,
        //     "PT. Ginougi Indonesia - Kode OTP Anda: {$otp}\nBerlaku 10 menit."
        // );

        return $user;
    }
    public function resendOtp(array $data): User
    {
        // 1️⃣ Pastikan user SUDAH ADA
        $user = $this->userRepo->findByEmail($data['email']);

        if (!$user) {
            throw new Exception('User tidak ditemukan');
        }

        // 2️⃣ Generate OTP baru
        $otp = rand(100000, 999999);

        // 3️⃣ Update OTP (replace OTP lama)
        $this->otpRepo->createOrUpdate($user, (string) $otp);

        // 4️⃣ Kirim OTP via email
        Mail::raw(
            "Kode OTP Anda: {$otp}\nBerlaku 10 menit.",
            fn($m) =>
            $m->to($user->email)
                ->subject('Resend OTP - PT. Ginougi Indonesia')
        );
        $this->curl_ngirimwa_meta_template(

            //$to,
            $user->no_hp,
            "Resend OTP - PT. Ginougi Indonesia - Kode OTP Anda: {$otp}\nBerlaku 10 menit."
        );
        return $user;
    }
    /**
     * VERIFY OTP
     */
    public function verifyOtp(string $email, string $otp): void
    {
        $user = $this->userRepo->findByEmail($email);

        if (!$user) {
            throw new Exception('User tidak ditemukan');
        }

        $otpData = $this->otpRepo->findValid($user, $otp);

        if (!$otpData) {
            throw new Exception('OTP salah atau kadaluarsa');
        }

        $this->otpRepo->verify($otpData);

        $user->update([
            'email_verified_at' => now()
        ]);
    }
    public function verifyOtpAndLogin(string $email, string $otp): string
    {
        $user = $this->userRepo->findByEmail($email);

        if (!$user) {
            throw new Exception('User tidak ditemukan');
        }

        if ($user->email_verified_at) {
            throw new Exception('Email sudah diverifikasi');
        }

        $otpData = $this->otpRepo->findValid($user, $otp);

        if (!$otpData) {
            throw new Exception('OTP salah atau kadaluarsa');
        }

        // verify otp
        $this->otpRepo->verify($otpData);

        // update email_verified_at
        $user->update([
            'email_verified_at' => now()
        ]);

        // 🔥 AUTO LOGIN
        return JWTAuth::fromUser($user);
    }
}
