<?php

declare(strict_types=1);

namespace App\Service\Telegram;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Persiste entre corridas de `app:telegram:poll` el paso pendiente de una
 * conversación guiada (ej. /agregar paso a paso). Bot de un solo usuario:
 * alcanza con un único estado global, no hace falta indexar por chat.
 */
final class TelegramConversationStateStorage
{
    private readonly string $filePath;

    public function __construct(
        #[Autowire(param: 'kernel.project_dir')]
        string $projectDir,
    ) {
        $this->filePath = $projectDir.'/var/telegram-conversation-state.json';
    }

    /**
     * @return array<string, string>|null
     */
    public function get(): ?array
    {
        if (!is_file($this->filePath)) {
            return null;
        }

        $decoded = json_decode(file_get_contents($this->filePath), true);

        return \is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, string> $state
     */
    public function save(array $state): void
    {
        file_put_contents($this->filePath, json_encode($state, \JSON_THROW_ON_ERROR));
    }

    public function clear(): void
    {
        if (is_file($this->filePath)) {
            unlink($this->filePath);
        }
    }
}
