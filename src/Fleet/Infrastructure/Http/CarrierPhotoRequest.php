<?php

declare(strict_types=1);

namespace Isa\Fleet\Infrastructure\Http;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/** Foto del mensajero: solo imágenes JPG, PNG o WebP y con tamaño máximo (config isa.fleet). */
final class CarrierPhotoRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'dimensions:min_width=64,min_height=64,max_width=6000,max_height=6000', 'max:'.(int) config('isa.fleet.photo_max_kb')],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['photo' => 'foto'];
    }

    public function photo(): UploadedFile
    {
        return $this->file('photo');
    }
}
