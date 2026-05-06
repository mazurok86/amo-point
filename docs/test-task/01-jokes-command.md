# Задание 1 — `jokes:fetch` + `GET /api/jokes`

## API

`https://official-joke-api.appspot.com/random_joke` → `{ "id": 123, "type": "general", "setup": "...", "punchline": "..." }`.

## Схема `jokes`

| поле | тип | заметка |
|---|---|---|
| id | bigIncrements | |
| external_id | unsignedBigInteger, unique | id из API — для дедупликации |
| type | string(50), index | general / programming / ... |
| setup | text | |
| punchline | text | |
| fetched_at | timestamp | время последнего успешного fetch |
| timestamps | | |

## Команда

`Http::timeout(5)->retry(3, 200)->get(...)` → `Joke::updateOrCreate(['external_id' => $id], ...)`. Идемпотентно: повторный fetch той же шутки обновляет одну запись, дубля не появится. Exit code `SUCCESS`/`FAILURE` для cron-мониторинга.

## Расписание

В Laravel 11+ Kernel'а нет — расписание объявляется в `bootstrap/app.php`:

```php
$schedule->command('jokes:fetch')->everyFiveMinutes()->withoutOverlapping();
```

## Endpoint

`GET /api/jokes` → `JokeResource::collection(Joke::latest()->paginate(...))`. Параметр `?per_page=N` (1..100, default 50).

## Альтернативы

| Подход | Почему не выбран |
|---|---|
| Системный crontab без `schedule()` | Теряем `withoutOverlapping`, единое место конфигурации, мониторинг. |
| Очередь + ScheduledJob | Излишне для одной HTTP-операции в 5 мин — расписание + синхронная команда проще. |
| Хранить «сырой» JSON в JSONB | API стабильный и плоский — нормализованные колонки удобнее для выборки/индексации. |
| `firstOrCreate` вместо `updateOrCreate` | API может отдать тот же id с обновлённым текстом — лучше держать актуальную версию. |
