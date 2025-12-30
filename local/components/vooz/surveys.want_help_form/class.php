<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;
use App\Infrastructure\Bitrix\BaseComponent;

/**
 * Компонент формы "Хочу помочь" (пожертвование)
 * Выводит HTML форму пожертвования и обрабатывает AJAX отправку
 */
class VoozSurveysWantHelpFormComponent extends BaseComponent
{
    public function executeComponent()
    {
        if (!Loader::includeModule('iblock')) {
            $this->arResult['ERROR'] = 'Модуль iblock не установлен';
            return;
        }

        $this->arResult['FORM_ID'] = 'want-help-form';
        $this->arResult['FORM_ACTION'] = '/local/handlers/survey_want_help_handler.php';

        // Значения предустановленных сумм
        $this->arResult['PRESET_AMOUNTS'] = [500, 1000, 2000, 5000];

        // CloudPayments Public ID для виджета
        $cloudPaymentsService = app('cloudpayments');
        if ($cloudPaymentsService) {
            $this->arResult['CLOUDPAYMENTS_PUBLIC_ID'] = $cloudPaymentsService->getPublicId();
            $this->arResult['CLOUDPAYMENTS_TEST_MODE'] = $cloudPaymentsService->isTestMode();
        }

        parent::executeComponent();
    }
}
