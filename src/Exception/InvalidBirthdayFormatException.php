<?php

declare(strict_types=1);

namespace App\Exception;

final class InvalidBirthdayFormatException extends \DomainException
{
    public static function forDate(string $rawDate): self
    {
        return new self(sprintf('Fecha inválida: "%s". Formato esperado: dd/mm/yyyy.', $rawDate));
    }
}
