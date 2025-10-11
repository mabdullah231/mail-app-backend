<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\CompanyDetail;

class DomainVerificationController extends Controller
{
    /**
     * Get domain verification status and guidance
     */
    public function getVerificationStatus()
    {
        $user = Auth::user();
        $company = $user->companyDetail;
        
        if (!$company) {
            return response()->json(['message' => 'Company details required'], 400);
        }

        $businessEmail = $company->business_email;
        $domain = $this->extractDomain($businessEmail);
        
        if (!$domain) {
            return response()->json([
                'status' => 'no_domain',
                'message' => 'No business email domain found',
                'guidance' => [
                    'step' => 1,
                    'title' => 'Set Business Email',
                    'description' => 'First, set your business email in company settings',
                    'action' => 'Go to Settings > Company Details'
                ]
            ]);
        }

        // Check SPF record
        $spfStatus = $this->checkSPFRecord($domain);
        
        // Check DKIM record (simplified check)
        $dkimStatus = $this->checkDKIMRecord($domain);
        
        // Check DMARC record
        $dmarcStatus = $this->checkDMARCRecord($domain);

        $overallStatus = 'verified';
        if (!$spfStatus['valid'] || !$dkimStatus['valid'] || !$dmarcStatus['valid']) {
            $overallStatus = 'needs_setup';
        }

        return response()->json([
            'status' => $overallStatus,
            'domain' => $domain,
            'business_email' => $businessEmail,
            'spf' => $spfStatus,
            'dkim' => $dkimStatus,
            'dmarc' => $dmarcStatus,
            'guidance' => $this->getGuidanceSteps($spfStatus, $dkimStatus, $dmarcStatus)
        ]);
    }

    /**
     * Extract domain from email
     */
    private function extractDomain($email)
    {
        if (!$email) return null;
        
        $parts = explode('@', $email);
        return count($parts) === 2 ? $parts[1] : null;
    }

    /**
     * Check SPF record
     */
    private function checkSPFRecord($domain)
    {
        $records = dns_get_record($domain, DNS_TXT);
        
        foreach ($records as $record) {
            if (strpos($record['txt'], 'v=spf1') === 0) {
                return [
                    'valid' => true,
                    'record' => $record['txt'],
                    'message' => 'SPF record found'
                ];
            }
        }
        
        return [
            'valid' => false,
            'record' => null,
            'message' => 'No SPF record found',
            'recommended_record' => 'v=spf1 include:_spf.google.com ~all'
        ];
    }

    /**
     * Check DKIM record (simplified)
     */
    private function checkDKIMRecord($domain)
    {
        // This is a simplified check - in reality you'd need to check for specific DKIM selectors
        $records = dns_get_record($domain, DNS_TXT);
        
        foreach ($records as $record) {
            if (strpos($record['txt'], 'v=DKIM1') === 0) {
                return [
                    'valid' => true,
                    'record' => $record['txt'],
                    'message' => 'DKIM record found'
                ];
            }
        }
        
        return [
            'valid' => false,
            'record' => null,
            'message' => 'No DKIM record found',
            'recommended_record' => 'Contact your email provider for DKIM setup'
        ];
    }

    /**
     * Check DMARC record
     */
    private function checkDMARCRecord($domain)
    {
        $records = dns_get_record("_dmarc.{$domain}", DNS_TXT);
        
        foreach ($records as $record) {
            if (strpos($record['txt'], 'v=DMARC1') === 0) {
                return [
                    'valid' => true,
                    'record' => $record['txt'],
                    'message' => 'DMARC record found'
                ];
            }
        }
        
        return [
            'valid' => false,
            'record' => null,
            'message' => 'No DMARC record found',
            'recommended_record' => 'v=DMARC1; p=quarantine; rua=mailto:dmarc@' . $domain
        ];
    }

    /**
     * Get guidance steps based on verification status
     */
    private function getGuidanceSteps($spf, $dkim, $dmarc)
    {
        $steps = [];
        
        if (!$spf['valid']) {
            $steps[] = [
                'step' => count($steps) + 1,
                'title' => 'Set up SPF Record',
                'description' => 'Add an SPF record to your domain DNS to authorize Email Zus to send emails on your behalf',
                'action' => 'Add TXT record',
                'record_type' => 'TXT',
                'record_name' => '@',
                'record_value' => $spf['recommended_record'],
                'priority' => 'high'
            ];
        }
        
        if (!$dkim['valid']) {
            $steps[] = [
                'step' => count($steps) + 1,
                'title' => 'Set up DKIM Record',
                'description' => 'Configure DKIM to digitally sign your emails and improve deliverability',
                'action' => 'Contact your email provider',
                'record_type' => 'TXT',
                'record_name' => 'default._domainkey',
                'record_value' => 'Contact your email provider for DKIM key',
                'priority' => 'medium'
            ];
        }
        
        if (!$dmarc['valid']) {
            $steps[] = [
                'step' => count($steps) + 1,
                'title' => 'Set up DMARC Record',
                'description' => 'Configure DMARC to protect against email spoofing and improve reputation',
                'action' => 'Add TXT record',
                'record_type' => 'TXT',
                'record_name' => '_dmarc',
                'record_value' => $dmarc['recommended_record'],
                'priority' => 'medium'
            ];
        }
        
        if (empty($steps)) {
            $steps[] = [
                'step' => 1,
                'title' => 'Domain Verified!',
                'description' => 'Your domain is properly configured for email sending',
                'action' => 'All set!',
                'priority' => 'success'
            ];
        }
        
        return $steps;
    }

    /**
     * Get DNS record templates
     */
    public function getDNSTemplates()
    {
        return response()->json([
            'spf' => [
                'type' => 'TXT',
                'name' => '@',
                'value' => 'v=spf1 include:_spf.google.com ~all',
                'description' => 'Authorizes Google and other specified servers to send emails'
            ],
            'dkim' => [
                'type' => 'TXT',
                'name' => 'default._domainkey',
                'value' => 'Contact your email provider for DKIM key',
                'description' => 'Digital signature for email authentication'
            ],
            'dmarc' => [
                'type' => 'TXT',
                'name' => '_dmarc',
                'value' => 'v=DMARC1; p=quarantine; rua=mailto:dmarc@yourdomain.com',
                'description' => 'Policy for handling authentication failures'
            ]
        ]);
    }

    /**
     * Test email deliverability
     */
    public function testDeliverability(Request $request)
    {
        $request->validate([
            'test_email' => 'required|email'
        ]);

        $user = Auth::user();
        $company = $user->companyDetail;
        
        if (!$company) {
            return response()->json(['message' => 'Company details required'], 400);
        }

        // Send test email
        try {
            \Mail::raw('This is a test email to verify your domain configuration.', function ($message) use ($request, $company) {
                $message->to($request->test_email)
                        ->subject('Domain Verification Test - Email Zus')
                        ->from($company->business_email ?? config('mail.from.address'));
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Test email sent successfully. Check your inbox and spam folder.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send test email: ' . $e->getMessage()
            ], 500);
        }
    }
}
