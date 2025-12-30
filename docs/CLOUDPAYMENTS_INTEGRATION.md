# Интеграция CloudPayments

Полная документация по интеграции платежной системы CloudPayments для приема пожертвований на странице "Хочу помочь".

## Содержание

1. [Обзор интеграции](#обзор-интеграции)
2. [Архитектура](#архитектура)
3. [Установка и настройка](#установка-и-настройка)
4. [Тестирование](#тестирование)
5. [Переход на боевой режим](#переход-на-боевой-режим)
6. [Troubleshooting](#troubleshooting)

---

## Обзор интеграции

Интеграция CloudPayments позволяет принимать пожертвования на странице `/ankety/?tab=want-help` через:
- **Единоразовые платежи** - разовое пожертвование
- **Рекуррентные платежи** - ежемесячные автоматические пожертвования

### Что реализовано

✅ Сохранение данных донации в инфоблок `survey_want_help`
✅ CloudPayments Widget для безопасного ввода данных карты
✅ Обработка webhooks (check, pay, fail) для отслеживания статуса платежей
✅ Автоматическая отправка email администратору и донору
✅ Поддержка рекуррентных платежей
✅ Логирование всех операций

---

## Архитектура

### Компоненты системы

```
Frontend (Форма пожертвования)
    ↓
Handler (survey_want_help_handler.php)
    ↓
SurveyWantHelpService
    ↓
IBlock (survey_want_help) ← Сохранение данных
    ↓
CloudPaymentsService ← Подготовка данных для виджета
    ↓
CloudPayments Widget ← Прием платежа
    ↓
Webhooks (check.php, pay.php, fail.php) ← Обновление статуса
```

### Файлы интеграции

| Файл | Назначение |
|------|-----------|
| `local/config/cloudpayments.php` | Конфигурация (ключи, настройки) |
| `local/src/App/Application/Services/CloudPaymentsService.php` | Сервис для работы с API CloudPayments |
| `local/src/App/Domain/Entities/CloudPaymentEntity.php` | Entity для данных платежа |
| `local/src/App/Application/Services/SurveyWantHelpService.php` | Сервис обработки пожертвований (обновлен) |
| `local/api/payments/check.php` | Webhook: проверка возможности платежа |
| `local/api/payments/pay.php` | Webhook: успешный платеж |
| `local/api/payments/fail.php` | Webhook: неуспешный платеж |
| `local/components/vooz/surveys.want_help_form/` | Компонент формы (обновлен) |

---

## Установка и настройка

### Шаг 1: Регистрация в CloudPayments

1. Перейдите на https://cloudpayments.ru/
2. Зарегистрируйте аккаунт (потребуется ИНН организации)
3. Войдите в личный кабинет
4. Создайте сайт с URL `https://ваш-домен.ru`

### Шаг 2: Получение ключей доступа

1. В личном кабинете перейдите: **Настройки → API**
2. Скопируйте:
   - **Public ID** (начинается с `pk_` или `test_api_`)
   - **API Secret** (длинная строка, храните в секрете!)

### Шаг 3: Замена placeholder ключей

Откройте файл `local/config/cloudpayments.php` и замените:

```php
return [
    // Замените эти значения на реальные ключи
    'public_id' => 'pk_ваш_публичный_ключ',  // ← Вставьте Public ID
    'api_secret' => 'ваш_секретный_ключ',   // ← Вставьте API Secret

    // В боевом режиме измените на false
    'test_mode' => false,

    // ... остальные настройки
];
```

### Шаг 4: Настройка webhooks в CloudPayments

В личном кабинете CloudPayments:

**Настройки → Уведомления** → Добавьте 3 webhook URL:

| Тип | URL | Описание |
|-----|-----|----------|
| Check | `https://ваш-домен.ru/local/api/payments/check.php` | Проверка перед платежом |
| Pay | `https://ваш-домен.ru/local/api/payments/pay.php` | Успешный платеж |
| Fail | `https://ваш-домен.ru/local/api/payments/fail.php` | Неуспешный платеж |

**ВАЖНО:**
- Webhooks работают только на публичном домене (не localhost)
- Для локальной разработки используйте ngrok или тестовый сервер

### Шаг 5: Настройка reCAPTCHA (опционально)

Рекомендуется добавить защиту от ботов:

1. Зарегистрируйте сайт на https://www.google.com/recaptcha
2. Добавьте ключи в конфиг
3. Добавьте проверку в `survey_want_help_handler.php`

---

## Тестирование

### Тестовый режим

По умолчанию интеграция работает в тестовом режиме:
- `test_mode` = `true` в конфиге
- Используются placeholder ключи
- Все операции эмулируются

### Тестовые карты CloudPayments

Для тестирования платежей используйте эти номера карт:

| Номер карты | Результат |
|-------------|-----------|
| `4242 4242 4242 4242` | Успешный платеж (Visa с 3-D Secure) |
| `5555 5555 5555 4444` | Успешный платеж (Mastercard с 3-D Secure) |
| `4012 8888 8888 1881` | Недостаточно средств |

**CVV:** любой трехзначный код (например, 123)
**Срок действия:** любая будущая дата (например, 12/25)
**Имя держателя:** любое (например, TEST CARD)

### Процесс тестирования

1. **Откройте страницу:**
   ```
   http://localhost:8001/ankety/?tab=want-help
   ```

2. **Заполните форму:**
   - Выберите тип платежа (единоразовый или ежемесячный)
   - Выберите сумму (или укажите свою)
   - Введите имя и email
   - Нажмите "Перейти к оплате"

3. **Проверьте сохранение:**
   - Данные должны сохраниться в инфоблок `survey_want_help`
   - Статус: `pending` (ожидание)

4. **Откроется виджет CloudPayments:**
   - Введите тестовый номер карты
   - Заполните остальные данные
   - Нажмите "Оплатить"

5. **После успешного платежа:**
   - Статус в инфоблоке изменится на `success`
   - Пользователь увидит сообщение об успехе
   - На email придут подтверждения

### Проверка webhooks

**Локально (с placeholder ключами):**
- Webhooks не вызываются (работает эмуляция)
- Статусы не обновляются автоматически

**На тестовом сервере (с реальными ключами):**

1. Откройте логи:
   ```bash
   tail -f error.log
   ```

2. Выполните тестовый платеж

3. Проверьте вызовы webhooks в логах:
   ```
   CloudPayments: Payment successful - Donation ID: 123, Transaction ID: 456789, Amount: 500.00 RUB
   ```

4. Проверьте статус в инфоблоке:
   ```
   CLOUDPAYMENT_STATUS = success
   ```

### Тестирование рекуррентных платежей

1. Выберите **"Ежемесячный платеж"**
2. Выполните первый платеж
3. В логах должна появиться запись о сохранении токена:
   ```
   CloudPayments: Recurrent token received for donation 123: tk_XXXXXXXXX
   ```

**Автоматизация рекуррентных платежей:**
- Требует дополнительной настройки (создание cron-задачи)
- Использует сохраненный token для последующих списаний
- См. метод `CloudPaymentsService::createRecurrentPayment()`

---

## Переход на боевой режим

После успешного тестирования:

### 1. Обновите конфигурацию

`local/config/cloudpayments.php`:

```php
return [
    'public_id' => 'pk_реальный_публичный_ключ',
    'api_secret' => 'реальный_секретный_ключ',
    'test_mode' => false, // ← Измените на false
    // ...
];
```

### 2. Проверьте webhooks

Убедитесь, что URL webhooks настроены на продакшн домен:
```
https://vooz.ru/local/api/payments/check.php
https://vooz.ru/local/api/payments/pay.php
https://vooz.ru/local/api/payments/fail.php
```

### 3. Свяжитесь с CloudPayments

Попросите менеджера CloudPayments:
- Переключить ваш сайт из тестового режима в боевой
- Проверить настройки уведомлений
- Согласовать тарифы (комиссии)

### 4. Проведите тестовый платеж

Выполните реальный платеж с минимальной суммой (например, 10 руб.) для проверки.

### 5. Мониторинг

Настройте мониторинг:
- Логи ошибок: `error.log`
- Уведомления о неуспешных платежах
- Регулярная проверка статусов в инфоблоке

---

## Troubleshooting

### Проблема: Виджет CloudPayments не открывается

**Симптомы:**
- После отправки формы ничего не происходит
- В консоли браузера ошибка: `cp is not defined`

**Решение:**
1. Проверьте, что CloudPayments SDK подключен в head:
   ```html
   <script src="https://widget.cloudpayments.ru/bundles/cloudpayments.js"></script>
   ```
2. Проверьте в DevTools → Network, загружается ли скрипт
3. Если используется Content Security Policy - добавьте `widget.cloudpayments.ru` в whitelist

### Проблема: Статус платежа не обновляется

**Симптомы:**
- Платеж проходит успешно, но в инфоблоке статус остается `pending`

**Решение:**
1. Проверьте, что webhooks настроены в CloudPayments:
   - Личный кабинет → Настройки → Уведомления
2. Проверьте доступность webhook URL (не должно быть 404, 500)
3. Проверьте логи webhook обработчиков:
   ```bash
   tail -f error.log | grep CloudPayments
   ```
4. Проверьте, что API Secret правильный (влияет на проверку подписи)

### Проблема: Webhook возвращает ошибку 401 (Invalid signature)

**Симптомы:**
- В логах CloudPayments: `Webhook returned 401`
- В логах сайта: нет записей о вызовах webhook

**Решение:**
1. Проверьте правильность `api_secret` в конфиге
2. Убедитесь, что в webhook не добавлены лишние пробелы или символы
3. В тестовом режиме проверка подписи отключена (если `api_secret = PLACEHOLDER_API_SECRET`)

### Проблема: Email не отправляются

**Симптомы:**
- Донация сохраняется, платеж проходит
- Email не приходят ни администратору, ни пользователю

**Решение:**
1. Проверьте настройки PHP mail():
   ```bash
   php -r "mail('test@example.com', 'Test', 'Test message');"
   ```
2. Используйте SMTP вместо mail():
   - Установите библиотеку PHPMailer
   - Обновите методы `sendEmailToAdmin()` и `sendEmailToUser()`
3. Проверьте спам-фильтры получателя

### Проблема: Рекуррентный платеж не создается автоматически

**Симптомы:**
- Первый платеж прошел успешно
- Token сохранен в логах
- Повторные ежемесячные платежи не списываются

**Решение:**

Автоматические рекуррентные платежи требуют дополнительной настройки:

1. Создайте таблицу для хранения токенов:
   ```sql
   CREATE TABLE vooz_recurrent_subscriptions (
       id INT AUTO_INCREMENT PRIMARY KEY,
       donation_id INT NOT NULL,
       token VARCHAR(255) NOT NULL,
       amount DECIMAL(10,2) NOT NULL,
       next_payment_date DATE NOT NULL,
       status ENUM('active', 'paused', 'cancelled') DEFAULT 'active',
       created_at DATETIME DEFAULT CURRENT_TIMESTAMP
   );
   ```

2. Создайте cron-задачу для ежедневной проверки:
   ```php
   // local/cron/process_recurrent_payments.php
   $cloudPayments = app('cloudpayments');

   // Получить подписки, у которых наступила дата следующего платежа
   $subscriptions = getActiveSubscriptionsDueToday();

   foreach ($subscriptions as $subscription) {
       $result = $cloudPayments->createRecurrentPayment(
           $subscription['token'],
           $subscription['amount'],
           'RUB',
           'VOOZ-RECURRENT-' . $subscription['id'] . '-' . time(),
           'Ежемесячное пожертвование ВООЗ',
           $subscription['email']
       );

       if ($result['Success']) {
           updateNextPaymentDate($subscription['id'], '+1 month');
       }
   }
   ```

3. Настройте cron на сервере:
   ```bash
   crontab -e
   # Запускать каждый день в 10:00
   0 10 * * * /usr/bin/php /var/www/html/local/cron/process_recurrent_payments.php
   ```

### Проблема: Placeholder ключи не работают

**Симптомы:**
- В консоли ошибка при инициализации виджета

**Решение:**

Это нормально! Placeholder ключи предназначены только для демонстрации структуры кода.

Для реального тестирования:
1. Зарегистрируйтесь на CloudPayments
2. Получите тестовые ключи
3. Замените placeholder-ы в `local/config/cloudpayments.php`

---

## API Reference

### CloudPaymentsService

Основной сервис для работы с CloudPayments.

**Получение сервиса:**
```php
$cloudPayments = app('cloudpayments');
```

**Методы:**

#### `getPublicId(): string`
Получить Public ID для виджета.

#### `isTestMode(): bool`
Проверить, включен ли тестовый режим.

#### `verifyWebhookSignature(string $requestBody, string $hmacHeader): bool`
Проверить подпись webhook запроса.

#### `prepareWidgetData(...): array`
Подготовить данные для виджета CloudPayments.

Параметры:
- `float $amount` - сумма платежа
- `string $currency` - валюта (RUB, USD, EUR)
- `string $invoiceId` - ID счета
- `string $description` - описание платежа
- `string $email` - email плательщика
- `string $name` - имя плательщика
- `bool $recurrent` - создавать токен для рекуррентных платежей

Возвращает:
```php
[
    'publicId' => 'pk_...',
    'amount' => 500.00,
    'currency' => 'RUB',
    'invoiceId' => 'VOOZ-DONATION-123-1234567890',
    'description' => 'Пожертвование ВООЗ - Единоразовое',
    'accountId' => 'user@example.com',
    'email' => 'user@example.com',
    'skin' => 'modern',
    'language' => 'ru-RU',
    'data' => ['name' => 'Иван Иванов']
]
```

#### `createPayment(...): array`
Создать платеж через API (серверная интеграция).

#### `createRecurrentPayment(...): array`
Создать рекуррентный платеж по сохраненному токену.

#### `refundPayment(int $transactionId, ?float $amount = null): array`
Отменить платеж (возврат средств).

#### `getTransaction(int $transactionId): array`
Получить информацию о транзакции.

---

## Дополнительные ресурсы

- [Официальная документация CloudPayments](https://developers.cloudpayments.ru/)
- [CloudPayments Widget API](https://developers.cloudpayments.ru/#platezhnye-formy)
- [Webhooks CloudPayments](https://developers.cloudpayments.ru/#webhooks)
- [Тестовые карты](https://developers.cloudpayments.ru/#test-cards)

---

## Контакты поддержки

**Техническая поддержка CloudPayments:**
- Email: support@cloudpayments.ru
- Телефон: +7 (495) 431-17-71
- Telegram: @cloudpayments_support

**Документация проекта VOOZ:**
- См. `docs/CREATING_PAGES.md` - создание новых страниц
- См. `docs/IBLOCK_CONFIGURATION.md` - работа с инфоблоками
- См. `docs/TROUBLESHOOTING.md` - общие проблемы
