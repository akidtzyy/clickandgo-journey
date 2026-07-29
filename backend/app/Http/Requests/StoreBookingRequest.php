<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Auth is handled by route middleware
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        \Illuminate\Support\Facades\Log::info('StoreBookingRequest inputs:', $this->except(['ktp_passport_file', 'sim_idp_file']));
        return [
            'name'         => ['required', 'string', 'min:3', 'max:255'],
            'email'        => ['required', 'email'],
            'phone'        => ['required', 'string', 'regex:/^(\+62|62|0)[0-9]{8,14}$/'],
            'booking_type' => ['required', 'in:package,car_rental'],
            'item_id'      => ['required', 'integer', 'min:1'],
            'date'         => ['required', 'date', 'after_or_equal:today'],
            'duration'     => ['required', 'string', 'max:100'],
            'quantity'     => ['sometimes', 'integer', 'min:1', 'max:99'],
            'notes'        => ['nullable', 'string', 'max:2000'],
            'payment_type' => ['required', 'in:FULL,DP'],
            'nationality_type' => ['required', 'in:WNI,WNA'],
            'identity_type'    => ['required', 'in:NIK,PASSPORT'],
            'identity_number'  => ['nullable', 'string', 'max:50'],
            'ktp_passport_file' => ['nullable', 'file', 'max:10240'],
            'sim_idp_file'      => ['nullable', 'file', 'max:10240'],
        ];
    }

    /**
     * Custom validation error messages.
     */
    public function messages(): array
    {
        return [
            'phone.regex'         => 'Nomor telepon harus dalam format Indonesia (misal: 08xxxxxxxx atau +628xxxxxxxx).',
            'date.after_or_equal' => 'Tanggal booking tidak boleh sebelum hari ini.',
            'booking_type.in'     => 'Tipe booking hanya boleh "package" atau "car_rental".',
            'payment_type.in'     => 'Tipe pembayaran hanya boleh "FULL" atau "DP".',
            'nationality_type.in' => 'Kewarganegaraan hanya boleh "WNI" atau "WNA".',
        ];
    }
}
