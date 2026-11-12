<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GreetingTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'          => 'required|string|max:255',
            'type'          => 'required|in:Birthday,Holiday,Work Anniversary,5 Year Milestone',
            'trigger_event' => 'nullable|string|max:255',
            'holiday_date'  => 'required_if:type,Holiday|nullable|date',
            'send_time'     => 'nullable|string',
            'status'        => 'required|in:Active,Disabled',
            'target_audience' => 'required|in:Clients,Employee',
            'subject'       => 'nullable|string|max:255',
            'body'          => 'nullable|string',
        ];
    }
}

