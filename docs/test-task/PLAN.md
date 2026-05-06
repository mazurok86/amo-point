# Тестовое задание PHP-разработчик

Реализация в репозитории `amo-point` (Laravel 13 + Breeze Blade + MySQL + Vite). Setup и инструкции для проверяющего — в [`README.md`](../../README.md).

## Состав ТЗ

1. **Обязательное.** Консольная команда раз в 5 минут забирает данные с API и сохраняет в БД. Route отдаёт массив записей в JSON. — детали: [01-jokes-command.md](01-jokes-command.md).
2. **Обязательное.** JS под страницу `https://test.amopoint-dev.ru/testzz/testlist.html`: при смене значения select остаются видимы только поля, в `name` которых содержится выбранное значение. — детали: [02-dynamic-fields.md](02-dynamic-fields.md).
3. **Бонус.** Счётчик посещений: JS-коллектор + бэкенд + страница статистики с графиками под авторизацией. — детали: [03-visit-counter.md](03-visit-counter.md).
