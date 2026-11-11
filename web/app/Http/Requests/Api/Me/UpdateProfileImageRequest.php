<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Me;

use App\Rules\UploadAllowed;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/v1/me/profile/avatar · POST me/profile/cover (P8-07) : `image` multipart, collection `images` de
 * config/xomiton.php (JPEG, PNG, WebP, 5 Mo ; type réel lu dans le contenu, UploadAllowed).
 */
final class UpdateProfileImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'image' => ['required', 'file', new UploadAllowed('images')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['image' => (string) __('profile.image_attribute')];
    }
}
