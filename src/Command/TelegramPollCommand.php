<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Telegram\TelegramCommandHandler;
use App\Service\Telegram\TelegramOffsetStorage;
use App\Service\Telegram\TelegramService;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Consulta getUpdates, procesa los mensajes nuevos y responde.
 *
 * Cómo funciona el offset: cada update de Telegram trae un update_id creciente.
 * Al pedir getUpdates con offset = último update_id + 1, Telegram entiende que
 * todo lo anterior ya fue confirmado y no lo vuelve a enviar. TelegramOffsetStorage
 * guarda ese número en disco para que corridas separadas de este comando no
 * reprocesen mensajes viejos.
 *
 * Sin --loop: una sola consulta y termina (útil para cron o prueba manual).
 * Con --loop: proceso persistente que usa long polling (Telegram mantiene la
 * conexión abierta hasta LONG_POLL_SECONDS esperando updates), respondiendo casi
 * al instante sin Webhooks ni sondeo agresivo. Pensado para correr como worker
 * de larga duración (ej. servicio en Railway).
 */
#[AsCommand(
    name: 'app:telegram:poll',
    description: 'Consulta y procesa los mensajes nuevos recibidos por Telegram.',
)]
final class TelegramPollCommand extends Command
{
    private const int LONG_POLL_SECONDS = 30;
    private const int RETRY_DELAY_SECONDS = 5;

    public function __construct(
        private readonly TelegramService $telegramService,
        private readonly TelegramCommandHandler $commandHandler,
        private readonly TelegramOffsetStorage $offsetStorage,
        private readonly ManagerRegistry $doctrine,
        #[Autowire(env: 'TELEGRAM_CHAT_ID')]
        private readonly string $ownerChatId,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'loop',
            null,
            InputOption::VALUE_NONE,
            'Corre indefinidamente usando long polling en vez de una sola consulta.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$input->getOption('loop')) {
            $this->processBatch($io, longPollSeconds: 0);

            return Command::SUCCESS;
        }

        while (true) {
            try {
                $this->processBatch($io, self::LONG_POLL_SECONDS);
            } catch (\Throwable $exception) {
                // Proceso de larga duración: una conexión MySQL caída por inactividad
                // (u otro error puntual de red/API) no debe tirar abajo el worker entero.
                $io->warning(sprintf('Error procesando updates: %s', $exception->getMessage()));
                $this->doctrine->getManager()->getConnection()->close();
                sleep(self::RETRY_DELAY_SECONDS);
            }
        }
    }

    private function processBatch(SymfonyStyle $io, int $longPollSeconds): void
    {
        $updates = $this->telegramService->getUpdates($this->offsetStorage->getOffset(), $longPollSeconds);

        if ($updates === []) {
            $io->comment('No hay updates nuevos.');

            return;
        }

        $lastUpdateId = null;

        foreach ($updates as $update) {
            $lastUpdateId = $update['update_id'];

            if (!$this->isFromOwner($update)) {
                continue;
            }

            $messageText = $update['message']['text'] ?? null;

            if ($messageText === null) {
                continue;
            }

            $reply = $this->commandHandler->handle($messageText);

            if ($reply !== null) {
                $this->telegramService->sendMessage($reply);
            }
        }

        $this->offsetStorage->saveOffset($lastUpdateId + 1);

        $io->success(sprintf('%d update(s) procesado(s).', \count($updates)));
    }

    /**
     * Ignora mensajes de cualquier chat que no sea el configurado en TELEGRAM_CHAT_ID:
     * es un bot de un solo usuario, no debe ejecutar comandos ajenos.
     *
     * @param array<string, mixed> $update
     */
    private function isFromOwner(array $update): bool
    {
        $chatId = $update['message']['chat']['id'] ?? null;

        return $chatId !== null && (string) $chatId === $this->ownerChatId;
    }
}
