<?php

declare(strict_types=1);

namespace App\Service\Telegram;

use App\Exception\BirthdayNotFoundException;
use App\Exception\DuplicateBirthdayException;
use App\Exception\InvalidBirthdayFormatException;
use App\Service\BirthdayService;

/**
 * Interpreta texto de mensajes de Telegram y decide qué operación de BirthdayService ejecutar.
 * No accede a Doctrine ni contiene reglas de negocio: solo rutea, mantiene el estado de
 * conversaciones guiadas (ej. /agregar paso a paso) y traduce resultados/excepciones a
 * texto de respuesta para Telegram.
 */
final class TelegramCommandHandler
{
    private const string ADD_USAGE_MESSAGE = "Formato incorrecto.\n\nUsar:\n\n/agregar Nombre dd/mm";
    private const string DELETE_USAGE_MESSAGE = "Formato incorrecto.\n\nUsar:\n\n/eliminar Nombre";
    private const string INVALID_DATE_REPLY_MESSAGE = 'Formato incorrecto. Mandá la fecha así: dd/mm.';
    private const string STEP_AWAITING_NAME = 'awaiting_name';
    private const string STEP_AWAITING_DATE = 'awaiting_date';
    private const string STEP_AWAITING_DELETE_NAME = 'awaiting_delete_name';
    private const string STEP_AWAITING_DELETE_CONFIRM = 'awaiting_delete_confirm';

    public function __construct(
        private readonly BirthdayService $birthdayService,
        private readonly TelegramConversationStateStorage $conversationState,
    ) {
    }

    /**
     * @return string|null texto de respuesta a enviar, o null si el mensaje no es un comando
     *                      reconocido ni la respuesta a una conversación pendiente
     */
    public function handle(string $messageText): ?string
    {
        $trimmed = trim($messageText);

        if (str_starts_with($trimmed, '/')) {
            $this->conversationState->clear();

            return $this->handleCommand($trimmed);
        }

        return $this->handleConversationReply($trimmed);
    }

    private function handleCommand(string $trimmed): ?string
    {
        $parts = explode(' ', $trimmed);

        return match ($parts[0]) {
            '/listar' => $this->birthdayService->listAllMessage(),
            '/agregar' => $this->handleAdd($parts),
            '/eliminar' => $this->handleDelete($parts),
            default => null,
        };
    }

    /**
     * @param string[] $parts
     */
    private function handleAdd(array $parts): string
    {
        if (\count($parts) === 1) {
            $this->conversationState->save(['step' => self::STEP_AWAITING_NAME]);

            return '¿Cuál es el nombre?';
        }

        if (\count($parts) !== 3) {
            return self::ADD_USAGE_MESSAGE;
        }

        [, $name, $rawDate] = $parts;

        return $this->addBirthday($name, $rawDate, self::ADD_USAGE_MESSAGE);
    }

    /**
     * @param string[] $parts
     */
    private function handleDelete(array $parts): string
    {
        if (\count($parts) === 1) {
            $this->conversationState->save(['step' => self::STEP_AWAITING_DELETE_NAME]);

            return '¿Cuál es el nombre a eliminar?';
        }

        if (\count($parts) !== 2) {
            return self::DELETE_USAGE_MESSAGE;
        }

        [, $name] = $parts;

        return $this->askDeleteConfirmation($name);
    }

    private function handleConversationReply(string $trimmed): ?string
    {
        $state = $this->conversationState->get();

        return match ($state['step'] ?? null) {
            self::STEP_AWAITING_NAME => $this->handleNameReply($trimmed),
            self::STEP_AWAITING_DATE => $this->handleDateReply($trimmed, $state['name']),
            self::STEP_AWAITING_DELETE_NAME => $this->askDeleteConfirmation($trimmed),
            self::STEP_AWAITING_DELETE_CONFIRM => $this->handleDeleteConfirmReply($trimmed, $state['name']),
            default => null,
        };
    }

    private function handleNameReply(string $name): string
    {
        $this->conversationState->save(['step' => self::STEP_AWAITING_DATE, 'name' => $name]);

        return 'Decime la fecha de nacimiento (dd/mm).';
    }

    private function handleDateReply(string $rawDate, string $name): string
    {
        // Ante fecha inválida el estado (awaiting_date + name) queda igual:
        // el usuario reintenta la fecha sin repetir el nombre.
        return $this->addBirthday($name, $rawDate, self::INVALID_DATE_REPLY_MESSAGE);
    }

    private function askDeleteConfirmation(string $name): string
    {
        if (!$this->birthdayService->birthdayExists($name)) {
            $this->conversationState->clear();

            return sprintf('No hay un cumpleaños registrado para %s.', $name);
        }

        $this->conversationState->save(['step' => self::STEP_AWAITING_DELETE_CONFIRM, 'name' => $name]);

        return sprintf('¿Confirmás eliminar a %s? (sí/no)', $name);
    }

    private function handleDeleteConfirmReply(string $reply, string $name): string
    {
        $normalized = mb_strtolower(trim($reply));

        if (!\in_array($normalized, ['si', 'sí', 'no'], true)) {
            return 'Respondé "sí" o "no".';
        }

        $this->conversationState->clear();

        if ($normalized === 'no') {
            return 'Cancelado.';
        }

        try {
            $this->birthdayService->deleteBirthdayByName($name);
        } catch (BirthdayNotFoundException $exception) {
            return $exception->getMessage();
        }

        return sprintf('🗑️ Cumpleaños de %s eliminado.', $name);
    }

    private function addBirthday(string $name, string $rawDate, string $invalidFormatMessage): string
    {
        try {
            $this->birthdayService->addBirthdayFromRawDate($name, $rawDate);
        } catch (InvalidBirthdayFormatException) {
            return $invalidFormatMessage;
        } catch (DuplicateBirthdayException) {
            $this->conversationState->clear();

            return sprintf('⚠️ Ya existe un cumpleaños registrado para %s.', $name);
        }

        $this->conversationState->clear();

        return '✅ Cumpleaños agregado correctamente.';
    }
}
