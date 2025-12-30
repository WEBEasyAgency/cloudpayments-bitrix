# CloudPayments интеграция для Bitrix

Готовая к использованию интеграция с платежной системой CloudPayments для Bitrix CMS.

**Это реальная работающая интеграция**, используемая в production на проекте [VOOZ](https://vooz.ru/).

## Что включено

### Сервисы и Entity
- `CloudPaymentsService` - сервис для работы с CloudPayments API
- `CloudPaymentEntity` - entity для данных платежей от CloudPayments
- `SurveyWantHelpService` - сервис для обработки донатов
- `SurveyWantHelpEntity` - entity для данных формы доната

### API Webhooks
- `/local/api/payments/check.php` - проверка перед платежом
- `/local/api/payments/pay.php` - обработка успешного платежа
- `/local/api/payments/fail.php` - обработка неудачного платежа

### Компонент Bitrix
- `vooz:surveys.want_help_form` - форма приема донатов с интеграцией CloudPayments Widget

### Конфигурация
- `local/config/cloudpayments.php` - конфигурация CloudPayments (ключи из .env)
- `local/config/iblocks/survey_want_help.php` - конфигурация инфоблока для донатов

### Handler
- `local/handlers/survey_want_help_handler.php` - AJAX обработчик формы доната

### Документация
- `docs/CLOUDPAYMENTS_INTEGRATION.md` - полная документация по интеграции

## Установка

### 1. Копирование файлов

Скопируйте содержимое директории `local/` в `local/` вашего Bitrix проекта:

```bash
# Структура после копирования:
local/
├── src/App/
│   ├── Application/Services/
│   │   ├── CloudPaymentsService.php
│   │   └── SurveyWantHelpService.php
│   └── Domain/Entities/
│       ├── CloudPaymentEntity.php
│       └── SurveyWantHelpEntity.php
├── config/
│   ├── cloudpayments.php
│   └── iblocks/
│       └── survey_want_help.php
├── api/payments/
│   ├── check.php
│   ├── pay.php
│   └── fail.php
├── components/vooz/
│   └── surveys.want_help_form/
├── handlers/
│   └── survey_want_help_handler.php
```

### 2. Настройка .env

Добавьте в файл `.env` в корне Bitrix проекта:

```env
# CloudPayments API Keys
CLOUDPAYMENTS_PUBLIC_ID=pk_XXXXXXXXXXXXXXXXXXXXXXXX
CLOUDPAYMENTS_API_SECRET=your_api_secret_here
CLOUDPAYMENTS_TEST_MODE=true

# Yandex SmartCaptcha (опционально, если у вас есть капча)
SMARTCAPTCHA_SERVER_KEY=your_server_key
SMARTCAPTCHA_CLIENT_KEY=your_client_key
```

Ключи получить здесь:
- Регистрация: https://cloudpayments.ru/
- API ключи: Личный кабинет → Настройки → API

### 3. Регистрация сервиса в init.php

В файле `bitrix/php_interface/init.php` (или `local/php_interface/init.php`):

```php
<?php
// Если у вас есть автозагрузчик PSR-4 для namespace App\
// то сервисы зарегистрируются автоматически

// Пример регистрации в Application (если используется):
use App\Core\Application;
use App\Application\Services\CloudPaymentsService;
use App\Application\Services\SurveyWantHelpService;

$app = Application::getInstance();

$app->set('cloudpayments', function() {
    return new CloudPaymentsService();
});

$app->set('survey_want_help', function() use ($app) {
    return new SurveyWantHelpService($app->get('cloudpayments'));
});
```

### 4. Автосинхронизация IBlock

IBlock `survey_want_help` автоматически создастся при первом обращении к сайту (если у вас настроена автосинхронизация IBlock из конфигов).

Если автосинхронизации нет, создайте IBlock вручную:
- Тип: `vooz_forms`
- Код: `survey_want_help`
- Свойства: см. в `local/config/iblocks/survey_want_help.php`

### 5. Настройка Webhooks в CloudPayments

1. Войдите в личный кабинет CloudPayments
2. Перейдите в **Настройки** → **Уведомления**
3. Укажите URL webhooks:

| Событие | URL |
|---------|-----|
| Check | `https://yoursite.com/local/api/payments/check.php` |
| Pay | `https://yoursite.com/local/api/payments/pay.php` |
| Fail | `https://yoursite.com/local/api/payments/fail.php` |

4. Метод: **POST**
5. Формат: **CloudPayments**
6. Кодировка: **UTF-8**

## Использование

### На странице Bitrix

```php
<?php
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/header.php');

$APPLICATION->SetTitle('Помощь проекту');

// Вызов компонента
$APPLICATION->IncludeComponent(
    'vooz:surveys.want_help_form',
    '',
    [],
    false
);

require($_SERVER['DOCUMENT_ROOT'].'/bitrix/footer.php');
?>
```

### Как это работает

1. **Пользователь заполняет форму** (сумма, email, имя, комментарий)
2. **AJAX запрос** на `/local/handlers/survey_want_help_handler.php`
3. **Создается запись в IBlock** со статусом "pending"
4. **Возвращаются данные для виджета** CloudPayments
5. **Открывается виджет оплаты** CloudPayments
6. **Пользователь вводит карту** и подтверждает платеж
7. **CloudPayments отправляет webhook** на `/local/api/payments/check.php` (проверка)
8. **CloudPayments отправляет webhook** на `/local/api/payments/pay.php` (успех) или `/local/api/payments/fail.php` (ошибка)
9. **Обновляется статус** в IBlock на "success" или "rejected"
10. **Отправляются email** администратору и донору

### Рекуррентные платежи (подписки)

Для ежемесячных донатов при успешном платеже сохраняется `token` в поле `RECURRENT_TOKEN`.

Пример автоматического списания:

```php
<?php
use App\Application\Services\CloudPaymentsService;

$service = app('cloudpayments');

// Получаем записи с рекуррентными платежами
$donations = \CIBlockElement::GetList(
    [],
    [
        'IBLOCK_CODE' => 'survey_want_help',
        'PROPERTY_PAYMENT_TYPE' => 'monthly',
        '!PROPERTY_RECURRENT_TOKEN' => false,
    ]
);

while ($donation = $donations->Fetch()) {
    $token = $donation['PROPERTY_RECURRENT_TOKEN_VALUE'];
    $amount = $donation['PROPERTY_AMOUNT_VALUE'];
    $email = $donation['PROPERTY_EMAIL_VALUE'];

    $invoiceId = $service->generateInvoiceId($donation['ID']);

    // Списываем средства
    $result = $service->createRecurrentPayment(
        $token,
        $amount,
        'RUB',
        $invoiceId,
        'Ежемесячная подписка',
        $email
    );

    if ($result['Success']) {
        echo "Списание успешно: " . $result['Model']['TransactionId'];
    }
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

- CVV: любой (например `123`)
- Срок: любая будущая дата (например `12/25`)

## Структура данных

### IBlock "survey_want_help"

| Свойство | Тип | Описание |
|----------|-----|----------|
| AMOUNT | String | Сумма доната |
| PAYMENT_TYPE | List | one_time / monthly |
| DONOR_NAME | String | Имя донора |
| EMAIL | String | Email донора |
| COMMENT | HTML | Комментарий |
| SUBMITTED_AT | String | Дата отправки формы |
| CLOUDPAYMENT_STATUS | List | pending / success / rejected |
| TRANSACTION_ID | String | ID транзакции CloudPayments |
| RECURRENT_TOKEN | String | Token для рекуррентных платежей |

## API методы CloudPaymentsService

### prepareWidgetData()
Подготовка данных для виджета оплаты

### generateInvoiceId()
Генерация уникального Invoice ID

### parseDonationIdFromInvoiceId()
Извлечение ID доната из Invoice ID

### verifyWebhookSignature()
Проверка подписи webhook (HMAC SHA256)

### createPayment()
Создание платежа через API (не через виджет)

### createRecurrentPayment()
Создание рекуррентного платежа по токену

### refundPayment()
Возврат средств

### getTransaction()
Получение информации о транзакции

## Безопасность

- ✅ Проверка HMAC подписи в webhooks
- ✅ Валидация данных форм
- ✅ Защита от SQL инъекций (через Bitrix API)
- ✅ API Secret хранится в .env (не в git)
- ✅ Yandex SmartCaptcha на форме (опционально)

## Логи

Ошибки и события логируются в:
- `local/api/payments/error.log` - ошибки webhooks
- PHP error log - общие ошибки приложения

## Полная документация

См. `docs/CLOUDPAYMENTS_INTEGRATION.md` для детальной документации.

## Поддержка

- Email: dev@vooz.ru
- Официальная документация CloudPayments: https://developers.cloudpayments.ru/

## Лицензия

MIT License

---

**Разработано командой VOOZ**
