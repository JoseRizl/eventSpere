<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAnnouncementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Assuming any authenticated user with the correct role can create announcements.
        // You can add more specific authorization logic here if needed.
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', function ($attribute, $value, $fail) {
                if (trim($value) === '') {
                    $fail('The announcement message cannot be empty.');
                }
            }],
            'image' => 'nullable|string',
            // The 'userId' from the frontend is now handled by auth()->id() in the controller,
            // but we keep it here in case you want to allow admins to post on behalf of others.
            // For now, we'll rely on the authenticated user.
            // 'userId' => 'required|exists:users,id',
            'event_id' => 'nullable|exists:events,id',
        ];
    }
}
