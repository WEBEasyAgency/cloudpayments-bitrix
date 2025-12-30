<?php

namespace App\Application\Services;

use App\Domain\Entities\CloudPaymentEntity;

/**
 * Сервис для работы с CloudPayments API
 */
class CloudPaymentsService
{
    private array $config;
    private string $apiUrl = 'https://api.cloudpayments.ru';

    public function __construct()
    {
        $configPath = $_SERVER['DOCUMENT_ROOT'] . '/local/config/cloudpayments.php';
        if (!file_exists($configPath)) {
            throw new \Exception('CloudPayments config file not found');
        }
        $this->config = require $configPath;
    }

    /**
     * Получить Public ID для виджета
     *
     * @return string
     */
    public function getPublicId(): string
    {
        return $this->config['public_id'];
    }

    /**
     * Проверить, включен ли тестовый режим
     *
     * @return bool
     */
    public function isTestMode(): bool
    {
        return $this->config['test_mode'] ?? true;
    }

    /**
     * Получить конфигурацию виджета
     *
     * @return array
     */
    public function getWidgetConfig(): array
    {
        return $this->config['widget'] ?? [];
    }

    /**
     * Проверка подписи webhook запроса от CloudPayments
     *
     * @param string $requestBody JSON тело запроса
     * @param string $hmacHeader Заголовок X-Content-HMAC
     * @return bool
     */
    public function verifyWebhookSignature(string $requestBody, string $hmacHeader): bool
    {
        $apiSecret = $this->config['api_secret'];

        // В тестовом режиме с placeholder ключами пропускаем проверку
        if ($apiSecret === 'PLACEHOLDER_API_SECRET') {
            return true;
        }

        $calculatedHmac = base64_encode(hash_hmac('sha256', $requestBody, $apiSecret, true));

        return hash_equals($calculatedHmac, $hmacHeader);
    }

    /**
     * Создание платежа через API (для серверной интеграции)
     * В нашем случае используем виджет, но метод может пригодиться
     *
     * @param float $amount Сумма платежа
     * @param string $currency Валюта
     * @param string $invoiceId ID счета в системе
     * @param string $description Описание платежа
     * @param string $email Email плательщика
     * @param array $additionalData Дополнительные данные
     * @return array Ответ от API
     */
    public function createPayment(
        float $amount,
        string $currency,
        string $invoiceId,
        string $description,
        string $email,
        array $additionalData = []
    ): array {
        $data = [
            'Amount' => $amount,
            'Currency' => $currency,
            'InvoiceId' => $invoiceId,
            'Description' => $description,
            'Email' => $email,
            'JsonData' => json_encode($additionalData),
        ];

        return $this->apiRequest('/payments/cards/charge', $data);
    }

    /**
     * Создание рекуррентного (регулярного) платежа
     *
     * @param string $token Token карты для рекуррентных платежей
     * @param float $amount Сумма
     * @param string $currency Валюта
     * @param string $invoiceId ID счета
     * @param string $description Описание
     * @param string $email Email
     * @param array $additionalData Дополнительные данные
     * @return array Ответ от API
     */
    public function createRecurrentPayment(
        string $token,
        float $amount,
        string $currency,
        string $invoiceId,
        string $description,
        string $email,
        array $additionalData = []
    ): array {
        $data = [
            'Token' => $token,
            'Amount' => $amount,
            'Currency' => $currency,
            'InvoiceId' => $invoiceId,
            'Description' => $description,
            'Email' => $email,
            'JsonData' => json_encode($additionalData),
        ];

        return $this->apiRequest('/payments/tokens/charge', $data);
    }

    /**
     * Отмена платежа (возврат средств)
     *
     * @param int $transactionId ID транзакции в CloudPayments
     * @param float|null $amount Сумма возврата (null = полный возврат)
     * @return array Ответ от API
     */
    public function refundPayment(int $transactionId, ?float $amount = null): array
    {
        $data = [
            'TransactionId' => $transactionId,
        ];

        if ($amount !== null) {
            $data['Amount'] = $amount;
        }

        return $this->apiRequest('/payments/refund', $data);
    }

    /**
     * Получить информацию о транзакции
     *
     * @param int $transactionId ID транзакции
     * @return array
     */
    public function getTransaction(int $transactionId): array
    {
        $data = [
            'TransactionId' => $transactionId,
        ];

        return $this->apiRequest('/payments/get', $data);
    }

    /**
     * Выполнить запрос к CloudPayments API
     *
     * @param string $endpoint Endpoint API (например, /payments/cards/charge)
     * @param array $data Данные запроса
     * @return array Ответ от API
     */
    private function apiRequest(string $endpoint, array $data): array
    {
        $apiSecret = $this->config['api_secret'];
        $publicId = $this->config['public_id'];

        // В тестовом режиме с placeholder ключами возвращаем mock-ответ
        if ($apiSecret === 'PLACEHOLDER_API_SECRET') {
            return [
                'Success' => true,
                'Message' => 'Test mode - placeholder keys',
                'Model' => [
                    'TransactionId' => rand(100000, 999999),
                    'Amount' => $data['Amount'] ?? 0,
                    'Status' => 'Completed',
                ],
            ];
        }

        $url = $this->apiUrl . $endpoint;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_USERPWD, $publicId . ':' . $apiSecret);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            return [
                'Success' => false,
                'Message' => 'API request failed with code ' . $httpCode,
            ];
        }

        return json_decode($response, true) ?? [];
    }

    /**
     * Генерация уникального InvoiceId для платежа
     *
     * @param int $donationId ID записи донации в инфоблоке
     * @return string
     */
    public function generateInvoiceId(int $donationId): string
    {
        return 'VOOZ-DONATION-' . $donationId . '-' . time();
    }

    /**
     * Парсинг InvoiceId для получения ID донации
     *
     * @param string $invoiceId
     * @return int|null
     */
    public function parseDonationIdFromInvoiceId(string $invoiceId): ?int
    {
        // Формат: VOOZ-DONATION-{donationId}-{timestamp}
        if (preg_match('/^VOOZ-DONATION-(\d+)-\d+$/', $invoiceId, $matches)) {
            return (int)$matches[1];
        }

        return null;
    }

    /**
     * Подготовка данных для виджета CloudPayments
     *
     * @param float $amount Сумма платежа
     * @param string $currency Валюта
     * @param string $invoiceId ID счета
     * @param string $description Описание
     * @param string $email Email плательщика
     * @param string $name Имя плательщика
     * @param bool $recurrent Создавать токен для рекуррентных платежей
     * @return array Данные для передачи в виджет
     */
    public function prepareWidgetData(
        float $amount,
        string $currency,
        string $invoiceId,
        string $description,
        string $email,
        string $name = '',
        bool $recurrent = false
    ): array {
        $widgetConfig = $this->getWidgetConfig();

        return [
            'publicId' => $this->getPublicId(),
            'amount' => $amount,
            'currency' => $currency,
            'invoiceId' => $invoiceId,
            'description' => $description,
            'accountId' => $email, // Уникальный идентификатор плательщика в системе
            'email' => $email,
            'skin' => $widgetConfig['skin'] ?? 'modern',
            'language' => $widgetConfig['language'] ?? 'ru-RU',
            'requireEmail' => false, // Email уже указан
            'data' => [
                'name' => $name,
            ],
        ];
    }
}
