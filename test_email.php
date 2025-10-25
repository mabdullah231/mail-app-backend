<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Mail;
use Illuminate\Mail\Message;

try {
    echo "Testing email configuration...\n";
    echo "MAIL_MAILER: " . config('mail.default') . "\n";
    echo "MAIL_HOST: " . config('mail.mailers.smtp.host') . "\n";
    echo "MAIL_PORT: " . config('mail.mailers.smtp.port') . "\n";
    echo "MAIL_FROM: " . config('mail.from.address') . "\n";
    echo "\n";

    $toEmail = 'imshoaibdev@gmail.com';
    
    Mail::raw('This is a test email to verify your email configuration is working correctly! Sent from your Laravel mail app.', function (Message $message) use ($toEmail) {
        $message->to($toEmail)
                ->subject('Test Email - Email Configuration Working!')
                ->from(config('mail.from.address'), config('mail.from.name'));
    });

    echo "✅ Test email sent successfully to {$toEmail}\n";
    echo "Please check your inbox (and spam folder) for the test email.\n";

} catch (Exception $e) {
    echo "❌ Failed to send test email\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "\nPlease check your .env file and make sure you have:\n";
    echo "1. Set MAIL_USERNAME to your Gmail address\n";
    echo "2. Set MAIL_PASSWORD to your Gmail App Password (not your regular password)\n";
    echo "3. Enabled 2-factor authentication on your Gmail account\n";
    echo "4. Generated an App Password for this application\n";
}