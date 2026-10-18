<?php

declare(strict_types=1);

namespace App\Http\Requests\Alerts;

use App\Http\Requests\Alerts\Concerns\ActsAsSosOwner;
use Illuminate\Foundation\Http\FormRequest;

/** `POST sos/{alert:ulid}/safe` (P5-06) : « Je suis en sécurité » (MarkSafe). */
final class MarkSafeRequest extends FormRequest
{
    use ActsAsSosOwner;
}
