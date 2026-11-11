<?php

declare(strict_types=1);

namespace App\Http\Requests\Alerts;

use App\Http\Requests\Alerts\Concerns\ActsAsSosOwner;
use Illuminate\Foundation\Http\FormRequest;

/** `GET sos/{alert:ulid}` (P5-06) : statut et personnes prévenues, pour le compte propriétaire ou le porteur du `X-Sos-Token`. */
final class ShowSosRequest extends FormRequest
{
    use ActsAsSosOwner;
}
