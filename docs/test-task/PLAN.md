# Тестовое задание PHP-разработчик — план реализации

Реализуется в репозитории `amo-point` (Laravel 13 + Breeze Blade + MySQL + Vite).

## Состав ТЗ

1. **Обязательное.** Консольная команда раз в 5 минут получает данные от API (`https://official-joke-api.appspot.com/random_joke`) и сохраняет в БД. Route отдаёт массив записей в JSON.
2. **Обязательное.** JS-сниппет/файл для страницы `http://test.amopoint-dev.ru/testzz/testlist.html`: при смене значения select `name="type"` отображаются только поля, в `name` которых содержится выбранное значение.
3. **Бонус.** Счётчик посещений: JS-коллектор (ip, город, устройство) + бэкенд (БД) + страница статистики с графиками (по часам — bar, по городам — pie) под авторизацией.

## Этапы

| # | Этап | Файл с деталями | Статус |
|---|------|-----------------|--------|
| 1 | Команда `jokes:fetch` + JSON-роут | [01-jokes-command.md](01-jokes-command.md) | ☑ |
| 2 | JS — динамическая видимость полей | [02-dynamic-fields.md](02-dynamic-fields.md) | ☑ |
| 3a | Счётчик: бэкенд приёма (`POST /api/visits`) | [03-visit-counter.md](03-visit-counter.md) | ☑ |
| 3b | Счётчик: JS-коллектор `public/track.js` | [03-visit-counter.md](03-visit-counter.md) | ☑ |
| 3c | Счётчик: страница `/stats` под auth | [03-visit-counter.md](03-visit-counter.md) | ☐ |
| 4 | README для проверяющего, финальная проверка (`pint`, `test`, `npm run build`) | — | ☐ |

Этапы реализуем последовательно. После каждого — рабочий вертикальный срез + тесты, отмечаем галочкой здесь.

## Решения общего уровня

- **Хранилище для счётчика посещений** — основная MySQL проекта (та же, что и `users`/`jokes`). ТЗ дословно: «БД(sqllite или другой на выбор)» — MySQL разрешена явно. Изначально планировали отдельное sqlite-соединение «для изоляции», но накладные (отдельный конфиг, multi-connection танцы в RefreshDatabase) перевесили умозрительную пользу. См. подробности в `03-visit-counter.md` («Хранилище»).
- **API-роуты** — добавим `routes/api.php` и подключим в `bootstrap/app.php` через `withRouting(api: ...)`. Префикс `/api`, без CSRF, отдельный rate-limit.
- **Авторизация страницы статистики** — стандартный `auth` middleware от Breeze (уже установлен).
- **Тесты** — PHPUnit (как настроено в `phpunit.xml`), SQLite `:memory:`. Для каждого нового публичного роута/команды — минимум один feature-тест.
- **Стиль** — `./vendor/bin/pint` перед каждым коммитом.
- **Документация решений** — после каждого этапа в его файле фиксируем итоговый алгоритм + отвергнутые альтернативы (ТЗ это особо отмечает как плюс).

## Соглашения по коммитам

Conventional commits, как уже заведено в репозитории:
- `feat: add jokes:fetch command`
- `feat: add /api/jokes endpoint`
- `feat(stats): add visit tracker JS collector`
- `test: cover jokes:fetch with Http::fake`
