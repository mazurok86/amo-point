# Этап 3 (бонус) — Счётчик посещений

## Архитектура

Два компонента:
1. **JS-коллектор** — `<script async src="/track.js">` подключается к произвольному сайту, собирает данные и отправляет на наш бэкенд.
2. **Бэкенд** — приём, обогащение (UA-парсинг), хранение в основной MySQL проекта, страница статистики под Breeze auth.

## Хранилище

Таблица `visits` живёт в **основной MySQL** проекта (та же БД, что и `users`/`jokes`). ТЗ дословно: «БД(sqllite или другой на выбор)» — MySQL разрешена явно.

Раньше планировали отдельное sqlite-соединение «для изоляции», но для тестового это **умозрительная польза** ценой реальных накладных (отдельный конфиг, env-переменная, файл в `.gitignore`, multi-connection танцы в RefreshDatabase). Симметричнее с `jokes`, проще для проверяющего: поднял MySQL — всё работает.

Тесты — sqlite `:memory:` для всего (как в `phpunit.xml` с самого начала).

## Geo

Резолвинг IP → город сейчас **НЕ реализован**. Колонки `country`/`city` в схеме сохранены — этого требует ТЗ («собирать город» + «pie chart по городам»). Для реальных визитов значения `null`. Для демо страницы статистики — `DemoVisitsSeeder` заполняет визиты разными городами.

**Почему отложили:** на localhost (`request->ip() === '127.0.0.1'`) внешний резолвер всё равно отдаёт null. Стоимость интеграции в проде — отдельная задача (см. альтернативы внизу). Не блокирует ни приёмку данных, ни UI статистики — резолвер позже подменяется одной сервис-биндой без правки контроллера.

## Файлы (полный список)

```
public/track.js                                       # JS-коллектор (3b)
public/track-demo.html                                # локальная страница для smoke-теста (3b, не часть деливерабла)
app/Models/Visit.php                                  # 3a
app/Http/Controllers/Api/VisitController.php          # POST /api/visits (3a)
app/Http/Controllers/StatsController.php              # GET /stats (3c)
app/Http/Requests/StoreVisitRequest.php               # 3a
app/Services/UserAgentParser.php                      # обёртка над jenssegers/agent (3a)
database/migrations/..._create_visits_table.php       # 3a
database/factories/VisitFactory.php                   # 3a
database/seeders/DemoVisitsSeeder.php                 # 3a
resources/views/stats/index.blade.php                 # 3c
routes/web.php                                        # /stats (auth) — edit (3c)
routes/api.php                                        # POST /api/visits — edit (3a)
bootstrap/app.php                                     # shouldRenderJsonWhen для api/* — edit (3a)
tests/Feature/Visits/StoreVisitTest.php               # 3a
tests/Feature/Visits/StatsPageTest.php                # 3c
```

Зависимости (composer):
- `jenssegers/agent` — UA parser (auto-discovery, без manual service-provider).

JS:
- Chart.js — через CDN на странице `/stats`. Без зависимости в `package.json`.

## План на 3 коммита

| Шаг | Что | Статус |
|---|---|---|
| **3a** | Бэкенд приёма: миграция `visits`, модель/фабрика, `StoreVisitRequest`, `UserAgentParser`, `VisitController`, `POST /api/visits` с throttle, `DemoVisitsSeeder`, тесты | ✅ ef86f6d |
| **3b** | JS-коллектор `public/track.js` (sendBeacon + URLSearchParams + UUID v4 в localStorage + try/catch) + `track-demo.html` для smoke-теста | ✅ |
| **3c** | Страница `/stats` под auth: `StatsController`, Blade с двумя `<canvas>`, Chart.js по CDN, тесты | ⌛ |

## Миграция `visits`

| поле | тип | заметка |
|------|-----|---------|
| id | bigIncrements | |
| host | string(255), index | хост из `page_url`, для multi-site фильтрации |
| visitor_uid | string(36), index | UUID v4 из localStorage; fallback на сервере если не пришёл |
| ip | string(45) | поддержка IPv6 |
| country | string(2), nullable | _не заполняется в текущей итерации; задел_ |
| city | string(120), nullable | _не заполняется (real); сидер использует разные города для демо_ |
| device | string(20), nullable | desktop / tablet / mobile / bot |
| browser | string(50), nullable | |
| os | string(50), nullable | |
| page_url | text | |
| referrer | text, nullable | |
| created_at | timestamp, index | для часовой группировки |

`updated_at` не нужен — события неизменяемы. Миграция: `$table->timestamp('created_at')->index()`, без `timestamps()`. На модели `public const UPDATED_AT = null` — Eloquent сам управляет `created_at`.

## JS-коллектор `public/track.js`

Подключение: `<script async src="https://our-host/track.js"></script>`. «Drop-in», без зависимостей.

**Endpoint резолвится автоматически** — из `<script src>`-атрибута собственного тега через `document.currentScript`, с fallback по поиску `<script src*=track.js>`. Получается `<script-origin>/api/visits` без необходимости в `data-endpoint` или ручной конфигурации.

Собирает:
- `visitor_uid` — UUID v4 в `localStorage` (ключ `__amo_visit_uid`, создаётся при первом заходе). Генератор: `crypto.randomUUID()` для современных браузеров, `Math.random()`-fallback для старых.
- `page_url` — `location.href`.
- `referrer` — `document.referrer` (опускается если пусто).
- `user_agent` — `navigator.userAgent`.

Что **не** собираем на клиенте:
- **IP** — берётся на сервере из `$request->ip()`.

Отправка:
- `navigator.sendBeacon(url, URLSearchParams)` — form-encoded body. **CORS-safelisted content type → preflight OPTIONS НЕ срабатывает**, никаких CORS-проблем с произвольных хостов.
- Fallback (`sendBeacon` нет или вернул false): `fetch(url, {method: 'POST', body: URLSearchParams, keepalive: true})`. Тот же form-encoded → preflight тоже не нужен.
- Весь файл в `try/catch` — любая внутренняя ошибка коллектора не должна валить хост-страницу.

**localStorage недоступен** (private mode, политика хост-сайта) → `visitor_uid` не отправляется → на сервере fallback ключ `md5(ip + ua + час)`.

## Backend `POST /api/visits`

- Маршрут в `routes/api.php`.
- `throttle:60,1` per IP — защита от ботов.
- CSRF не применяется (api группа).
- CORS — дефолтный `HandleCors` в Laravel 11+ (`paths: ['api/*']`, `allowed_origins: ['*']`) — open by default, ничего публиковать не надо.
- `StoreVisitRequest` валидирует:
  - `visitor_uid` — `nullable|uuid`. Если null — на сервере fallback ключ `md5(ip + ua + дата+час)`.
  - `page_url` — `required|url|max:2048`.
  - `referrer` — `nullable|url|max:2048`.
  - `user_agent` — `nullable|string|max:1024`.
- В контроллере:
  1. Парсим UA → `device/browser/os` через `UserAgentParser`.
  2. Извлекаем `host` из `page_url` через `parse_url(..., PHP_URL_HOST)`.
  3. Берём `ip` из `$request->ip()`.
  4. `Visit::create([...])`.
  5. Отвечаем `204 No Content` (sendBeacon ответ не читает; для fetch-fallback — лёгкий статус).

## `GET /stats` (3c — впереди)

Blade-страница под `auth` middleware:
- Простая форма: `<input type="date" name="date">` + `<select name="host">` (уникальные хосты в БД) + кнопка submit. Без JS-фреймворка.
- Bar chart: уникальные визиты по часам за выбранный день/хост:
  ```sql
  SELECT strftime('%H', created_at) AS h, COUNT(DISTINCT visitor_uid) AS c
  FROM visits WHERE date(created_at) = ? AND host = ? GROUP BY h ORDER BY h
  ```
- Pie chart: топ-N городов за тот же период. `LIMIT 10`, остальное в `Other`.
- Данные кладём в `data-*` атрибуты `<canvas>` → инлайн-`<script>` отдаёт их Chart.js.

## Уникальность визита

`COUNT(DISTINCT visitor_uid)` за час. Если клиент не прислал UID — на сервере fallback `md5(ip + ua + дата+час)`. Зафиксировано как осознанный компромисс.

## Демо

`DemoVisitsSeeder` создаёт ~200 визитов с разными `city`/`device`/`host`/timestamps. Запускается отдельно: `php artisan db:seed --class=DemoVisitsSeeder` (не в дефолтном `db:seed`). Чтобы было что показать на `/stats` сразу после установки.

## Тесты

- `StoreVisitTest`:
  - POST с валидным payload → запись в БД с распарсенными device/browser/os, host, ip.
  - Невалидный URL → 422.
  - Throttle: 61-й запрос в минуту → 429.
  - Без `visitor_uid` → fallback md5 в `visitor_uid`.
- `StatsPageTest`:
  - Гость → 302 на `/login`.
  - Авторизованный — 200, страница содержит canvas + правильные данные в data-атрибутах для тестовой выборки.

## Альтернативы (для README)

| Альтернатива | Почему не выбрана |
|---|---|
| **JSON body вместо URLSearchParams в sendBeacon** | `Content-Type: application/json` — non-simple → preflight OPTIONS перед каждым POST. Лишняя точка отказа на CORS. |
| **Очередь `dispatch(new RecordVisit(...))`** | Прирост сложности (queue worker и т.д.). Объём ~1 запрос на визит — синхронно нормально. |
| **Отдельное sqlite-соединение `analytics` для visits** | «Изоляция аналитики» звучит хорошо, но на практике даёт реальные накладные (отдельный конфиг, env-переменная, multi-connection в `RefreshDatabase` — сами наступили на этот баг и откатились) ради умозрительной пользы. ТЗ разрешает MySQL дословно («БД(sqllite или другой)»). Симметричнее с `jokes`. |
| **ApexCharts / ECharts / D3** | Chart.js — наименьший по весу при достаточной функциональности (bar + pie). |
| **Парсинг UA на клиенте через `navigator.userAgentData`** | Не во всех браузерах, не для Safari. Серверный парсер надёжнее. |
| **Реальный geo-резолвинг** (ip-api.com / MaxMind GeoLite2) | На localhost IP всегда `127.0.0.1`, резолвер вернёт null — польза только в проде. Отложили в future scope. Колонки и UI на месте — добавить можно одним сервисом без правки контроллера. |
| **Cookie вместо localStorage для `visitor_uid`** | Cookie сайта-донора шлётся в КАЖДЫЙ его запрос — лишние байты. Cookie с нашего хоста — third-party, блокируется Safari/Firefox/Chrome. localStorage — first-party, не сериализуется в HTTP, не подвержен third-party-блокам. |
| **Tracking pixel** (`<img src="...">` вместо JS+sendBeacon) | Без JS невозможно собрать `visitor_uid`. |
| **CSRF-cookie + Sanctum stateful** | Усложняет кросс-доменное использование (нужен origin allowlist). API-группа без CSRF проще и достаточно. |
| **Без `throttle` на `POST /api/visits`** | Endpoint открыт миру (без auth, без CSRF). Без лимита — бот / зацикленный коллектор / DoS забьют таблицу мусором за секунды. `throttle:60,1` per IP — первая дешёвая планка (Laravel-дефолт для api-группы); легитимный трафик с одного IP редко превышает 60 страниц/мин, NAT крупных офисов в случае нужды легко поднимается. |
| **Throttle по `visitor_uid` вместо IP** | `visitor_uid` генерится клиентом → атакующий выдаёт новый на каждый запрос → throttle бесполезен. IP менее уникален (NAT), но не подделывается тривиально. |

## Definition of Done

### Шаг 3a — БД + бэкенд приёма
- [x] Таблица `visits` живёт в основной MySQL (без отдельных connection-ов / sqlite-файлов).
- [x] Миграция `visits` создаёт схему с индексами на `host` / `visitor_uid` / `created_at`.
- [x] `POST /api/visits` принимает данные, парсит UA через `UserAgentParser` (jenssegers/agent), пишет в БД. Throttle `60,1` включён.
- [x] CORS на `api/*` открыт (дефолт Laravel) — POST с произвольного origin проходит без preflight.
- [x] Validation errors на `api/*` отдаются JSON-ом 422 (через `shouldRenderJsonWhen` в `bootstrap/app.php`), а не HTTP 302 redirect.
- [x] `DemoVisitsSeeder` создаёт ~200 демо-визитов с разными городами, запускается командой.
- [x] Все тесты зелёные (36/36, 225 assertions).
- [x] `./vendor/bin/pint --test` без замечаний.

### Шаг 3b — JS-коллектор
- [x] `public/track.js` — IIFE, без зависимостей, всё в try/catch.
- [x] Endpoint резолвится автоматически из `document.currentScript.src` (+ fallback по поиску `<script src*=track.js>`).
- [x] `visitor_uid` — UUID v4 в `localStorage` (`crypto.randomUUID()` + Math.random fallback).
- [x] Отправка: `sendBeacon(URLSearchParams)` → fetch keepalive fallback.
- [x] CORS-safe (form-encoded body → preflight не срабатывает).
- [x] `public/track-demo.html` — локальная страница для smoke-теста.

### Шаг 3c — Страница статистики
- [ ] `/stats` под `auth`, отображает bar+pie корректно для тестовых данных.
