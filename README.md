# Birthday Reminder Bot

Envía un mensaje por Telegram un día antes del cumpleaños de cada persona guardada en base de datos.

## Stack

- Symfony 6.4 / PHP 8.3
- Doctrine ORM + MySQL (managed, ej. Railway — no corre local)
- Symfony HttpClient (sin librerías externas de Telegram)
- Docker (PHP 8.3-alpine, solo runtime de la app)

## Arquitectura

```
src/
├── Entity/Birthday.php              # id, name, birthDate, enabled
├── Repository/BirthdayRepository.php # query: cumpleaños por mes/día (ignora año)
├── Service/TelegramService.php      # enviarMensaje() — único punto que habla con la API de Telegram
├── Service/BirthdayService.php      # toda la lógica de negocio (qué es "mañana", armado de mensaje)
└── Command/CheckBirthdaysCommand.php # app:check-birthdays — solo orquesta, sin lógica propia
```

## 1. Configurar `.env`

`.env` (commiteado) solo trae placeholders/defaults — **nunca poner secretos ahí**. Los
valores reales van en `.env.local` (git-ignorado, ya existe con tus datos actuales):

```bash
# .env.local
DATABASE_URL="mysql://usuario:password@host-railway:puerto/db"
TELEGRAM_BOT_TOKEN=123456789:AAExxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
TELEGRAM_CHAT_ID=123456789
```

- `TELEGRAM_BOT_TOKEN`: lo da [@BotFather](https://t.me/BotFather) al crear el bot.
- `TELEGRAM_CHAT_ID`: tu chat id (o el de un grupo/canal). Se obtiene mandándole un mensaje
  al bot y consultando `https://api.telegram.org/bot<TOKEN>/getUpdates`. **Falta completar
  este valor en `.env.local` — sin él el comando no sabe a quién mandarle el mensaje.**
- `DATABASE_URL`: instancia MySQL managed (Railway u otra). No hay servicio MySQL local en
  `docker-compose.yml` — el contenedor `php` se conecta directo a esa DB externa.

## 2. Levantar con Docker

El contenedor sale a internet a través de un proxy corporativo, ya configurado como build
args en `docker-compose.yml` (`http_proxy`/`https_proxy`/`no_proxy`). Si el proxy cambia,
actualizalo ahí.

```bash
docker compose build
docker compose run --rm php composer install
docker compose run --rm php bin/console doctrine:migrations:migrate --no-interaction
```

`doctrine:migrations:migrate` corre contra la DB de Railway definida en `.env.local` — va
a crear la tabla `birthday` ahí.

## 3. Cargar cumpleaños

MVP no trae UI ni comando de alta — se insertan directo (Doctrine fixtures o SQL manual):

```sql
INSERT INTO birthday (name, birth_date, enabled) VALUES ('Sofía', '1998-07-25', 1);
```

## 4. Probar el comando

```bash
docker compose run --rm php bin/console app:check-birthdays
```

Si hay cumpleaños mañana, manda por Telegram:

```
🎂 Recordatorio

Mañana cumple:

• Sofía
• Juan
```

Si no hay ninguno, no manda nada (solo imprime aviso en consola).

## 5. Cron diario

En el host (o dentro del contenedor si corre de forma persistente):

```cron
0 9 * * * docker compose -f /ruta/al/proyecto/docker-compose.yml run --rm php bin/console app:check-birthdays >> /var/log/birthday-reminder.log 2>&1
```

Corre todos los días 9am, revisa quién cumple mañana y notifica.

## Nota

PHP local del entorno de desarrollo es 8.1; `doctrine/doctrine-bundle` 3.x usa constantes
tipadas (feature de PHP 8.3) y rompe fuera de Docker. Por eso todo comando (`composer`,
`bin/console`) corre **dentro del contenedor**, nunca con el PHP del host.
