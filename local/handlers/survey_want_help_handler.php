<?php

/**
 * AJAX обработчик формы "Хочу помочь" (донаты)
 * URL: /local/handlers/survey_want_help_handler.php
 * Метод: POST
 * Возвращает: JSON
 */

// Проверка метода запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Метод не поддерживается']);
    exit;
}

// Инициализация Bitrix
$_SERVER['DOCUMENT_ROOT'] = $_SERVER['DOCUMENT_ROOT'] ?? dirname(dirname(dirname(__FILE__)));
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // Автозагрузчик
    require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/init.php';

    // Получение данных из POST
    $amount = isset($_POST['amount']) ? trim($_POST['amount']) : '';
    $paymentType = isset($_POST['paymentType']) ? trim($_POST['paymentType']) : '';
    $donorName = isset($_POST['donorName']) ? trim($_POST['donorName']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';

    // Создание Entity с валидацией
    $surveyEntity = new \App\Domain\Entities\SurveyWantHelpEntity(
        amount: $amount,
        paymentType: $paymentType,
        donorName: $donorName,
        email: $email,
        comment: $comment
    );

    // Вызов Service для сохранения
    $service = new \App\Application\Services\SurveyWantHelpService();
    $result = $service->saveDonation($surveyEntity);

    echo json_encode($result, JSON_UNESCAPED_UNICODE);

} catch (\Exception $e) {
    error_log('SurveyWantHelpHandler: ' . $e->getMessage());
    error_log('SurveyWantHelpHandler: ' . $e->getTraceAsString());

    echo json_encode([
        'success' => false,
        'message' => 'Ошибка при обработке запроса',
        'debug' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php';
