<?php

declare(strict_types=1);

namespace Foundation\Common\Http\Requests;

use Foundation\Common\Traits\HasPrincipal;
use Illuminate\Foundation\Http\FormRequest;

abstract class ApiRequest extends FormRequest
{
    use HasPrincipal;

    final public function authorize(): bool
    {
        return true; // authorization is delegated to Policies
    }
}
