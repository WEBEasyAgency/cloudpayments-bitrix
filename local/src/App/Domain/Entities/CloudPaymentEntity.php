<?php

namespace App\Domain\Entities;

/**
 * Entity для данных платежа CloudPayments
 * Представляет данные, получаемые от CloudPayments через webhooks
 */
class CloudPaymentEntity
{
    /**
     * @var int ID транзакции в CloudPayments
     */
    public int $transactionId;

    /**
     * @var float Сумма платежа
     */
    public float $amount;

    /**
     * @var string Валюта платежа (RUB, USD, EUR и т.д.)
     */
    public string $currency;

    /**
     * @var string Дата и время платежа (ISO 8601)
     */
    public string $dateTime;

    /**
     * @var string Email плательщика
     */
    public string $email;

    /**
     * @var string Имя плательщика (опционально)
     */
    public string $name = '';

    /**
     * @var string Номер заказа/счета в вашей системе
     */
    public string $invoiceId;

    /**
     * @var string Описание платежа
     */
    public string $description;

    /**
     * @var string Статус платежа (Authorized, Completed, Cancelled, Declined)
     */
    public string $status;

    /**
     * @var string Код статуса
     */
    public string $statusCode;

    /**
     * @var bool Тестовый платеж или нет
     */
    public bool $testMode;

    /**
     * @var string|null Token для рекуррентных платежей (если есть)
     */
    public ?string $token = null;

    /**
     * @var array Дополнительные данные (AccountId и т.д.)
     */
    public array $data = [];

    /**
     * Создание Entity из данных webhook CloudPayments
     *
     * @param array $webhookData Данные от CloudPayments
     * @return self
     */
    public static function fromWebhook(array $webhookData): self
    {
        $entity = new self();

        $entity->transactionId = (int)($webhookData['TransactionId'] ?? 0);
        $entity->amount = (float)($webhookData['Amount'] ?? 0);
        $entity->currency = $webhookData['Currency'] ?? 'RUB';
        $entity->dateTime = $webhookData['DateTime'] ?? date('c');
        $entity->email = $webhookData['Email'] ?? '';
        $entity->name = $webhookData['Name'] ?? '';
        $entity->invoiceId = $webhookData['InvoiceId'] ?? '';
        $entity->description = $webhookData['Description'] ?? '';
        $entity->status = $webhookData['Status'] ?? '';
        $entity->statusCode = $webhookData['StatusCode'] ?? '';
        $entity->testMode = (bool)($webhookData['TestMode'] ?? true);
        $entity->token = $webhookData['Token'] ?? null;
        $entity->data = $webhookData['Data'] ?? [];

        return $entity;
    }

    /**
     * Проверка валидности данных платежа
     *
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->transactionId > 0
            && $this->amount > 0
            && !empty($this->currency)
            && !empty($this->invoiceId);
    }

    /**
     * Успешный ли платеж
     *
     * @return bool
     */
    public function isSuccessful(): bool
    {
        return in_array($this->status, ['Authorized', 'Completed']);
    }

    /**
     * Конвертация в массив для сохранения
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'transaction_id' => $this->transactionId,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'date_time' => $this->dateTime,
            'email' => $this->email,
            'name' => $this->name,
            'invoice_id' => $this->invoiceId,
            'description' => $this->description,
            'status' => $this->status,
            'status_code' => $this->statusCode,
            'test_mode' => $this->testMode,
            'token' => $this->token,
            'data' => $this->data,
        ];
    }
}
