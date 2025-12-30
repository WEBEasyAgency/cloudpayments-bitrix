<?php

/**
 * Конфигурация CloudPayments
 *
 * Ключи читаются из .env файла в корне проекта.
 * Скопируйте .env.example в .env и заполните своими ключами:
 *
 * 1. Зарегистрируйтесь на https://cloudpayments.ru/
 * 2. Создайте сайт в личном кабинете
 * 3. Получите Public ID и API Secret в разделе "Настройки" → "API"
 * 4. Укажите ключи в .env файле
 */

/**
 * Функция для загрузки переменных из .env файла
 */
if (!function_exists('loadCloudPaymentsEnv')) {
    function loadCloudPaymentsEnv(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return [];
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $env = [];

        foreach ($lines as $line) {
            // Пропускаем комментарии
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Парсим строку вида KEY=value
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                $value = trim($parts[1]);

                // Убираем кавычки если есть
                $value = trim($value, '"\'');

                $env[$key] = $value;
            }
        }

        return $env;
    }
}

// Загружаем переменные из .env
$envPath = $_SERVER['DOCUMENT_ROOT'] . '/.env';
$cloudPaymentsEnv = loadCloudPaymentsEnv($envPath);

/**
 * Функция для получения значения из .env с fallback
 */
if (!function_exists('getCloudPaymentsEnv')) {
    function getCloudPaymentsEnv(array $env, string $key, $default = null)
    {
        return $env[$key] ?? $_ENV[$key] ?? getenv($key) ?: $default;
    }
}

/**
 * Преобразуем строковое значение в boolean
 */
if (!function_exists('cloudPaymentsEnvBool')) {
    function cloudPaymentsEnvBool(array $env, string $key, bool $default = false): bool
    {
        $value = getCloudPaymentsEnv($env, $key, $default);
        if (is_bool($value)) {
            return $value;
        }
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}

return [
    /**
     * Public ID - используется на клиентской стороне для виджета оплаты
     * Формат: pk_XXXXXXXXXXXXXXXXXXXXXXXX или test_api_XXXXXXXXXXXXXXXXXXXXXXXX
     *
     * Где взять: Личный кабинет → Настройки → API → Public ID
     */
    'public_id' => getCloudPaymentsEnv($cloudPaymentsEnv, 'CLOUDPAYMENTS_PUBLIC_ID', 'your_public_id_here'),

    /**
     * API Secret - используется на серверной стороне для webhooks и API запросов
     * ВАЖНО: Храните в секрете! Не публикуйте в публичных репозиториях!
     *
     * Где взять: Личный кабинет → Настройки → API → API Secret
     */
    'api_secret' => getCloudPaymentsEnv($cloudPaymentsEnv, 'CLOUDPAYMENTS_API_SECRET', 'your_api_secret_here'),

    /**
     * Тестовый режим
     * true - использовать тестовые ключи и эмуляцию платежей
     * false - боевой режим с реальными платежами
     */
    'test_mode' => cloudPaymentsEnvBool($cloudPaymentsEnv, 'CLOUDPAYMENTS_TEST_MODE', true),

    /**
     * URL для webhook-ов CloudPayments
     * CloudPayments будет отправлять уведомления о платежах на эти адреса
     *
     * ВАЖНО: Настройте эти URL в личном кабинете CloudPayments:
     * Личный кабинет → Настройки → Уведомления
     */
    'webhooks' => [
        'check' => '/local/api/payments/check.php',  // Проверка возможности платежа
        'pay' => '/local/api/payments/pay.php',      // Успешный платеж
        'fail' => '/local/api/payments/fail.php',    // Неуспешный платеж
    ],

    /**
     * Настройки виджета оплаты
     */
    'widget' => [
        'language' => 'ru-RU',           // Язык виджета
        'currency' => 'RUB',             // Валюта платежа
        'skin' => 'modern',              // Стиль виджета: classic, modern, mini
        'require_confirmation' => true,  // Требовать 3-D Secure
    ],

    /**
     * Тестовые карты для проверки
     * Используйте эти номера карт в тестовом режиме
     */
    'test_cards' => [
        'success_visa' => '4242 4242 4242 4242',        // Visa с 3-D Secure - успешный платеж
        'success_mastercard' => '5555 5555 5555 4444',  // Mastercard с 3-D Secure - успешный платеж
        'insufficient_funds' => '4012 8888 8888 1881',  // Visa - недостаточно средств
        // CVV: любой трехзначный код
        // Expiry: любая будущая дата (например, 12/25)
    ],

    /**
     * Настройки для рекуррентных платежей (подписки)
     */
    'recurrent' => [
        'enabled' => true,               // Включить поддержку рекуррентных платежей
        'interval' => 'Month',           // Интервал: Day, Week, Month
        'period' => 1,                   // Период: каждый 1 месяц
        'start_date_type' => 'immediate', // immediate - сразу, custom - указать дату
    ],
];
