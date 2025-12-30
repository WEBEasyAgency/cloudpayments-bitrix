# CloudPayments интеграция для Bitrix

Готовая к использованию интеграция с платежной системой CloudPayments для Bitrix CMS.

**Это реальная работающая интеграция**, используемая в production на проекте [VOOZ](https://vooz.ru/).

## Что включено

### Основные компоненты

- **CloudPaymentsService** (`local/src/App/Application/Services/CloudPaymentsService.php`)
  - Работа с CloudPayments API
  - Подготовка данных для виджета
  - Создание платежей (одноразовые и рекуррентные)
  - Возвраты, получение информации о транзакциях
  - Проверка HMAC подписи webhooks

- **CloudPaymentEntity** (`local/src/App/Domain/Entities/CloudPaymentEntity.php`)
  - Entity для данных платежа от CloudPayments
  - Валидация данных webhook
  - Проверка успешности платежа

### API Webhooks

- `/local/api/payments/check.php` - проверка перед платежом
- `/local/api/payments/pay.php` - обработка успешного платежа
- `/local/api/payments/fail.php` - обработка неудачного платежа

### Конфигурация

- `local/config/cloudpayments.php` - конфигурация CloudPayments (читает ключи из .env)

### Документация

- `docs/CLOUDPAYMENTS_INTEGRATION.md` - полная документация по интеграции

## Установка

⚠️ **Важно**: После копирования файлов обязательно настройте автозагрузку классов (шаг 3), иначе интеграция не заработает!

### 1. Копирование файлов

Скопируйте содержимое директории `local/` в `local/` вашего Bitrix проекта:

```bash
local/
├── src/App/
│   ├── Application/Services/
│   │   └── CloudPaymentsService.php
│   └── Domain/Entities/
│       └── CloudPaymentEntity.php
├── config/
│   └── cloudpayments.php
└── api/payments/
    ├── check.php
    ├── pay.php
    └── fail.php
```

### 2. Настройка .env

Добавьте в `.env` в корне проекта:

```env
CLOUDPAYMENTS_PUBLIC_ID=pk_XXXXXXXXXXXXXXXXXXXXXXXX
CLOUDPAYMENTS_API_SECRET=your_api_secret_here
CLOUDPAYMENTS_TEST_MODE=true
```

**Где получить ключи:**
- Регистрация: https://cloudpayments.ru/
- API ключи: Личный кабинет → Настройки → API

### 3. Настройка автозагрузки классов

**ВАЖНО!** Классы используют namespace `App\`, поэтому нужна PSR-4 автозагрузка.

#### Вариант А: У вас уже есть PSR-4 автозагрузчик

Если у вас уже настроена автозагрузка для `App\` → `local/src/App/`, то ничего делать не нужно - классы подхватятся автоматически.

#### Вариант Б: Настроить автозагрузку вручную

Создайте файл `local/php_interface/init.php` (или дополните существующий):

```php
<?php
// Автозагрузчик для namespace App\
spl_autoload_register(function ($class) {
    // Проверяем что класс из namespace App\
    if (strpos($class, 'App\\') !== 0) {
        return;
    }

    // Формируем путь к файлу
    $path = $_SERVER['DOCUMENT_ROOT'] . '/local/src/' . str_replace('\\', '/', $class) . '.php';

    // Подключаем файл если он существует
    if (file_exists($path)) {
        require_once $path;
    }
});
```

#### Вариант В: Использовать Composer (рекомендуется)

Если в проекте используется Composer, создайте `local/composer.json`:

```json
{
    "autoload": {
        "psr-4": {
            "App\\": "src/App/"
        }
    }
}
```

Затем выполните:

```bash
cd local/
composer dump-autoload
```

И в `bitrix/php_interface/init.php` добавьте:

```php
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/vendor/autoload.php';
```

### 4. Регистрация сервиса (опционально)

Если у вас есть Application контейнер, зарегистрируйте сервис в `init.php`:

```php
<?php
use App\Core\Application;
use App\Application\Services\CloudPaymentsService;

$app = Application::getInstance();
$app->set('cloudpayments', function() {
    return new CloudPaymentsService();
});
```

Если нет - просто создавайте экземпляр напрямую:

```php
$cloudPayments = new \App\Application\Services\CloudPaymentsService();
```

### 5. Настройка Webhooks в CloudPayments

1. Войдите в личный кабинет CloudPayments
2. Перейдите в **Настройки** → **Уведомления**
3. Укажите URL webhooks:

| Событие | URL |
|---------|-----|
| Check | `https://yoursite.com/local/api/payments/check.php` |
| Pay | `https://yoursite.com/local/api/payments/pay.php` |
| Fail | `https://yoursite.com/local/api/payments/fail.php` |

4. Метод: **POST**, Формат: **CloudPayments**, Кодировка: **UTF-8**

## Проверка установки

Создайте тестовый файл `test_cloudpayments.php` в корне проекта:

```php
<?php
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

use App\Application\Services\CloudPaymentsService;

try {
    $service = new CloudPaymentsService();
    echo "✅ CloudPaymentsService подключен успешно!<br>";
    echo "Public ID: " . $service->getPublicId() . "<br>";
    echo "Test Mode: " . ($service->isTestMode() ? 'Да' : 'Нет') . "<br>";
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage();
}
```

Откройте в браузере `http://yoursite.com/test_cloudpayments.php`. Если видите сообщение об успехе - всё настроено правильно!

## Использование

### Пример: Прием платежей через виджет

```php
<?php
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/header.php');

use App\Application\Services\CloudPaymentsService;

$cloudPayments = new CloudPaymentsService();

// Ваша бизнес-логика: создаем заказ в БД
$orderId = 123; // ID заказа в вашей системе
$amount = 1000.00; // Сумма
$customerEmail = 'customer@example.com';
$customerName = 'Иван Иванов';

// Генерируем Invoice ID
$invoiceId = $cloudPayments->generateInvoiceId($orderId);

// Подготавливаем данные для виджета
$widgetData = $cloudPayments->prepareWidgetData(
    $amount,
    'RUB',
    $invoiceId,
    'Оплата заказа #' . $orderId,
    $customerEmail,
    $customerName
);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Оплата заказа</title>
    <script src="https://widget.cloudpayments.ru/bundles/cloudpayments.js"></script>
</head>
<body>
    <h1>Оплата заказа #<?= $orderId ?></h1>
    <p>Сумма: <?= number_format($amount, 2) ?> ₽</p>

    <button id="payButton">Оплатить</button>

    <script>
        const widget = new cp.CloudPayments();
        const widgetData = <?= json_encode($widgetData) ?>;

        document.getElementById('payButton').addEventListener('click', () => {
            widget.pay('charge', widgetData, {
                onSuccess: (options) => {
                    alert('Платеж успешен! ID: ' + options.TransactionId);
                    window.location.href = '/success.php?order_id=<?= $orderId ?>';
                },
                onFail: (reason) => {
                    alert('Ошибка оплаты: ' + reason);
                }
            });
        });
    </script>
</body>
</html>

<?php require($_SERVER['DOCUMENT_ROOT'].'/bitrix/footer.php'); ?>
```

### Адаптация webhooks под ваш проект

Webhooks (`check.php`, `pay.php`, `fail.php`) содержат TODO комментарии. Адаптируйте их под свою логику:

**Пример адаптации `pay.php`:**

```php
<?php
// В файле local/api/payments/pay.php

// После проверки подписи и парсинга данных:
$payment = CloudPaymentEntity::fromWebhook($webhookData);
$orderId = $service->parseDonationIdFromInvoiceId($payment->invoiceId);

// ВАША ЛОГИКА:
// Обновляем заказ в вашей таблице
$el = new CIBlockElement();
$el->Update($orderId, [
    'PROPERTY_VALUES' => [
        'STATUS' => 'paid',
        'TRANSACTION_ID' => $payment->transactionId,
        'PAID_AT' => date('d.m.Y H:i:s'),
    ]
]);

// Отправляем email клиенту
CEvent::Send('ORDER_PAID', SITE_ID, [
    'EMAIL' => $payment->email,
    'ORDER_ID' => $orderId,
    'AMOUNT' => $payment->amount,
]);

echo json_encode(['code' => 0]);
```

### Методы CloudPaymentsService

#### prepareWidgetData()
Подготовка данных для виджета

```php
$widgetData = $service->prepareWidgetData(
    amount: 1000.00,
    currency: 'RUB',
    invoiceId: 'ORDER-123-1234567890',
    description: 'Оплата заказа',
    email: 'customer@example.com',
    name: 'Иван Иванов',
    recurrent: false // true для рекуррентных платежей
);
```

#### generateInvoiceId()
Генерация уникального Invoice ID

```php
$invoiceId = $service->generateInvoiceId($orderId);
// Результат: "VOOZ-DONATION-123-1735632000"
```

#### parseDonationIdFromInvoiceId()
Извлечение ID заказа из Invoice ID

```php
$orderId = $service->parseDonationIdFromInvoiceId($invoiceId);
```

#### verifyWebhookSignature()
Проверка HMAC подписи webhook

```php
$requestBody = file_get_contents('php://input');
$hmacHeader = $_SERVER['HTTP_X_CONTENT_HMAC'] ?? '';

if (!$service->verifyWebhookSignature($requestBody, $hmacHeader)) {
    http_response_code(401);
    exit;
}
```

#### createPayment()
Создание платежа через API (серверная интеграция)

```php
$result = $service->createPayment(
    1000.00,
    'RUB',
    'ORDER-123',
    'Оплата заказа',
    'customer@example.com'
);
```

#### createRecurrentPayment()
Создание рекуррентного платежа по токену

```php
$result = $service->createRecurrentPayment(
    $token, // Token от предыдущего платежа
    500.00,
    'RUB',
    'SUBSCRIPTION-456',
    'Подписка',
    'customer@example.com'
);
```

#### refundPayment()
Возврат средств

```php
// Полный возврат
$result = $service->refundPayment($transactionId);

// Частичный возврат
$result = $service->refundPayment($transactionId, 500.00);
```

#### getTransaction()
Получение информации о транзакции

```php
$result = $service->getTransaction($transactionId);
```

## Рекуррентные платежи (подписки)

### 1. Первый платеж с сохранением токена

```php
$widgetData = $service->prepareWidgetData(
    amount: 500.00,
    currency: 'RUB',
    invoiceId: $service->generateInvoiceId($subscriptionId),
    description: 'Подписка',
    email: 'customer@example.com',
    recurrent: true  // Важно! Сохраняем токен
);
```

### 2. Сохранение токена в webhook pay.php

```php
$payment = CloudPaymentEntity::fromWebhook($webhookData);

if ($payment->token) {
    // Сохраните токен в вашей БД для будущих списаний
    saveRecurrentToken($subscriptionId, $payment->token);
}
```

### 3. Последующие списания

```php
$token = getRecurrentToken($subscriptionId);

$result = $service->createRecurrentPayment(
    $token,
    500.00,
    'RUB',
    $service->generateInvoiceId($subscriptionId),
    'Ежемесячная подписка',
    'customer@example.com'
);

if ($result['Success']) {
    echo "Списание успешно!";
}
```

## Тестирование

### Тестовый режим

В `.env` установите:
```env
CLOUDPAYMENTS_TEST_MODE=true
```

### Тестовые карты

| Номер карты | Результат |
|-------------|-----------|
| `4242 4242 4242 4242` | Visa - успешный платеж |
| `5555 5555 5555 4444` | Mastercard - успешный платеж |
| `4012 8888 8888 1881` | Visa - недостаточно средств |

- **CVV**: любой (например `123`)
- **Срок**: любая будущая дата (например `12/25`)

## Безопасность

✅ Проверка HMAC подписи в webhooks (SHA256)
✅ API Secret хранится в .env (не в git)
✅ Валидация данных через CloudPaymentEntity
✅ HTTPS для webhooks (обязательно в production)

## Полная документация

См. `docs/CLOUDPAYMENTS_INTEGRATION.md` для детальной документации по API CloudPayments.

## Поддержка

- Email: dev@vooz.ru
- Официальная документация CloudPayments: https://developers.cloudpayments.ru/

## Лицензия

MIT License

---

**Разработано командой VOOZ**
