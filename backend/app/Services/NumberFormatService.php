<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Support\Facades\Auth;

class NumberFormatService
{
    /**
     * Default format templates for different entity types
     * Company ID is NOT included by default for customer privacy
     * Can be enabled via company settings: settings.number_formats.include_company_id = true
     */
    private const DEFAULT_FORMATS = [
        'purchase_order' => '{PREFIX}-{YEAR}-{SEQUENCE}',
        'sales_order' => '{PREFIX}-{YEAR}-{SEQUENCE}',
        'work_order' => '{PREFIX}-{YEARMONTH}-{SEQUENCE}',
        'grn' => '{PREFIX}-{YEAR}-{SEQUENCE}',
        'delivery_note' => '{PREFIX}-{YEAR}-{SEQUENCE}',
        'ncr' => '{PREFIX}-{YEAR}-{SEQUENCE}',
        'inspection' => '{PREFIX}-{YEAR}-{SEQUENCE}',
        'routing' => '{PREFIX}-{SEQUENCE}',
        'bom' => '{PREFIX}-{SEQUENCE}',
        'customer_code' => '{PREFIX}-{SEQUENCE}',
        'supplier_code' => '{PREFIX}-{SEQUENCE}',
        'work_center' => '{PREFIX}-{SEQUENCE}',
        'acceptance_rule' => '{PREFIX}-{SEQUENCE}',
        'mrp_run' => '{PREFIX}-{DATE}-{SEQUENCE}',
    ];

    /**
     * Default prefixes for different entity types
     */
    private const DEFAULT_PREFIXES = [
        'purchase_order' => 'PO',
        'sales_order' => 'SO',
        'work_order' => 'WO',
        'grn' => 'GRN',
        'delivery_note' => 'DN',
        'ncr' => 'NCR',
        'inspection' => 'INS',
        'routing' => 'RTG',
        'bom' => 'BOM',
        'customer_code' => 'CUS',
        'supplier_code' => 'SUP',
        'work_center' => 'WC',
        'acceptance_rule' => 'AR',
        'mrp_run' => 'MRP',
    ];

    /**
     * Default sequence padding
     */
    private const DEFAULT_SEQUENCE_PADDING = [
        'purchase_order' => 5,
        'sales_order' => 5,
        'work_order' => 4,
        'grn' => 5,
        'delivery_note' => 5,
        'ncr' => 5,
        'inspection' => 5,
        'routing' => 5,
        'bom' => 5,
        'customer_code' => 5,
        'supplier_code' => 5,
        'work_center' => 4,
        'acceptance_rule' => 4,
        'mrp_run' => 3,
    ];

    /**
     * Generate number based on format template
     * 
     * @param string $entityType Entity type (e.g., 'purchase_order', 'sales_order')
     * @param int $sequence Sequence number
     * @param int|null $companyId Company ID (if null, uses authenticated user's company)
     * @param string|null $customPrefix Custom prefix (if null, uses default)
     * @param bool|null $includeCompanyId Whether to include company ID (null = check company settings, default: false)
     * @return string Generated number
     */
    public function generate(
        string $entityType,
        int $sequence,
        ?int $companyId = null,
        ?string $customPrefix = null,
        ?bool $includeCompanyId = null
    ): string {
        $companyId = $companyId ?? Auth::user()->company_id;
        $company = Company::find($companyId);
        
        // Get format from company settings or use default
        $format = $this->getFormat($entityType, $company);
        
        // Get prefix
        $prefix = $customPrefix ?? $this->getPrefix($entityType, $company);
        
        // Determine if company ID should be included
        // Priority: 1. Parameter, 2. Company settings, 3. Default (false for privacy)
        if ($includeCompanyId === null) {
            $includeCompanyId = $company->settings['number_formats']['include_company_id'] ?? false;
        }
        
        // Prepare replacement values
        $replacements = [
            '{PREFIX}' => $prefix,
            '{YEAR}' => now()->format('Y'),
            '{YEARMONTH}' => now()->format('Ym'),
            '{DATE}' => now()->format('Ymd'),
            '{SEQUENCE}' => str_pad(
                $sequence,
                self::DEFAULT_SEQUENCE_PADDING[$entityType] ?? 5,
                '0',
                STR_PAD_LEFT
            ),
        ];
        
        // Add company ID if requested
        if ($includeCompanyId) {
            $replacements['{COMPANY_ID}'] = str_pad($companyId, 3, '0', STR_PAD_LEFT);
            // Insert COMPANY_ID into format if not already present
            if (strpos($format, '{COMPANY_ID}') === false) {
                // Insert after YEAR/YEARMONTH/DATE if present, otherwise after PREFIX
                if (strpos($format, '{YEAR}') !== false) {
                    $format = str_replace('{YEAR}', '{YEAR}-{COMPANY_ID}', $format);
                } elseif (strpos($format, '{YEARMONTH}') !== false) {
                    $format = str_replace('{YEARMONTH}', '{YEARMONTH}-{COMPANY_ID}', $format);
                } elseif (strpos($format, '{DATE}') !== false) {
                    $format = str_replace('{DATE}', '{DATE}-{COMPANY_ID}', $format);
                } else {
                    $format = str_replace('{PREFIX}', '{PREFIX}-{COMPANY_ID}', $format);
                }
            }
        } else {
            // Remove COMPANY_ID placeholder if not included
            $format = str_replace('{COMPANY_ID}-', '', $format);
            $format = str_replace('-{COMPANY_ID}', '', $format);
            $format = str_replace('{COMPANY_ID}', '', $format);
        }
        
        // Replace placeholders
        return str_replace(array_keys($replacements), array_values($replacements), $format);
    }

    /**
     * Get format template for entity type
     * 
     * @param string $entityType
     * @param Company|null $company
     * @return string
     */
    private function getFormat(string $entityType, ?Company $company): string
    {
        // Try to get from company settings
        if ($company && isset($company->settings['number_formats'][$entityType])) {
            return $company->settings['number_formats'][$entityType];
        }
        
        // Use default format
        return self::DEFAULT_FORMATS[$entityType] ?? '{PREFIX}-{SEQUENCE}';
    }

    /**
     * Get prefix for entity type
     * 
     * @param string $entityType
     * @param Company|null $company
     * @return string
     */
    private function getPrefix(string $entityType, ?Company $company): string
    {
        // Try to get from company settings
        if ($company && isset($company->settings['number_prefixes'][$entityType])) {
            return $company->settings['number_prefixes'][$entityType];
        }
        
        // Use default prefix
        return self::DEFAULT_PREFIXES[$entityType] ?? 'NUM';
    }

    /**
     * Extract sequence number from a generated number
     * Works with any format - extracts the last numeric part
     * 
     * @param string $number
     * @return int Sequence number (0 if not found)
     */
    public function extractSequence(string $number): int
    {
        // Extract last numeric sequence (handles both old and new formats)
        if (preg_match('/(\d+)$/', $number, $matches)) {
            return (int) $matches[1];
        }
        return 0;
    }

    /**
     * Parse number to extract components
     * 
     * @param string $number
     * @param string $entityType
     * @return array|null Returns null if parsing fails
     */
    public function parse(string $number, string $entityType): ?array
    {
        $format = self::DEFAULT_FORMATS[$entityType] ?? '{PREFIX}-{SEQUENCE}';
        
        // Convert format to regex pattern
        $pattern = preg_replace(
            ['/\{PREFIX\}/', '/\{YEAR\}/', '/\{YEARMONTH\}/', '/\{DATE\}/', '/\{COMPANY_ID\}/', '/\{SEQUENCE\}/'],
            ['([A-Z]+)', '(\d{4})', '(\d{6})', '(\d{8})', '(\d{3})', '(\d+)'],
            preg_quote($format, '/')
        );
        
        if (preg_match('/^' . $pattern . '$/', $number, $matches)) {
            return [
                'prefix' => $matches[1] ?? null,
                'year' => $matches[2] ?? null,
                'yearmonth' => $matches[3] ?? null,
                'date' => $matches[4] ?? null,
                'company_id' => isset($matches[5]) ? (int) $matches[5] : null,
                'sequence' => isset($matches[6]) ? (int) $matches[6] : (int) end($matches),
            ];
        }
        
        return null;
    }

    /**
     * Get available format placeholders
     * 
     * @return array
     */
    public function getAvailablePlaceholders(): array
    {
        return [
            '{PREFIX}' => 'Entity prefix (e.g., PO, SO, WO)',
            '{YEAR}' => 'Current year (YYYY)',
            '{YEARMONTH}' => 'Current year and month (YYYYMM)',
            '{DATE}' => 'Current date (YYYYMMDD)',
            '{COMPANY_ID}' => 'Company ID (3 digits, zero-padded)',
            '{SEQUENCE}' => 'Sequence number (padded based on entity type)',
        ];
    }
}
