<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Birthday;
use App\Service\BirthdayService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:check-birthdays',
    description: 'Revisa quién cumple años hoy y mañana, y envía el recordatorio por Telegram.',
)]
final class CheckBirthdaysCommand extends Command
{
    public function __construct(private readonly BirthdayService $birthdayService)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $today = $this->birthdayService->getBirthdaysForToday();
        $tomorrow = $this->birthdayService->getBirthdaysForTomorrow();

        if ($today === [] && $tomorrow === []) {
            $io->comment('No hay cumpleaños hoy ni mañana. No se envía nada.');

            return Command::SUCCESS;
        }

        $this->birthdayService->notifyDailyReminders();

        $io->success(sprintf(
            'Recordatorio enviado por Telegram. Hoy: %s. Mañana: %s.',
            $this->namesOrNone($today),
            $this->namesOrNone($tomorrow),
        ));

        return Command::SUCCESS;
    }

    /**
     * @param Birthday[] $birthdays
     */
    private function namesOrNone(array $birthdays): string
    {
        if ($birthdays === []) {
            return 'ninguno';
        }

        return implode(', ', array_map(static fn (Birthday $birthday): string => $birthday->getName(), $birthdays));
    }
}
