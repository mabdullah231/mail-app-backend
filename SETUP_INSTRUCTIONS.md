# Email Marketing Platform - Setup Instructions

## Prerequisites
- PHP 8.2 or higher
- Composer
- MySQL/MariaDB
- Node.js and NPM (for frontend)

## Backend Setup

### 1. Install Dependencies
```bash
cd mail-app-backend
composer install
```

### 2. Environment Configuration
Copy `.env.example` to `.env` and configure the following:

```env
# Database Configuration
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mail_app_backend
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Mail Configuration (for sending emails)
MAIL_MAILER=smtp
MAIL_HOST=your_smtp_host
MAIL_PORT=587
MAIL_USERNAME=your_email@domain.com
MAIL_PASSWORD=your_email_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your_email@domain.com
MAIL_FROM_NAME="Email Zus"

# Twilio Configuration (for SMS)
TWILIO_SID=your_twilio_sid
TWILIO_TOKEN=your_twilio_token
TWILIO_PHONE=+1234567890

# PayPal Configuration (for payments)
PAYPAL_CLIENT_ID=your_paypal_client_id
PAYPAL_CLIENT_SECRET=your_paypal_client_secret
PAYPAL_MODE=sandbox

# Google OAuth (optional)
GOOGLE_CLIENT_ID=your_google_client_id
GOOGLE_CLIENT_SECRET=your_google_client_secret
GOOGLE_REDIRECT_URL=http://localhost:8000/auth/google/callback
```

### 3. Generate Application Key
```bash
php artisan key:generate
```

### 4. Run Database Migrations
```bash
php artisan migrate
```

### 5. Create Storage Links
```bash
php artisan storage:link
```

### 6. Install Additional Packages
```bash
composer require twilio/sdk maatwebsite/excel
```

### 7. Start the Development Server
```bash
php artisan serve
```

## API Endpoints

### Authentication
- `POST /api/auth/register` - Register new user
- `POST /api/auth/login` - Login user
- `POST /api/auth/verify-email` - Verify email with code
- `POST /api/auth/logout` - Logout user

### Company Management
- `POST /api/company/store-or-update` - Create/update company details
- `GET /api/company/details` - Get company details

### Customer Management
- `GET /api/customers` - List customers
- `POST /api/customers` - Create customer
- `PUT /api/customers/{id}` - Update customer
- `DELETE /api/customers/{id}` - Delete customer

### Template Management
- `GET /api/templates` - List templates
- `POST /api/templates` - Create template
- `PUT /api/templates/{id}` - Update template
- `DELETE /api/templates/{id}` - Delete template

### Email Sending
- `POST /api/email/send-single` - Send email to one customer
- `POST /api/email/send-bulk` - Send email to multiple customers
- `POST /api/email/send-to-all` - Send email to all customers
- `GET /api/email/stats` - Get email statistics

### SMS Sending
- `POST /api/sms/send-single` - Send SMS to one customer
- `POST /api/sms/send-bulk` - Send SMS to multiple customers
- `GET /api/sms/stats` - Get SMS statistics

### Subscription Management
- `GET /api/subscription/current` - Get current subscription
- `POST /api/subscription/subscribe-branding-removal` - Subscribe to remove branding
- `GET /api/subscription/pricing` - Get pricing information

### Super Admin (User Type 0 only)
- `GET /api/super-admin/dashboard-stats` - Platform statistics
- `GET /api/super-admin/users` - List all users
- `PUT /api/super-admin/users/{id}/toggle-status` - Ban/unban user
- `GET /api/super-admin/emails/export` - Export all emails to Excel

## User Types
- **0**: Super Admin (Platform owner)
- **1**: Business Admin (Company owner)
- **2**: Regular User (Not used in current implementation)

## Automated Features

### Scheduler Setup
Add to your crontab for automated reminders:
```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

### Queue Setup (Optional)
For better performance with bulk emails:
```bash
php artisan queue:work
```

## File Upload Limits
- Company logos: Max 2MB (PNG, JPG, JPEG, GIF)
- Signatures: Max 2MB (PNG, JPG, JPEG, GIF)

## Template Placeholders
Available placeholders for email templates:
- `{{customer.name}}` - Customer name
- `{{customer.email}}` - Customer email
- `{{customer.phone}}` - Customer phone
- `{{customer.address}}` - Customer address
- `{{customer.country}}` - Customer country
- `{{company.name}}` - Company name
- `{{company.address}}` - Company address
- `{{company.logo}}` - Company logo (auto-formatted as image)
- `{{company.signature}}` - Company signature (auto-formatted as image)

## Subscription Plans
- **Free**: 100 emails/month, 3 templates, 10 SMS/month, includes branding
- **Paid**: Remove branding option
  - $5/month
  - $14/3 months
  - $26/6 months
  - $50/12 months

## Security Features
- Email verification required
- Sanctum API authentication
- Soft deletes for data recovery
- File upload validation
- Rate limiting on email/SMS sending

## Troubleshooting

### Common Issues
1. **Migration errors**: Ensure database exists and credentials are correct
2. **File upload issues**: Check storage permissions and symlink
3. **Email not sending**: Verify SMTP configuration
4. **SMS not working**: Check Twilio credentials and phone number format

### Logs
Check Laravel logs at `storage/logs/laravel.log` for detailed error information.
