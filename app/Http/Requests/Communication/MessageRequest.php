<?php

namespace App\Http\Requests\Communication;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Reply to an existing Communication Hub thread. `communication.create` —
 * the same permission that lets a member open a thread also lets them add to
 * one.
 */
class MessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(Permission::CommunicationCreate) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
        ];
    }

    public function body(): string
    {
        return $this->string('body')->trim()->value();
    }
}
