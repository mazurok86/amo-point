# Задание 3 (бонус) — Счётчик посещений

## Архитектура

1. **JS-коллектор** `public/track.js` — `<script async>` подключается к произвольному сайту, отправляет визит на наш endpoint.
2. **Backend** — приём, обогащение (UA-парсинг), хранение в основной MySQL проекта (та же БД, что и `users` / `jokes` — ТЗ дословно: «БД(sqllite или другой на выбор)»).
3. **Дашборд** `/stats` — Blade-страница под Breeze auth с двумя графиками (Chart.js по CDN).

## Схема `visits`

| поле | тип | заметка |
|---|---|---|
| id | bigIncrements | |
| host | string(255), index | хост из `page_url` для multi-site фильтрации |
| visitor_uid | string(36), index | UUID v4 из localStorage; fallback на сервере если не пришёл |
| ip | string(45) | поддержка IPv6 |
| country | string(2), nullable | _не заполняется (см. Geo)_ |
| city | string(120), nullable | _не заполняется (см. Geo); сидер использует разные города_ |
| device | string(20), nullable | desktop / tablet / mobile / bot |
| browser | string(50), nullable | |
| os | string(50), nullable | |
| page_url | text | |
| referrer | text, nullable | |
| created_at | timestamp, index | для часовой группировки |

`updated_at` не нужен — события неизменяемы. На модели `public const UPDATED_AT = null` — Eloquent сам управляет `created_at`.

## JS-коллектор `public/track.js`

`<script async src="https://your-host/track.js">`. Drop-in, без зависимостей.

**Endpoint резолвится автоматически** из собственного `<script src>` через `document.currentScript` (с fallback по поиску `<script src*=track.js>`) → `<script-origin>/api/visits`. Без `data-endpoint` или ручной конфигурации.

Собирает: `visitor_uid` (UUID v4 в `localStorage`, ключ `__amo_visit_uid`; `crypto.randomUUID()` + Math.random fallback), `page_url`, `referrer`, `user_agent`. **IP** берётся на сервере из `$request->ip()`.

Отправка: `navigator.sendBeacon(url, URLSearchParams)` — form-encoded body, **CORS-safelisted → preflight OPTIONS НЕ срабатывает**, никаких CORS-проблем с произвольных хостов. Fallback: `fetch(url, {method, body, keepalive: true})` — тоже без preflight. Весь файл в `try/catch` — ошибка коллектора не валит хост-страницу.

localStorage недоступен → `visitor_uid` не отправляется → сервер генерит fallback `md5(ip + ua + час)`.

## Backend `POST /api/visits`

- `routes/api.php`. Throttle `60,1` per IP.
- CSRF не применяется (api-группа). CORS open by default (Laravel 11+ `HandleCors`, `paths: ['api/*']`, `allowed_origins: ['*']`).
- `StoreVisitRequest` валидирует: `visitor_uid` (uuid|nullable), `page_url` (url, max 2048), `referrer` (url|nullable, max 2048), `user_agent` (string|nullable, max 1024).
- В контроллере: парсим UA через `UserAgentParser` (jenssegers/agent), извлекаем host через `parse_url(..., PHP_URL_HOST)`, пишем `Visit::create([...])`. Ответ `204 No Content`.

`bootstrap/app.php` → `shouldRenderJsonWhen(fn ($r) => $r->is('api/*') || $r->expectsJson())` — иначе валидация на api-роутах редиректит 302 вместо 422.

## Дашборд `GET /stats`

Blade-страница под `auth` middleware. Форма-фильтр (GET): `<input type="date">` + `<select name="host">` + submit. Без JS-фреймворка.

Агрегации **в SQL** — четыре независимых запроса по покрывающему композитному индексу `(host, created_at)`:

1. Totals: `COUNT(*)` + `COUNT(DISTINCT visitor_uid)`.
2. Hourly: `GROUP BY HOUR(created_at)`, `COUNT(DISTINCT visitor_uid)`. В PHP добиваем недостающие часы нулями — bar chart показывает полную 24-часовую ось.
3. Cities: `WHERE city IS NOT NULL GROUP BY city ORDER BY 2 DESC LIMIT 10`.
4. Hosts (для селектора фильтра): `SELECT DISTINCT host`.

Прод-таргет — MySQL, поэтому используется `HOUR()`. Для тестов на sqlite `:memory:` оставлен однострочный fallback на `strftime('%H', ...)` через ветку по `DB::connection()->getDriverName()` — это не «двойная поддержка», а минимальный костыль, чтобы суит оставался зелёным.

Фильтр времени — `whereBetween('created_at', [$start, $end])`, **не** `whereDate(...)`. Последний переписывается в `WHERE DATE(created_at) = ?`, что отключает использование индекса на `created_at`.

Данные → `data-labels` / `data-values` каждого `<canvas>` → инлайн-`<script>` инициализирует Chart.js (UMD-build с jsDelivr CDN, `chart.js@4.4.0`, без `npm i`).

## Уникальность визита

`COUNT(DISTINCT visitor_uid)` за час. Если клиент UID не прислал — на сервере fallback `md5(ip + ua + час)`. Осознанный компромисс.

## Geo (отложено)

Колонки `country`/`city` в схеме сохранены — этого требует ТЗ («собирать город» + pie-chart по городам). Но IP→geo резолвинг не реализован. Для реальных визитов city=null. Демо страницы статистики обеспечивает `DemoVisitsSeeder` с разнообразными городами.

**Причина:** на localhost IP всегда `127.0.0.1` → внешний резолвер всё равно отдаёт null. Польза только в проде. Резолвер позже подменяется одной сервис-биндой без правки контроллера.

## Альтернативы

| Подход | Почему не выбран |
|---|---|
| **JSON body вместо URLSearchParams в sendBeacon** | `Content-Type: application/json` — non-simple → preflight OPTIONS перед каждым POST. Лишняя точка отказа на CORS. |
| **Очередь `dispatch(new RecordVisit)`** | Прирост сложности (queue worker и т.д.). Объём ~1 запрос на визит — синхронно нормально. |
| **Отдельное sqlite-соединение `analytics` для visits** | «Изоляция аналитики» звучит хорошо, но даёт реальные накладные (отдельный конфиг, env-переменная, multi-connection-танцы в `RefreshDatabase`) ради умозрительной пользы. ТЗ разрешает MySQL дословно. Симметричнее с `jokes`. |
| **ApexCharts / ECharts / D3** | Chart.js — наименьший по весу при достаточной функциональности (bar + pie). |
| **Парсинг UA на клиенте через `navigator.userAgentData`** | Не во всех браузерах, не для Safari. Серверный парсер надёжнее. |
| **Реальный geo-резолвинг** (ip-api.com / MaxMind GeoLite2) | На localhost IP → `127.0.0.1`, резолвер вернёт null. Польза только в проде. UI и колонки на месте — добавить можно одним сервисом без правки контроллера. |
| **Cookie вместо localStorage для `visitor_uid`** | Cookie сайта-донора шлётся в КАЖДЫЙ его HTTP-запрос — лишние байты. Cookie с нашего хоста — third-party, блокируется Safari/Firefox/Chrome. localStorage — first-party, не сериализуется в HTTP, не подвержен third-party-блокам. |
| **Tracking pixel** (`<img src="...">`) | Без JS невозможно собрать `visitor_uid`. |
| **CSRF-cookie + Sanctum stateful** | Усложняет кросс-доменное использование (нужен origin allowlist). API-группа без CSRF проще и достаточно. |
| **Без `throttle` на `POST /api/visits`** | Endpoint открыт миру (без auth, без CSRF). Без лимита — бот / зацикленный коллектор / DoS забьют таблицу мусором. `throttle:60,1` per IP — первая дешёвая планка (Laravel-дефолт для api-группы). Легитимный трафик с одного IP редко превышает 60 страниц/мин. |
| **Throttle по `visitor_uid` вместо IP** | UID генерится клиентом → атакующий выдаёт новый на каждый запрос → throttle бесполезен. |
| **PHP-агрегация через `Collection::groupBy/unique`** | Портабельно sqlite ↔ MySQL без `HOUR()` vs `strftime` развилки, но грузит ВСЕ строки за день в память — на 100k+ записей за один host в день заметные тормоза, на миллионе OOM. SQL-агрегация по индексу `(host, created_at)` ничего лишнего в PHP не поднимает. |
| **Денормализованные агрегаты** (`visit_stats_hourly` по cron) | Гарантированная скорость дашборда независимо от объёма raw-данных. Но дублирование данных, eventual consistency, дополнительная инфра (cron/queue). Для текущего объёма SQL-агрегации с индексом достаточно. |
