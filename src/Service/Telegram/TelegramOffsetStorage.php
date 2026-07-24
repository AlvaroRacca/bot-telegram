<?php

declare(strict_types=1);

namespace App\Service\Telegram;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Persiste entre corridas de `app:telegram:poll` el último update_id procesado,
 * para no reprocesar updates viejos (ver explicación de offset en TelegramPollCommand).
 */
final class TelegramOffsetStorage
{
    private readonly string $filePath;

    public function __construct(
        #[Autowire(param: 'kernel.project_dir')]
        string $projectDir,
    ) {
        $this->filePath = $projectDir.'/var/telegram-offset.txt';
    }

    public function getOffset(): int
    {
        if (!is_file($this->filePath)) {
            return 0;
        }

        return (int) file_get_contents($this->filePath);
    }

    public function saveOffset(int $offset): void
    {
        file_put_contents($this->filePath, (string) $offset);
    }
}
