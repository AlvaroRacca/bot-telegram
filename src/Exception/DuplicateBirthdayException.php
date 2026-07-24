<?php

declare(strict_types=1);

namespace App\Exception;

final class DuplicateBirthdayException extends \DomainException
{
    public static function forName(string $name): self
    {
        return new self(sprintf('Ya existe un cumpleaños registrado para "%s".', $name));
    }
}
