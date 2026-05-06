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
| country | string(2), nullable | ISO-2; заполняется асинхронно (см. Geo) |
| city | string(120), nullable | заполняется асинхронно (см. Geo); сидер использует разные города |
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
- В контроллере: парсим UA через `UserAgentParser` (jenssegers/agent), извлекаем host через `parse_url(..., PHP_URL_HOST)`, пишем `Visit::create([...])`, диспатчим `ResolveVisitGeo::dispatch($visit->id)` (см. Geo). Ответ `204 No Content`.

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

## Geo (резолвинг IP → страна/город)

`country` (ISO-2) и `city` заполняются **асинхронно** после записи визита:

1. `VisitController::store` после `Visit::create(...)` диспатчит `ResolveVisitGeo::dispatch($visit->id)`.
2. `App\Jobs\ResolveVisitGeo` (`ShouldQueue`, `tries=3`, `backoff=[10,60,300]`, `timeout=10`) перечитывает `Visit` по id, вызывает `IpGeoLocator::resolve($ip)` и обновляет колонки. В job передаётся id, а не модель — стандартный приём, чтобы воркер работал с актуальной строкой, а не сериализованным снапшотом.
3. `App\Services\IpGeoLocator` ходит в `http://ip-api.com/json/{ip}?fields=status,countryCode,city` с таймаутом 1с, кеширует по IP на 24ч в дефолтный `Cache::store`. На исключение/таймаут/HTTP-ошибку — `[null, null]` + `Log::warning`.

**Зачем job, а не синхронно в контроллере.** Внешний HTTP в hot-path добавил бы 50–200 мс к p95 ответа `POST /api/visits` и сделал бы endpoint зависимым от ip-api.com (доступность, rate-limit 45 req/min с серверного IP). Очередь даёт ретраи и backoff бесплатно.

**Short-circuit для приватных/loopback IP** (`127.0.0.1`, `10.*`, `192.168.*`, `172.16/12`, IPv6 link-local) — без HTTP-запроса, сразу `[null, null]`. Иначе на dev-визитах долбили бы публичный API напрасно.

**Выбор провайдера — ip-api.com.** Бесплатно, без API-ключа, отдаёт `countryCode`/`city` одним запросом. Минус — лимит 45 req/min с одного IP. Если упрёмся: меняем драйвер внутри `IpGeoLocator` (вариант — MaxMind GeoLite2, локальный `.mmdb`, оффлайн, без лимитов) — контроллер и job не трогаем.

**Запуск воркера:**
- локалка — `composer dev` (поднимает `queue:listen` среди прочего);
- прод — supervisor + `php artisan queue:work --max-time=3600`, `php artisan queue:restart` после деплоя.

Без воркера `country`/`city` остаются `null` — `/stats` это переживает (`WHERE city IS NOT NULL`). Демо-данные в `DemoVisitsSeeder` подставляют города напрямую, ip-api.com не дёргают.

## Альтернативы

| Подход | Почему не выбран |
|---|---|
| **JSON body вместо URLSearchParams в sendBeacon** | `Content-Type: application/json` — non-simple → preflight OPTIONS перед каждым POST. Лишняя точка отказа на CORS. |
| **Синхронный гео-резолвинг прямо в контроллере** | Внешний HTTP в hot-path: 50–200 мс к p95, зависимость `/api/visits` от ip-api.com (rate-limit, аптайм). Job + retry/backoff бесплатны при уже поднятой database-очереди. |
| **Запись `Visit` тоже через очередь** | Само создание визита остаётся синхронным — это однострочный INSERT, никакой внешней зависимости. Очередь нужна только для гео-обогащения. |
| **Отдельное sqlite-соединение `analytics` для visits** | «Изоляция аналитики» звучит хорошо, но даёт реальные накладные (отдельный конфиг, env-переменная, multi-connection-танцы в `RefreshDatabase`) ради умозрительной пользы. ТЗ разрешает MySQL дословно. Симметричнее с `jokes`. |
| **ApexCharts / ECharts / D3** | Chart.js — наименьший по весу при достаточной функциональности (bar + pie). |
| **Парсинг UA на клиенте через `navigator.userAgentData`** | Не во всех браузерах, не для Safari. Серверный парсер надёжнее. |
| **MaxMind GeoLite2 вместо ip-api.com** | Локальный `.mmdb` — оффлайн, без rate-limit, быстрее. Но требует регистрации, периодического апдейта файла (~70 МБ) и доп. composer-пакета (`geoip2/geoip2`). Для текущего объёма ip-api.com бесплатно и без инфры; драйвер внутри `IpGeoLocator` подменяется в одну строку, если упрёмся в лимиты. |
| **stevebauman/location** | Готовая обвязка над теми же провайдерами с конфиг-файлом и сменой драйвера. Здесь обвязка тривиальная (один HTTP-вызов + кеш) — лишний пакет ради ничего. |
| **Cookie вместо localStorage для `visitor_uid`** | Cookie сайта-донора шлётся в КАЖДЫЙ его HTTP-запрос — лишние байты. Cookie с нашего хоста — third-party, блокируется Safari/Firefox/Chrome. localStorage — first-party, не сериализуется в HTTP, не подвержен third-party-блокам. |
| **Tracking pixel** (`<img src="...">`) | Без JS невозможно собрать `visitor_uid`. |
| **CSRF-cookie + Sanctum stateful** | Усложняет кросс-доменное использование (нужен origin allowlist). API-группа без CSRF проще и достаточно. |
| **Без `throttle` на `POST /api/visits`** | Endpoint открыт миру (без auth, без CSRF). Без лимита — бот / зацикленный коллектор / DoS забьют таблицу мусором. `throttle:60,1` per IP — первая дешёвая планка (Laravel-дефолт для api-группы). Легитимный трафик с одного IP редко превышает 60 страниц/мин. |
| **Throttle по `visitor_uid` вместо IP** | UID генерится клиентом → атакующий выдаёт новый на каждый запрос → throttle бесполезен. |
| **PHP-агрегация через `Collection::groupBy/unique`** | Портабельно sqlite ↔ MySQL без `HOUR()` vs `strftime` развилки, но грузит ВСЕ строки за день в память — на 100k+ записей за один host в день заметные тормоза, на миллионе OOM. SQL-агрегация по индексу `(host, created_at)` ничего лишнего в PHP не поднимает. |
| **Денормализованные агрегаты** (`visit_stats_hourly` по cron) | Гарантированная скорость дашборда независимо от объёма raw-данных. Но дублирование данных, eventual consistency, дополнительная инфра (cron/queue). Для текущего объёма SQL-агрегации с индексом достаточно. |
