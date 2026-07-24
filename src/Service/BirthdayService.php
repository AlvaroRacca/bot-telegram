<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Birthday;
use App\Exception\DuplicateBirthdayException;
use App\Exception\InvalidBirthdayFormatException;
use App\Repository\BirthdayRepository;
use App\Service\Telegram\TelegramService;

final class BirthdayService
{
    private const string INPUT_DATE_FORMAT = 'd/m/Y';

    public function __construct(
        private readonly BirthdayRepository $birthdayRepository,
        private readonly TelegramService $telegramService,
    ) {
    }

    /**
     * Cumpleaños habilitados que caen mañana.
     *
     * @return Birthday[]
     */
    public function getBirthdaysForTomorrow(): array
    {
        return $this->birthdayRepository->findEnabledBirthdaysOn(new \DateTimeImmutable('tomorrow'));
    }

    /**
     * Envía por Telegram el recordatorio de los cumpleaños de mañana.
     * No envía nada si no hay ninguno.
     */
    public function notifyTomorrowBirthdays(): void
    {
        $birthdays = $this->getBirthdaysForTomorrow();

        if ($birthdays === []) {
            return;
        }

        $this->telegramService->sendMessage($this->buildReminderMessage($birthdays));
    }

    /**
     * Texto final listo para enviar por Telegram con todos los cumpleaños registrados.
     */
    public function listAllMessage(): string
    {
        $birthdays = $this->birthdayRepository->findAllOrdered();

        if ($birthdays === []) {
            return 'No hay cumpleaños registrados.';
        }

        $lines = ['🎂 Cumpleaños registrados', ''];

        foreach ($birthdays as $birthday) {
            $lines[] = sprintf('• %s — %s', $birthday->getName(), $birthday->getBirthDate()->format('d/m'));
        }

        return implode("\n", $lines);
    }

    /**
     * Registra un cumpleaños a partir de una fecha en formato dd/mm/yyyy.
     *
     * @throws InvalidBirthdayFormatException si $rawDate no respeta el formato esperado
     * @throws DuplicateBirthdayException     si ya existe un registro con ese nombre
     */
    public function addBirthdayFromRawDate(string $name, string $rawDate): Birthday
    {
        $birthDate = \DateTimeImmutable::createFromFormat(self::INPUT_DATE_FORMAT, $rawDate);

        if ($birthDate === false || $birthDate->format(self::INPUT_DATE_FORMAT) !== $rawDate) {
            throw InvalidBirthdayFormatException::forDate($rawDate);
        }

        if ($this->birthdayRepository->findByName($name) !== null) {
            throw DuplicateBirthdayException::forName($name);
        }

        $birthday = new Birthday($name, $birthDate);
        $this->birthdayRepository->save($birthday);

        return $birthday;
    }

    /**
     * @param Birthday[] $birthdays
     */
    private function buildReminderMessage(array $birthdays): string
    {
        $lines = [
            '🎂 Recordatorio',
            '',
            'Mañana cumple:',
            '',
        ];

        foreach ($birthdays as $birthday) {
            $lines[] = sprintf('• %s', $birthday->getName());
        }

        return implode("\n", $lines);
    }
}
