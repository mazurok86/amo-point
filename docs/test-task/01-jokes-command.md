# Этап 1 — Команда `jokes:fetch` + JSON-роут

## API

`https://official-joke-api.appspot.com/random_joke` — возвращает один объект:
```json
{ "id": 123, "type": "general", "setup": "...", "punchline": "..." }
```

## Файлы

```
app/Console/Commands/FetchJokes.php
app/Models/Joke.php
app/Http/Controllers/Api/JokeController.php
app/Http/Resources/JokeResource.php
database/migrations/YYYY_MM_DD_HHMMSS_create_jokes_table.php
database/factories/JokeFactory.php
routes/api.php                            # новый файл
tests/Feature/FetchJokesCommandTest.php
tests/Feature/JokesApiTest.php
```

Изменить:
- `bootstrap/app.php` — подключить `routes/api.php` через `withRouting(api: ...)`, добавить `withSchedule(...)` с `command('jokes:fetch')->everyFiveMinutes()->withoutOverlapping()`.

## Миграция `jokes`

| поле | тип | заметка |
|------|-----|---------|
| id | bigIncrements | |
| external_id | unsignedBigInteger, unique | id из API — нужен для дедупликации |
| type | string(50), index | general / programming / ... |
| setup | text | |
| punchline | text | |
| fetched_at | timestamp | время последнего успешного апдейта |
| timestamps | | |

## Команда `jokes:fetch`

- HTTP-клиент — `Http::timeout(5)->retry(3, 200)->get(...)`.
- Запись через `Joke::updateOrCreate(['external_id' => $id], [...])` — идемпотентно.
- Логировать через `Log::info('jokes:fetch ok', ['external_id' => $id])` (видно в `php artisan pail`).
- Exit code: `self::SUCCESS` / `self::FAILURE` (для cron-мониторинга).

## Расписание

В Laravel 11+ Kernel’а нет — расписание объявляется в `bootstrap/app.php`:
```php
->withSchedule(function (Schedule $schedule) {
    $schedule->command('jokes:fetch')
        ->everyFiveMinutes()
        ->withoutOverlapping()
        ->onOneServer();
})
```
В README указать запуск:
- dev: `php artisan schedule:work` (или `composer dev` где это запускается параллельно с queue/serve/vite — добавим строку в `composer.json` `dev`).
- prod: системный cron `* * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1`.

## Роут

`routes/api.php`:
```php
Route::get('/jokes', [JokeController::class, 'index']);
```

`JokeController::index`:
```php
return JokeResource::collection(
    Joke::latest('id')->paginate($request->integer('per_page', 50))
);
```

`JokeResource` — отдаёт `id`, `external_id`, `type`, `setup`, `punchline`, `fetched_at`.

## Тесты

- `FetchJokesCommandTest`:
  - `Http::fake([...])` → `artisan('jokes:fetch')->assertSuccessful()` → `assertDatabaseHas('jokes', [...])`.
  - Повторный запуск с тем же `external_id` → запись одна.
  - Сетевой fail → команда возвращает `FAILURE` и не падает.
- `JokesApiTest`:
  - Сидируем 3 шутки фабрикой → `getJson('/api/jokes')` → структура `data[].setup` и т.д.
  - Пагинация: `?per_page=2` → 2 элемента в `data`.

## Definition of Done

- [x] Миграция применяется и откатывается (`migrate:fresh`, `migrate:rollback`).
- [ ] `php artisan jokes:fetch` создаёт запись (с включённым интернетом). _— проверить вручную._
- [x] `GET /api/jokes` возвращает JSON-список (проверено `route:list` + тестами).
- [x] Расписание зарегистрировано (`*/5 * * * * php artisan jokes:fetch` в `schedule:list`).
- [x] Все тесты зелёные (7/7 в фильтре, 32/32 во всём наборе).
- [x] `./vendor/bin/pint --test` без замечаний.

## Альтернативы (для README/защиты)

| Альтернатива | Почему не выбрана |
|---|---|
| Cron-задача напрямую через системный crontab без `schedule()` | Теряем `withoutOverlapping`, мониторинг и единое место конфигурации. |
| Очередь + ScheduledJob | Излишне для одной HTTP-операции в 5 минут. Расписание + синхронная команда проще. |
| Хранить «сырой» JSON в JSONB | API стабильный и плоский, нормализованные колонки удобнее для выборки/индексации. |
| `firstOrCreate` вместо `updateOrCreate` | API может отдать тот же id с обновлённым текстом — лучше держать актуальную версию. |
