<?php

declare(strict_types=1);

namespace App\Service\Telegram;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Único punto de contacto con la API HTTP de Telegram.
 * No interpreta comandos ni contiene lógica de negocio: solo envía y consulta.
 */
final class TelegramService
{
    private const string API_BASE_URL = 'https://api.telegram.org';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire(env: 'TELEGRAM_BOT_TOKEN')]
        private readonly string $botToken,
        #[Autowire(env: 'TELEGRAM_CHAT_ID')]
        private readonly string $chatId,
    ) {
    }

    /**
     * Envía un mensaje de texto plano al chat configurado en TELEGRAM_CHAT_ID.
     *
     * @throws TransportExceptionInterface si no se pudo conectar con la API de Telegram
     * @throws \RuntimeException           si Telegram respondió con un error (token/chat inválido, etc.)
     */
    public function sendMessage(string $message): void
    {
        $response = $this->httpClient->request(
            'POST',
            sprintf('%s/bot%s/sendMessage', self::API_BASE_URL, $this->botToken),
            [
                'json' => [
                    'chat_id' => $this->chatId,
                    'text' => $message,
                ],
            ],
        );

        $this->assertOk($response->getStatusCode(), $response->getContent(false));
    }

    /**
     * Consulta updates nuevos desde $offset (inclusive).
     * Telegram no reenvía updates ya confirmados una vez que se pidió un $offset mayor a su update_id.
     *
     * $longPollSeconds > 0 activa long polling: Telegram mantiene la conexión abierta
     * hasta ese máximo de segundos y responde apenas llega un update nuevo (o al agotarse
     * el tiempo, con lista vacía). Así se logra respuesta casi instantánea sin sondear
     * en loop ajustado ni usar Webhooks.
     *
     * @return array<int, array<string, mixed>> lista cruda de updates devueltos por Telegram
     *
     * @throws TransportExceptionInterface si no se pudo conectar con la API de Telegram
     * @throws \RuntimeException           si Telegram respondió con un error
     */
    public function getUpdates(int $offset, int $longPollSeconds = 0): array
    {
        $response = $this->httpClient->request(
            'GET',
            sprintf('%s/bot%s/getUpdates', self::API_BASE_URL, $this->botToken),
            [
                'query' => [
                    'offset' => $offset,
                    'timeout' => $longPollSeconds,
                ],
                'timeout' => $longPollSeconds + 10,
                'max_duration' => $longPollSeconds + 10,
            ],
        );

        $statusCode = $response->getStatusCode();
        $content = $response->getContent(false);

        $this->assertOk($statusCode, $content);

        /** @var array{ok: bool, result: array<int, array<string, mixed>>} $decoded */
        $decoded = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);

        return $decoded['result'];
    }

    private function assertOk(int $statusCode, string $content): void
    {
        if ($statusCode !== 200) {
            throw new \RuntimeException(sprintf(
                'Telegram API respondió con código %d: %s',
                $statusCode,
                $content,
            ));
        }
    }
}
