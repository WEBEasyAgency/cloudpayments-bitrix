<?php

/**
 * Конфигурация инфоблока "Анкеты: Хочу помочь"
 * Тип: vooz_forms
 * Назначение: Сохранение заявок на пожертвование
 */

return [
    'version' => '1.0',
    'need_sync' => true,
    'strict' => false,

    'iblock' => [
        'type' => 'vooz_forms',
        'code' => 'survey_want_help',
        'name' => 'Анкеты: Хочу помочь (Донаты)',
        'active' => 'Y',
        'sort' => 110,
        'description' => 'Заявки на пожертвование',
        'messages' => [
            'ELEMENT_NAME' => 'Заявка',
            'ELEMENTS_NAME' => 'Заявки',
        ],
    ],

    'properties' => [
        // Обязательные поля
        'AMOUNT' => [
            'name' => 'Сумма пожертвования (руб.)',
            'type' => 'S',
            'sort' => 100,
        ],

        'PAYMENT_TYPE' => [
            'name' => 'Тип платежа',
            'type' => 'L',
            'sort' => 110,
            'VALUES' => [
                ['VALUE' => 'Единоразовый платеж', 'XML_ID' => 'one_time', 'SORT' => 100],
                ['VALUE' => 'Ежемесячный платеж', 'XML_ID' => 'monthly', 'SORT' => 200],
            ],
        ],

        'DONOR_NAME' => [
            'name' => 'Ваше имя',
            'type' => 'S',
            'sort' => 120,
        ],

        'EMAIL' => [
            'name' => 'Электронная почта',
            'type' => 'S',
            'sort' => 130,
        ],

        // Опциональные поля
        'COMMENT' => [
            'name' => 'Ваш комментарий',
            'type' => 'S',
            'sort' => 140,
            'user_type' => 'HTML',
        ],

        // Служебные поля
        'SUBMITTED_AT' => [
            'name' => 'Дата отправки',
            'type' => 'S',
            'sort' => 150,
        ],

        'CLOUDPAYMENT_STATUS' => [
            'name' => 'Статус платежа (CloudPayment)',
            'type' => 'L',
            'sort' => 160,
            'VALUES' => [
                ['VALUE' => 'Ожидание', 'XML_ID' => 'pending', 'SORT' => 100],
                ['VALUE' => 'Успешно', 'XML_ID' => 'success', 'SORT' => 200],
                ['VALUE' => 'Отклонено', 'XML_ID' => 'rejected', 'SORT' => 300],
            ],
        ],
    ],

    'fields' => [
        'ID' => ['type' => 'standard'],
        'CODE' => ['type' => 'standard'],
        'NAME' => ['type' => 'standard', 'required' => true],
        'ACTIVE' => ['type' => 'standard'],
        'ACTIVE_FROM' => ['type' => 'standard'],
    ],

    'demo_data' => [],
];
