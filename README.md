# AmoPoint — тестовое задание PHP-разработчика

Реализованы все три задания из ТЗ:

1. **Команда `jokes:fetch`** + **`GET /api/jokes`** — каждые 5 минут забирает шутку с official-joke-api, отдаёт массив записей в JSON.
2. **`public/testzz/testlist.js`** — JS-сниппет под `https://test.amopoint-dev.ru/testzz/testlist.html`.
3. **Счётчик посещений** (бонус) — JS-коллектор `public/track.js` + `POST /api/visits` + дашборд `/stats` с графиками под Breeze auth.

Алгоритмы решений и разбор отвергнутых альтернатив — в [`docs/test-task/`](docs/test-task/PLAN.md).

## Стек

Laravel 13 (PHP 8.3+) · Breeze (Blade) · MySQL · Tailwind v4 · Vite · PHPUnit · Pint.

## Развёртывание

Требования: PHP 8.3+, Composer, Node 24 (см. `.nvmrc`), MySQL 8+.

```bash
git clone https://github.com/mazurok86/amo-point.git
cd amo-point
mysql -uroot -e 'CREATE DATABASE amo_point CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'
cp .env.example .env
composer setup    # install + key:generate + migrate + npm build
composer dev      # serve + queue + pail + vite → http://127.0.0.1:8000
```

Опционально nginx-vhost для `amo-point.local` — конфиг в `nginx/servers/amo-point.conf`.

## Проверка ТЗ

### Задание 1 — JSON API

```bash
php artisan jokes:fetch                      # одноразовый запуск
php artisan schedule:work                    # планировщик (каждые 5 мин)
curl http://127.0.0.1:8000/api/jokes
```

Поддерживается `?per_page=N` (1..100, default 50).

### Задание 2 — testlist.js

`public/testzz/testlist.js` работает в обеих формах поставки из ТЗ:

- **подключаемый файл**: `<script src="testlist.js">` (можно в `<head>` или перед `</body>`);
- **сниппет в DevTools Console**: открыть боевую страницу, F12 → Console, вставить весь файл, Enter.

Локальное зеркало для проверки без выхода на внешний хост: `http://amo-point.local/testzz/testlist.html` (byte-identical с боевой за исключением одного добавленного `<script src="testlist.js">`).

### Задание 3 — счётчик посещений

**Подключение `track.js` на произвольном сайте:**

```html
<script async src="https://your-host/track.js"></script>
```

Drop-in: ноль зависимостей, ноль конфигурации. Endpoint резолвится автоматически из `<script src>` через `document.currentScript` → `<origin>/api/visits`. На каждый pageload отправляется один POST с `visitor_uid` (UUID v4 в `localStorage`), `page_url`, `referrer`, `user_agent`. IP, host и парсинг UA — на сервере.

В проде хостьте `track.js` по **HTTPS** — иначе браузер на HTTPS-странице-доноре заблокирует mixed-content.

**Локальный smoke-тест:** `http://amo-point.local/track-demo.html` — страница с подключённым `track.js`. В DevTools → Network должен пройти `POST /api/visits` 204.

**Дашборд `/stats`** — посадочная страница после login/register (заменяет дефолтный Breeze-овский `/dashboard`). Чтобы увидеть с данными:

```bash
php artisan db:seed --class=DemoVisitsSeeder   # ~200 демо-визитов
```

затем зарегистрироваться через `/register` (без подтверждения email) — страница откроется с графиками (bar по часам, pie по городам).

## Тесты и стиль

```bash
php artisan test            # вся сьюита (SQLite :memory:)
./vendor/bin/pint --test    # проверка стиля; pint без флага — автофикс
```

См. [`CLAUDE.md`](CLAUDE.md) для контекста проекта.
