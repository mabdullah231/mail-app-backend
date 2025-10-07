<?php

namespace App\Exports;

use App\Models\Customer;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class EmailsExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return Customer::with(['company.user'])
            ->select('name', 'email', 'phone', 'address', 'country', 'company_id', 'created_at')
            ->get();
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'Name',
            'Email',
            'Phone',
            'Address',
            'Country',
            'Company Name',
            'Company Owner',
            'Date Added'
        ];
    }

    /**
     * @param mixed $customer
     * @return array
     */
    public function map($customer): array
    {
        return [
            $customer->name,
            $customer->email,
            $customer->phone ?? '',
            $customer->address ?? '',
            $customer->country ?? '',
            $customer->company->name ?? '',
            $customer->company->user->name ?? '',
            $customer->created_at->format('Y-m-d H:i:s')
        ];
    }
}
