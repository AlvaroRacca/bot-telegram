<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Telegram\TelegramService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:test',
    description: 'Envía un mensaje de prueba por Telegram para verificar que el bot funciona.',
)]
final class TestCommand extends Command
{
    private const string TEST_MESSAGE = '🚀 Birthday Reminder Bot funcionando correctamente.';

    public function __construct(private readonly TelegramService $telegramService)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->telegramService->sendMessage(self::TEST_MESSAGE);

        $output->writeln('Mensaje enviado correctamente.');

        return Command::SUCCESS;
    }
}
