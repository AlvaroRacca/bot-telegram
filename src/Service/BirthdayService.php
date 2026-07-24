<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Birthday;
use App\Exception\BirthdayNotFoundException;
use App\Exception\DuplicateBirthdayException;
use App\Exception\InvalidBirthdayFormatException;
use App\Repository\BirthdayRepository;
use App\Service\Telegram\TelegramService;

final class BirthdayService
{
    private const string INPUT_DATE_FORMAT = 'd/m';

    /**
     * Año placeholder para almacenar el nacimiento: se guarda como fecha completa
     * (la columna es DATE) pero el año nunca se usa ni se pide al usuario —
     * findEnabledBirthdaysOn compara solo mes/día. Bisiesto para no perder 29/02.
     */
    private const int PLACEHOLDER_YEAR = 2000;

    public function __construct(
        private readonly BirthdayRepository $birthdayRepository,
        private readonly TelegramService $telegramService,
    ) {
    }

    /**
     * Cumpleaños habilitados que caen hoy.
     *
     * @return Birthday[]
     */
    public function getBirthdaysForToday(): array
    {
        return $this->birthdayRepository->findEnabledBirthdaysOn(new \DateTimeImmutable('today'));
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
     * Envía por Telegram el recordatorio de los cumpleaños de hoy y de mañana.
     * No envía nada si no hay ninguno en ninguno de los dos días.
     */
    public function notifyDailyReminders(): void
    {
        $today = $this->getBirthdaysForToday();
        $tomorrow = $this->getBirthdaysForTomorrow();

        if ($today === [] && $tomorrow === []) {
            return;
        }

        $this->telegramService->sendMessage($this->buildReminderMessage($today, $tomorrow));
    }

    public function birthdayExists(string $name): bool
    {
        return $this->birthdayRepository->findByName($name) !== null;
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
     * Registra un cumpleaños a partir de una fecha en formato dd/mm (sin año).
     *
     * @throws InvalidBirthdayFormatException si $rawDate no respeta el formato esperado
     * @throws DuplicateBirthdayException     si ya existe un registro con ese nombre
     */
    public function addBirthdayFromRawDate(string $name, string $rawDate): Birthday
    {
        if (preg_match('#^(\d{2})/(\d{2})$#', $rawDate, $matches) !== 1) {
            throw InvalidBirthdayFormatException::forDate($rawDate);
        }

        [, $day, $month] = $matches;

        if (!checkdate((int) $month, (int) $day, self::PLACEHOLDER_YEAR)) {
            throw InvalidBirthdayFormatException::forDate($rawDate);
        }

        if ($this->birthdayRepository->findByName($name) !== null) {
            throw DuplicateBirthdayException::forName($name);
        }

        $birthDate = (new \DateTimeImmutable())
            ->setDate(self::PLACEHOLDER_YEAR, (int) $month, (int) $day)
            ->setTime(0, 0, 0);

        $birthday = new Birthday($name, $birthDate);
        $this->birthdayRepository->save($birthday);

        return $birthday;
    }

    /**
     * @throws BirthdayNotFoundException si no existe un registro con ese nombre
     */
    public function deleteBirthdayByName(string $name): void
    {
        $birthday = $this->birthdayRepository->findByName($name);

        if ($birthday === null) {
            throw BirthdayNotFoundException::forName($name);
        }

        $this->birthdayRepository->delete($birthday);
    }

    /**
     * @param Birthday[] $today
     * @param Birthday[] $tomorrow
     */
    private function buildReminderMessage(array $today, array $tomorrow): string
    {
        $lines = ['🎂 Recordatorio'];

        if ($today !== []) {
            array_push($lines, '', 'Hoy cumple:', '');

            foreach ($today as $birthday) {
                $lines[] = sprintf('• %s', $birthday->getName());
            }
        }

        if ($tomorrow !== []) {
            array_push($lines, '', 'Mañana cumple:', '');

            foreach ($tomorrow as $birthday) {
                $lines[] = sprintf('• %s', $birthday->getName());
            }
        }

        return implode("\n", $lines);
    }
}
