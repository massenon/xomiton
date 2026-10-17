<?php

declare(strict_types=1);

namespace App\Application\Mail\Exceptions;

final class TemplateNotFound extends TemplateException
{
    public static function forSlug(string $slug): self
    {
        return new self($slug, sprintf('Template « %s » introuvable.', $slug));
    }
}
