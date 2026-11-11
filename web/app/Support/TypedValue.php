<?php

declare(strict_types=1);

namespace App\Support;

use BackedEnum;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * Conversion prudente d'une valeur venue de l'extérieur (paramètre d'URL, état d'un lien d'export signé) vers le type
 * déclaré d'une propriété publique : `?par=abc`, `?q[]=x` ou `?tri[]=x` ne lèvent plus de TypeError (500), la valeur
 * est simplement ignorée et la propriété garde son défaut. Types gérés : string, int, float, bool, array, énumération
 * adossée (nullables compris) ; tout autre type est rendu tel quel (`handled` faux) pour laisser l'appelant décider.
 */
final class TypedValue
{
    private function __construct() {}

    /** Nom du type déclaré de la propriété (null : non typée, union ou absente). */
    public static function typeName(object $target, string $property): ?string
    {
        if (! property_exists($target, $property)) {
            return null;
        }

        $type = (new ReflectionProperty($target, $property))->getType();

        return $type instanceof ReflectionNamedType ? $type->getName() : null;
    }

    /**
     * @return array{handled: bool, ok: bool, value: mixed} `ok` faux : valeur à ignorer (défaut conservé)
     */
    public static function coerce(object $target, string $property, mixed $value): array
    {
        if (! property_exists($target, $property)) {
            return ['handled' => false, 'ok' => false, 'value' => $value];
        }

        $type = (new ReflectionProperty($target, $property))->getType();

        if (! $type instanceof ReflectionNamedType) {
            return ['handled' => false, 'ok' => true, 'value' => $value];
        }

        if ($value === null) {
            return ['handled' => true, 'ok' => $type->allowsNull(), 'value' => null];
        }

        $coerced = match ($type->getName()) {
            'string' => is_string($value) || is_int($value) || is_float($value) ? (string) $value : null,
            'int' => is_int($value) ? $value : (is_string($value) && preg_match('/^-?\d{1,9}$/', $value) === 1 ? (int) $value : null),
            'float' => is_int($value) || is_float($value) ? (float) $value : (is_string($value) && is_numeric($value) ? (float) $value : null),
            'bool' => is_bool($value) ? $value : (in_array($value, ['1', 1, 'true'], true) ? true : (in_array($value, ['0', 0, 'false', ''], true) ? false : null)),
            'array' => is_array($value) ? $value : null,
            default => self::isBackedEnum($type->getName()) ? self::enumFrom($type->getName(), $value) : $value,
        };

        $handled = in_array($type->getName(), ['string', 'int', 'float', 'bool', 'array'], true) || self::isBackedEnum($type->getName());

        return ['handled' => $handled, 'ok' => ! $handled || $coerced !== null, 'value' => $coerced];
    }

    private static function isBackedEnum(string $type): bool
    {
        return enum_exists($type) && is_subclass_of($type, BackedEnum::class);
    }

    private static function enumFrom(string $type, mixed $value): ?BackedEnum
    {
        if ($value instanceof $type && $value instanceof BackedEnum) {
            return $value;
        }

        /** @var class-string<BackedEnum> $type */
        return is_string($value) || is_int($value) ? $type::tryFrom($value) : null;
    }
}
