<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Mail\Message;

class TestEmailController extends Controller
{
    public function sendTestEmail(Request $request)
    {
        try {
            $toEmail = $request->input('email', 'imshoaibdev@gmail.com');
            
            Mail::raw('This is a test email to verify your email configuration is working correctly!', function (Message $message) use ($toEmail) {
                $message->to($toEmail)
                        ->subject('Test Email - Email Configuration Working!')
                        ->from(config('mail.from.address'), config('mail.from.name'));
            });

            return response()->json([
                'success' => true,
                'message' => "Test email sent successfully to {$toEmail}",
                'email_config' => [
                    'mailer' => config('mail.default'),
                    'host' => config('mail.mailers.smtp.host'),
                    'port' => config('mail.mailers.smtp.port'),
                    'from' => config('mail.from.address')
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send test email',
                'error' => $e->getMessage(),
                'email_config' => [
                    'mailer' => config('mail.default'),
                    'host' => config('mail.mailers.smtp.host'),
                    'port' => config('mail.mailers.smtp.port'),
                    'from' => config('mail.from.address')
                ]
            ], 500);
        }
    }
}