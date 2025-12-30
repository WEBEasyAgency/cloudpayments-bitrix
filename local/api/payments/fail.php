<?php

/**
 * CloudPayments Webhook: Fail
 *
 * Этот скрипт вызывается CloudPayments при неуспешном платеже
 * (отклонен банком, недостаточно средств и т.д.)
 *
 * Должен вернуть:
 * - {"code": 0} - уведомление обработано успешно
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

    // Получаем ID донации из InvoiceId
    $donationId = $cloudPaymentsService->parseDonationIdFromInvoiceId($payment->invoiceId);

    if ($donationId === null) {
        error_log('CloudPayments Fail: Invalid invoice ID - ' . $payment->invoiceId);
        echo json_encode(['code' => 0]);
        exit;
    }

    // Обновляем статус донации в инфоблоке
    \CModule::IncludeModule('iblock');

    // Получаем инфоблок
    $iblockId = \CIBlock::GetList([], ['CODE' => 'survey_want_help'])->Fetch()['ID'];

    if (!$iblockId) {
        error_log('CloudPayments Fail: IBlock survey_want_help not found');
        echo json_encode(['code' => 0]);
        exit;
    }

    // Получаем ID enum значения для статуса "rejected"
    $property = \CIBlockProperty::GetList(
        [],
        ['IBLOCK_ID' => $iblockId, 'CODE' => 'CLOUDPAYMENT_STATUS']
    )->Fetch();

    if ($property) {
        $enumValue = \CIBlockPropertyEnum::GetList(
            [],
            ['PROPERTY_ID' => $property['ID'], 'XML_ID' => 'rejected']
        )->Fetch();

        $rejectedEnumId = $enumValue['ID'] ?? null;
    }

    // Обновляем элемент
    $el = new \CIBlockElement();
    $updateFields = [
        'MODIFIED_BY' => 1,
    ];

    $updateProperties = [];

    // Обновляем статус платежа
    if (!empty($rejectedEnumId)) {
        $updateProperties['CLOUDPAYMENT_STATUS'] = $rejectedEnumId;
    }

    // Обновляем элемент
    $el->Update($donationId, $updateFields);

    // Обновляем свойства
    if (!empty($updateProperties)) {
        \CIBlockElement::SetPropertyValuesEx($donationId, $iblockId, $updateProperties);
    }

    // Логируем неуспешный платеж
    error_log(sprintf(
        'CloudPayments: Payment failed - Donation ID: %d, Transaction ID: %d, Reason: %s (Code: %s)',
        $donationId,
        $payment->transactionId,
        $payment->status,
        $payment->statusCode
    ));

    // Можно отправить email с уведомлением о неуспешной оплате
    // или предложить пользователю попробовать другую карту

    // Возвращаем успех
    echo json_encode([
        'code' => 0,
    ]);

} catch (Exception $e) {
    // Логирование ошибки
    error_log('CloudPayments Fail webhook error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());

    // Все равно возвращаем success
    echo json_encode([
        'code' => 0,
    ]);
}
