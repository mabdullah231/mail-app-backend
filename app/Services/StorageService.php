<?php

namespace App\Services;

use App\Models\CompanyDetail;
use Illuminate\Support\Facades\File;

class StorageService
{
    /**
     * Calculate current storage usage for a company
     */
    public static function getCurrentUsage(CompanyDetail $company): int
    {
        $usage = 0;
        
        // Check company logos and signatures
        if ($company->logo && File::exists(public_path($company->logo))) {
            $usage += File::size(public_path($company->logo));
        }
        
        if ($company->signature && File::exists(public_path($company->signature))) {
            $usage += File::size(public_path($company->signature));
        }
        
        // Check template attachments
        $templates = $company->templates;
        foreach ($templates as $template) {
            if ($template->attachments) {
                foreach ($template->attachments as $attachment) {
                    $path = is_array($attachment) ? $attachment['path'] : $attachment;
                    if ($path && File::exists(public_path($path))) {
                        $usage += File::size(public_path($path));
                    }
                }
            }
        }
        
        // Convert to MB
        return round($usage / (1024 * 1024), 2);
    }
    
    /**
     * Get storage limit for a company
     */
    public static function getStorageLimit(CompanyDetail $company): int
    {
        $subscription = $company->subscription;
        
        if ($subscription) {
            return $subscription->limits['storage_mb'] ?? 100;
        }
        
        // Check company-specific limit
        return $company->storage_limit ?? 50; // Default 50MB for free users
    }
    
    /**
     * Check if file upload would exceed storage limit
     */
    public static function canUpload(CompanyDetail $company, int $fileSizeMB): bool
    {
        $currentUsage = self::getCurrentUsage($company);
        $limit = self::getStorageLimit($company);
        
        return ($currentUsage + $fileSizeMB) <= $limit;
    }
    
    /**
     * Get storage usage info
     */
    public static function getUsageInfo(CompanyDetail $company): array
    {
        $currentUsage = self::getCurrentUsage($company);
        $limit = self::getStorageLimit($company);
        
        return [
            'current_usage_mb' => $currentUsage,
            'limit_mb' => $limit,
            'remaining_mb' => max(0, $limit - $currentUsage),
            'usage_percentage' => $limit > 0 ? round(($currentUsage / $limit) * 100, 2) : 0
        ];
    }
}
