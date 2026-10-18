<?php

declare(strict_types=1);

namespace App\Http\Requests\Alerts;

use App\Http\Requests\Alerts\Concerns\ActsAsSosOwner;
use Illuminate\Foundation\Http\FormRequest;

/** `POST sos/{alert:ulid}/cancel` (P5-06) : repentir de 60 s après l'envoi (CancelSos, Q39). */
final class CancelSosRequest extends FormRequest
{
    use ActsAsSosOwner;
}
