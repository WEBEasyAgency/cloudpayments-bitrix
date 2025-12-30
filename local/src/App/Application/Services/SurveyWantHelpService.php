<?php

namespace App\Application\Services;

use App\Domain\Entities\SurveyWantHelpEntity;

class SurveyWantHelpService
{
    private const SURVEY_IBLOCK_CODE = 'survey_want_help';
    private const ADMIN_EMAIL = 'info@rare-diseases.ru';

    /**
     * Сохранить заявку на пожертвование
     */
    public function saveDonation(SurveyWantHelpEntity $survey): array
    {
        // Валидация данных
        $validationErrors = $survey->validate();
        if (!empty($validationErrors)) {
            return [
                'success' => false,
                'errors' => $validationErrors
            ];
        }

        try {
            if (!\Bitrix\Main\Loader::includeModule('iblock')) {
                throw new \Exception('Модуль iblock не загружен');
            }

            // Получаем ID инфоблока
            $iblockId = $this->getIBlockId();
            if (!$iblockId) {
                throw new \Exception('Инфоблок пожертвований не найден');
            }

            // Получаем ID enum-значения для типа платежа
            $paymentTypeEnumId = $this->getPaymentTypeEnumId($iblockId, $survey->getPaymentTypeEnum());

            // Создаем элемент в инфоблоке
            $submittedAt = date('d.m.Y H:i:s');
            $el = new \CIBlockElement;
            $elementId = $el->Add([
                'IBLOCK_ID' => $iblockId,
                'NAME' => $survey->donorName . ' - ' . $survey->amount . ' руб. (' . $submittedAt . ')',
                'CODE' => 'donation-' . time() . '-' . random_int(1000, 9999),
                'ACTIVE' => 'Y',
                'ACTIVE_FROM' => $submittedAt,
                'PROPERTY_VALUES' => [
                    'AMOUNT' => $survey->amount,
                    'PAYMENT_TYPE' => ['VALUE' => $paymentTypeEnumId],
                    'DONOR_NAME' => $survey->donorName,
                    'EMAIL' => $survey->email,
                    'COMMENT' => $survey->comment,
                    'SUBMITTED_AT' => $submittedAt,
                    'CLOUDPAYMENT_STATUS' => ['VALUE' => 'pending'], // Enum ID для 'Ожидание'
                ]
            ]);

            if (!$elementId) {
                throw new \Exception('Ошибка при сохранении заявки: ' . $el->LAST_ERROR);
            }

            // Отправляем email админу
            $this->sendEmailToAdmin($survey, $submittedAt);

            // Отправляем email пользователю
            $this->sendEmailToUser($survey);

            // Подготавливаем данные для CloudPayments виджета
            $paymentData = $this->preparePaymentData($elementId, $survey);

            return [
                'success' => true,
                'message' => 'Заявка успешно отправлена',
                'elementId' => $elementId,
                'paymentData' => $paymentData, // Данные для виджета CloudPayments
            ];

        } catch (\Exception $e) {
            $errorMsg = 'SurveyWantHelpService: Ошибка при сохранении заявки: ' . $e->getMessage();
            error_log($errorMsg);
            error_log('SurveyWantHelpService: Stack trace: ' . $e->getTraceAsString());
            return [
                'success' => false,
                'message' => 'Ошибка при отправке заявки. Попробуйте позже.',
                'debug' => $errorMsg
            ];
        }
    }

    /**
     * Получить ID инфоблока
     */
    private function getIBlockId(): ?int
    {
        $res = \CIBlock::GetList([], ['CODE' => self::SURVEY_IBLOCK_CODE, 'CHECK_PERMISSIONS' => 'N']);
        if ($iblock = $res->Fetch()) {
            return (int)$iblock['ID'];
        }
        return null;
    }

    /**
     * Получить ID enum-значения для типа платежа
     */
    private function getPaymentTypeEnumId(int $iblockId, string $paymentType): ?int
    {
        // Получаем ID свойства PAYMENT_TYPE
        $propRes = \CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => 'PAYMENT_TYPE']);
        if (!$prop = $propRes->Fetch()) {
            return null;
        }

        $propertyId = (int)$prop['ID'];

        // Получаем ID enum-значения
        $enumRes = \CIBlockPropertyEnum::GetList([], ['PROPERTY_ID' => $propertyId, 'XML_ID' => $paymentType]);
        if ($enumItem = $enumRes->Fetch()) {
            return (int)$enumItem['ID'];
        }

        return null;
    }

    /**
     * Отправить email администратору
     */
    private function sendEmailToAdmin(SurveyWantHelpEntity $survey, string $submittedAt): void
    {
        $subject = 'Новая заявка на пожертвование';
        $message = $this->buildAdminEmailBody($survey, $submittedAt);

        $headers = [
            'Content-Type' => 'text/html; charset=UTF-8',
            'From' => 'noreply@rare-diseases.ru'
        ];

        mail(self::ADMIN_EMAIL, $subject, $message, $headers);
    }

    /**
     * Отправить email пользователю
     */
    private function sendEmailToUser(SurveyWantHelpEntity $survey): void
    {
        $subject = 'Спасибо, что помогаете';
        $message = $this->buildUserEmailBody($survey);

        $headers = [
            'Content-Type' => 'text/html; charset=UTF-8',
            'From' => 'noreply@rare-diseases.ru'
        ];

        mail($survey->email, $subject, $message, $headers);
    }

    /**
     * Построить тело email для администратора
     */
    private function buildAdminEmailBody(SurveyWantHelpEntity $survey, string $submittedAt): string
    {
        $comment = !empty($survey->comment) ? $survey->comment : 'Нет';

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #007bff; color: white; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .content { background-color: #f9f9f9; padding: 20px; border-radius: 5px; }
        .field { margin-bottom: 15px; }
        .field-label { font-weight: bold; color: #007bff; }
        .field-value { color: #666; margin-top: 5px; }
        .footer { margin-top: 20px; font-size: 12px; color: #999; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Новая заявка на пожертвование</h2>
        </div>
        <div class="content">
            <div class="field">
                <div class="field-label">Сумма</div>
                <div class="field-value">{$survey->amount} руб.</div>
            </div>
            <div class="field">
                <div class="field-label">Тип платежа</div>
                <div class="field-value">{$survey->paymentType}</div>
            </div>
            <div class="field">
                <div class="field-label">Имя</div>
                <div class="field-value">{$survey->donorName}</div>
            </div>
            <div class="field">
                <div class="field-label">Email</div>
                <div class="field-value"><a href="mailto:{$survey->email}">{$survey->email}</a></div>
            </div>
            <div class="field">
                <div class="field-label">Комментарий</div>
                <div class="field-value">{$comment}</div>
            </div>
            <div class="field">
                <div class="field-label">Дата отправки</div>
                <div class="field-value">{$submittedAt}</div>
            </div>
        </div>
        <div class="footer">
            <p>Это автоматическое сообщение. Пожалуйста, не отвечайте на него.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Построить тело email для пользователя
     */
    private function buildUserEmailBody(SurveyWantHelpEntity $survey): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #28a745; color: white; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .content { background-color: #f9f9f9; padding: 20px; border-radius: 5px; }
        .footer { margin-top: 20px; font-size: 12px; color: #999; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Спасибо, что помогаете!</h2>
        </div>
        <div class="content">
            <p>Здравствуйте, {$survey->donorName}!</p>
            <p>Огромное спасибо за вашу заявку на пожертвование!</p>
            <p>Мы благодарны вам за помощь и поддержку. Ваш вклад помогает нам продолжать работу по поддержке пациентов с редкими заболеваниями.</p>
            <p><strong>Детали вашей заявки:</strong></p>
            <p>
                <strong>Сумма:</strong> {$survey->amount} руб.<br>
                <strong>Тип платежа:</strong> {$survey->paymentType}
            </p>
            <p>Вскоре с вами свяжется наша команда для дальнейших действий.</p>
            <p>С благодарностью,<br>Команда ВООЗ</p>
        </div>
        <div class="footer">
            <p>Это автоматическое сообщение. Пожалуйста, не отвечайте на него.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Подготовить данные для CloudPayments виджета
     *
     * @param int $elementId ID элемента донации в инфоблоке
     * @param SurveyWantHelpEntity $survey Данные донации
     * @return array Данные для виджета CloudPayments
     */
    private function preparePaymentData(int $elementId, SurveyWantHelpEntity $survey): array
    {
        /** @var CloudPaymentsService $cloudPaymentsService */
        $cloudPaymentsService = app('cloudpayments');

        // Генерируем InvoiceId
        $invoiceId = $cloudPaymentsService->generateInvoiceId($elementId);

        // Определяем, нужен ли рекуррентный платеж (для ежемесячных пожертвований)
        $isRecurrent = $survey->paymentType === 'Ежемесячный платеж';

        // Формируем описание платежа
        $description = sprintf(
            'Пожертвование ВООЗ - %s',
            $isRecurrent ? 'Ежемесячное' : 'Единоразовое'
        );

        // Подготавливаем данные для виджета
        $widgetData = $cloudPaymentsService->prepareWidgetData(
            amount: (float)$survey->amount,
            currency: 'RUB',
            invoiceId: $invoiceId,
            description: $description,
            email: $survey->email,
            name: $survey->donorName,
            recurrent: $isRecurrent
        );

        return $widgetData;
    }
}
