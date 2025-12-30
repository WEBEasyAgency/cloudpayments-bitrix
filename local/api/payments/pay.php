<?php

/**
 * CloudPayments Webhook: Pay
 *
 * Этот скрипт вызывается CloudPayments ПОСЛЕ успешного проведения платежа.
 * Здесь нужно обновить статус донации и выполнить необходимые действия.
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
        error_log('CloudPayments Pay: Invalid invoice ID - ' . $payment->invoiceId);
        echo json_encode(['code' => 0]); // Все равно возвращаем success, чтобы не было повторных запросов
        exit;
    }

    // Обновляем статус донации в инфоблоке
    \CModule::IncludeModule('iblock');

    // Получаем инфоблок
    $iblockId = \CIBlock::GetList([], ['CODE' => 'survey_want_help'])->Fetch()['ID'];

    if (!$iblockId) {
        error_log('CloudPayments Pay: IBlock survey_want_help not found');
        echo json_encode(['code' => 0]);
        exit;
    }

    // Получаем ID enum значения для статуса "success"
    $property = \CIBlockProperty::GetList(
        [],
        ['IBLOCK_ID' => $iblockId, 'CODE' => 'CLOUDPAYMENT_STATUS']
    )->Fetch();

    if ($property) {
        $enumValue = \CIBlockPropertyEnum::GetList(
            [],
            ['PROPERTY_ID' => $property['ID'], 'XML_ID' => 'success']
        )->Fetch();

        $successEnumId = $enumValue['ID'] ?? null;
    }

    // Обновляем элемент
    $el = new \CIBlockElement();
    $updateFields = [
        'MODIFIED_BY' => 1, // ID пользователя (системный)
    ];

    $updateProperties = [];

    // Обновляем статус платежа
    if (!empty($successEnumId)) {
        $updateProperties['CLOUDPAYMENT_STATUS'] = $successEnumId;
    }

    // Сохраняем ID транзакции и дополнительные данные в отдельные свойства (если они есть)
    // Можно добавить свойства в конфиг инфоблока для хранения:
    // - Transaction ID
    // - Payment Date
    // - Payment Token (для рекуррентных платежей)

    // Обновляем элемент
    $el->Update($donationId, $updateFields);

    // Обновляем свойства
    if (!empty($updateProperties)) {
        \CIBlockElement::SetPropertyValuesEx($donationId, $iblockId, $updateProperties);
    }

    // Логируем успешный платеж
    error_log(sprintf(
        'CloudPayments: Payment successful - Donation ID: %d, Transaction ID: %d, Amount: %.2f %s',
        $donationId,
        $payment->transactionId,
        $payment->amount,
        $payment->currency
    ));

    // Если есть токен для рекуррентных платежей - сохраняем его
    if (!empty($payment->token)) {
        error_log(sprintf(
            'CloudPayments: Recurrent token received for donation %d: %s',
            $donationId,
            $payment->token
        ));

        // TODO: Сохранить токен для будущих рекуррентных платежей
        // Можно создать отдельный инфоблок или таблицу для хранения токенов
    }

    // Можно отправить email с подтверждением оплаты
    // $surveyService = app('survey.want_help'); // если создадим такой сервис
    // $surveyService->sendPaymentConfirmationEmail($donationId, $payment);

    // Возвращаем успех
    echo json_encode([
        'code' => 0,
    ]);

} catch (Exception $e) {
    // Логирование ошибки
    error_log('CloudPayments Pay webhook error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());

    // Все равно возвращаем success, чтобы CloudPayments не повторял запрос
    echo json_encode([
        'code' => 0,
    ]);
}
