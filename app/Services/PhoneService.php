<?php

namespace App\Services;

class PhoneService
{
    /**
     * Normalize phone number to E.164 format
     */
    public static function normalizeToE164(string $phone, string $defaultCountry = 'US'): string
    {
        // Remove all non-digit characters except +
        $phone = preg_replace('/[^\d+]/', '', $phone);
        
        // If it starts with +, it's already international format
        if (str_starts_with($phone, '+')) {
            return $phone;
        }
        
        // If it starts with 00, replace with +
        if (str_starts_with($phone, '00')) {
            $phone = '+' . substr($phone, 2);
            return $phone;
        }
        
        // If it starts with 1 and looks like US number, add +
        if (str_starts_with($phone, '1') && strlen($phone) == 11) {
            return '+' . $phone;
        }
        
        // If it's 10 digits and default country is US, add +1
        if (strlen($phone) == 10 && $defaultCountry == 'US') {
            return '+1' . $phone;
        }
        
        // For other countries, you might want to add country codes
        // This is a basic implementation - you might want to use a library like libphonenumber
        $countryCodes = [
            'US' => '1',
            'CA' => '1',
            'GB' => '44',
            'AU' => '61',
            'DE' => '49',
            'FR' => '33',
            'IT' => '39',
            'ES' => '34',
            'NL' => '31',
            'BE' => '32',
            'CH' => '41',
            'AT' => '43',
            'SE' => '46',
            'NO' => '47',
            'DK' => '45',
            'FI' => '358',
            'PL' => '48',
            'CZ' => '420',
            'HU' => '36',
            'RO' => '40',
            'BG' => '359',
            'HR' => '385',
            'SI' => '386',
            'SK' => '421',
            'LT' => '370',
            'LV' => '371',
            'EE' => '372',
            'IE' => '353',
            'PT' => '351',
            'GR' => '30',
            'CY' => '357',
            'MT' => '356',
            'LU' => '352',
            'IS' => '354',
            'LI' => '423',
            'MC' => '377',
            'SM' => '378',
            'VA' => '379',
            'AD' => '376',
            'JP' => '81',
            'KR' => '82',
            'CN' => '86',
            'IN' => '91',
            'BR' => '55',
            'MX' => '52',
            'AR' => '54',
            'CL' => '56',
            'CO' => '57',
            'PE' => '51',
            'VE' => '58',
            'UY' => '598',
            'PY' => '595',
            'BO' => '591',
            'EC' => '593',
            'GY' => '592',
            'SR' => '597',
            'FK' => '500',
            'ZA' => '27',
            'EG' => '20',
            'NG' => '234',
            'KE' => '254',
            'GH' => '233',
            'MA' => '212',
            'TN' => '216',
            'DZ' => '213',
            'LY' => '218',
            'SD' => '249',
            'ET' => '251',
            'UG' => '256',
            'TZ' => '255',
            'ZM' => '260',
            'ZW' => '263',
            'BW' => '267',
            'NA' => '264',
            'SZ' => '268',
            'LS' => '266',
            'MG' => '261',
            'MU' => '230',
            'SC' => '248',
            'KM' => '269',
            'DJ' => '253',
            'SO' => '252',
            'ER' => '291',
            'SS' => '211',
            'CF' => '236',
            'TD' => '235',
            'CM' => '237',
            'GQ' => '240',
            'GA' => '241',
            'CG' => '242',
            'CD' => '243',
            'AO' => '244',
            'GW' => '245',
            'GN' => '224',
            'SL' => '232',
            'LR' => '231',
            'CI' => '225',
            'GH' => '233',
            'TG' => '228',
            'BJ' => '229',
            'NE' => '227',
            'BF' => '226',
            'ML' => '223',
            'SN' => '221',
            'GM' => '220',
            'GN' => '224',
            'GW' => '245',
            'CV' => '238',
            'ST' => '239',
            'MR' => '222',
            'EH' => '212',
            'RU' => '7',
            'KZ' => '7',
            'UZ' => '998',
            'KG' => '996',
            'TJ' => '992',
            'TM' => '993',
            'AF' => '93',
            'PK' => '92',
            'BD' => '880',
            'LK' => '94',
            'MV' => '960',
            'BT' => '975',
            'NP' => '977',
            'MM' => '95',
            'TH' => '66',
            'LA' => '856',
            'KH' => '855',
            'VN' => '84',
            'MY' => '60',
            'SG' => '65',
            'BN' => '673',
            'ID' => '62',
            'PH' => '63',
            'TW' => '886',
            'HK' => '852',
            'MO' => '853',
            'MN' => '976',
            'KP' => '850',
            'FJ' => '679',
            'PG' => '675',
            'SB' => '677',
            'VU' => '678',
            'NC' => '687',
            'PF' => '689',
            'WS' => '685',
            'TO' => '676',
            'KI' => '686',
            'TV' => '688',
            'NR' => '674',
            'PW' => '680',
            'FM' => '691',
            'MH' => '692',
            'AS' => '1684',
            'GU' => '1671',
            'MP' => '1670',
            'VI' => '1340',
            'PR' => '1787',
            'DO' => '1809',
            'HT' => '509',
            'CU' => '53',
            'JM' => '1876',
            'BS' => '1242',
            'BB' => '1246',
            'AG' => '1268',
            'DM' => '1767',
            'GD' => '1473',
            'KN' => '1869',
            'LC' => '1758',
            'VC' => '1784',
            'TT' => '1868',
            'GY' => '592',
            'SR' => '597',
            'BZ' => '501',
            'GT' => '502',
            'SV' => '503',
            'HN' => '504',
            'NI' => '505',
            'CR' => '506',
            'PA' => '507',
            'NZ' => '64',
            'AU' => '61',
            'NC' => '687',
            'PF' => '689',
            'WS' => '685',
            'TO' => '676',
            'KI' => '686',
            'TV' => '688',
            'NR' => '674',
            'PW' => '680',
            'FM' => '691',
            'MH' => '692',
            'AS' => '1684',
            'GU' => '1671',
            'MP' => '1670',
            'VI' => '1340',
            'PR' => '1787',
            'DO' => '1809',
            'HT' => '509',
            'CU' => '53',
            'JM' => '1876',
            'BS' => '1242',
            'BB' => '1246',
            'AG' => '1268',
            'DM' => '1767',
            'GD' => '1473',
            'KN' => '1869',
            'LC' => '1758',
            'VC' => '1784',
            'TT' => '1868',
            'GY' => '592',
            'SR' => '597',
            'BZ' => '501',
            'GT' => '502',
            'SV' => '503',
            'HN' => '504',
            'NI' => '505',
            'CR' => '506',
            'PA' => '507',
        ];
        
        if (isset($countryCodes[$defaultCountry])) {
            return '+' . $countryCodes[$defaultCountry] . $phone;
        }
        
        // If we can't determine the country code, return as is
        return $phone;
    }
    
    /**
     * Validate if phone number is in E.164 format
     */
    public static function isValidE164(string $phone): bool
    {
        return preg_match('/^\+[1-9]\d{1,14}$/', $phone);
    }
    
    /**
     * Format phone number for display
     */
    public static function formatForDisplay(string $phone): string
    {
        if (!self::isValidE164($phone)) {
            return $phone;
        }
        
        // Remove the + sign
        $phone = substr($phone, 1);
        
        // Format US/Canada numbers
        if (str_starts_with($phone, '1') && strlen($phone) == 11) {
            return '+1 (' . substr($phone, 1, 3) . ') ' . substr($phone, 4, 3) . '-' . substr($phone, 7, 4);
        }
        
        // Format other numbers (basic formatting)
        if (strlen($phone) >= 10) {
            return '+' . substr($phone, 0, -10) . ' ' . substr($phone, -10, 3) . ' ' . substr($phone, -7, 3) . ' ' . substr($phone, -4);
        }
        
        return '+' . $phone;
    }
}
