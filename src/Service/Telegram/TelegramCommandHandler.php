<?php

declare(strict_types=1);

namespace App\Service\Telegram;

use App\Exception\DuplicateBirthdayException;
use App\Exception\InvalidBirthdayFormatException;
use App\Service\BirthdayService;

/**
 * Interpreta texto de mensajes de Telegram y decide qué operación de BirthdayService ejecutar.
 * No accede a Doctrine ni contiene reglas de negocio: solo rutea y traduce resultados/excepciones
 * a texto de respuesta para Telegram.
 */
final class TelegramCommandHandler
{
    private const string ADD_USAGE_MESSAGE = "Formato incorrecto.\n\nUsar:\n\n/agregar Nombre dd/mm/yyyy";

    public function __construct(private readonly BirthdayService $birthdayService)
    {
    }

    /**
     * @return string|null texto de respuesta a enviar, o null si el mensaje no es un comando reconocido
     */
    public function handle(string $messageText): ?string
    {
        $parts = explode(' ', trim($messageText));
        $command = $parts[0] ?? '';

        return match ($command) {
            '/listar' => $this->birthdayService->listAllMessage(),
            '/agregar' => $this->handleAdd($parts),
            default => null,
        };
    }

    /**
     * @param string[] $parts
     */
    private function handleAdd(array $parts): string
    {
        if (\count($parts) !== 3) {
            return self::ADD_USAGE_MESSAGE;
        }

        [, $name, $rawDate] = $parts;

        try {
            $this->birthdayService->addBirthdayFromRawDate($name, $rawDate);
        } catch (InvalidBirthdayFormatException) {
            return self::ADD_USAGE_MESSAGE;
        } catch (DuplicateBirthdayException) {
            return sprintf('⚠️ Ya existe un cumpleaños registrado para %s.', $name);
        }

        return '✅ Cumpleaños agregado correctamente.';
    }
}
