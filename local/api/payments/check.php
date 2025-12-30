<?php

/**
 * CloudPayments Webhook: Check
 *
 * Этот скрипт вызывается CloudPayments ПЕРЕД проведением платежа
 * для проверки возможности его проведения.
 *
 * Должен вернуть:
 * - {"code": 0} - разрешить платеж
 * - {"code": 13, "message": "Причина отказа"} - отклонить платеж
 */

// Инициализация Bitrix
$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../../');
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

// Инициализация автозагрузчика и приложения
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/src/App/Core/Autoloader.php';
$autoloader = new \App\Core\Autoloader();
$autoloader->addNamespace('App', $_SERVER['DOCUMENT_ROOT'] . '/local/src/App');
$autoloader->register();

use App\Domain\Entities\CloudPaymentEntity;

header('Content-Type: application/json');

try {
    // Получаем данные от CloudPayments
    $requestBody = file_get_contents('php://input');
    $webhookData = json_decode($requestBody, true);

    // Получаем CloudPayments сервис
    $cloudPaymentsService = app('cloudpayments');

    // Проверяем подпись запроса (безопасность)
    $hmacHeader = $_SERVER['HTTP_X_CONTENT_HMAC'] ?? '';
    if (!$cloudPaymentsService->verifyWebhookSignature($requestBody, $hmacHeader)) {
        http_response_code(401);
        echo json_encode([
            'code' => 13,
            'message' => 'Invalid signature',
        ]);
        exit;
    }

    // Создаем Entity из данных webhook
    $payment = CloudPaymentEntity::fromWebhook($webhookData);

    // Проверяем валидность данных
    if (!$payment->isValid()) {
        echo json_encode([
            'code' => 13,
            'message' => 'Invalid payment data',
        ]);
        exit;
    }

    // Получаем ID донации из InvoiceId
    $donationId = $cloudPaymentsService->parseDonationIdFromInvoiceId($payment->invoiceId);

    if ($donationId === null) {
        echo json_encode([
            'code' => 13,
            'message' => 'Invalid invoice ID',
        ]);
        exit;
    }

    // Проверяем, существует ли донация в базе
    \CModule::IncludeModule('iblock');
    $element = \CIBlockElement::GetByID($donationId)->Fetch();

    if (!$element) {
        echo json_encode([
            'code' => 13,
            'message' => 'Donation not found',
        ]);
        exit;
    }

    // Дополнительные проверки можно добавить здесь:
    // - Проверка суммы
    // - Проверка email
    // - Проверка статуса донации и т.д.

    // Если всё ОК - разрешаем платеж
    echo json_encode([
        'code' => 0, // 0 = success, разрешить платеж
    ]);

} catch (Exception $e) {
    // Логирование ошибки
    error_log('CloudPayments Check webhook error: ' . $e->getMessage());

    // Отклоняем платеж при ошибке
    echo json_encode([
        'code' => 13,
        'message' => 'Internal server error',
    ]);
}
