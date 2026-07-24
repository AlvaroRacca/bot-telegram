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
    description: 'Revisa quién cumple años mañana y envía el recordatorio por Telegram.',
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

        $birthdays = $this->birthdayService->getBirthdaysForTomorrow();

        if ($birthdays === []) {
            $io->comment('No hay cumpleaños mañana. No se envía nada.');

            return Command::SUCCESS;    
            
        }

        $this->birthdayService->notifyTomorrowBirthdays();

        $io->success(sprintf(
            'Recordatorio enviado por Telegram. Mañana cumple: %s',
            implode(', ', array_map(static fn (Birthday $birthday): string => $birthday->getName(), $birthdays)),
        ));

        return Command::SUCCESS;
    }
}
