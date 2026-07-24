<?php

declare(strict_types=1);

namespace App\Exception;

final class BirthdayNotFoundException extends \DomainException
{
    public static function forName(string $name): self
    {
        return new self(sprintf('No hay un cumpleaños registrado para "%s".', $name));
    }
}
