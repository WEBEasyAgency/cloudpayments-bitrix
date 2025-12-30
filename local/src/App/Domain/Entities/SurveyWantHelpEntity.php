<?php

namespace App\Domain\Entities;

class SurveyWantHelpEntity
{
    public function __construct(
        // Обязательные поля
        public string $amount,          // Сумма (строка, т.к. может быть и "500" и "другое значение")
        public string $paymentType,     // Тип платежа: "Единоразовый платеж" или "Ежемесячный платеж"
        public string $donorName,       // Имя донатора
        public string $email,           // Email
        // Опциональные поля
        public string $comment = '',    // Комментарий
    ) {
    }

    /**
     * Валидация данных
     */
    public function validate(): array
    {
        $errors = [];

        // Сумма - обязательное поле, только цифры, больше 0
        if (empty(trim($this->amount))) {
            $errors['amount'] = 'Сумма обязательна';
        } elseif (!is_numeric($this->amount)) {
            $errors['amount'] = 'Сумма должна быть числом';
        } elseif ((float)$this->amount <= 0) {
            $errors['amount'] = 'Сумма должна быть больше 0';
        }

        // Тип платежа - обязательное поле
        if (empty(trim($this->paymentType))) {
            $errors['paymentType'] = 'Выберите тип платежа';
        } elseif (!in_array($this->paymentType, ['Единоразовый платеж', 'Ежемесячный платеж'])) {
            $errors['paymentType'] = 'Неверный тип платежа';
        }

        // Имя - обязательное поле, только буквы/тире
        if (empty(trim($this->donorName))) {
            $errors['donorName'] = 'Имя обязательно';
        } elseif (!preg_match('/^[а-яА-ЯёЁa-zA-Z\s\-]+$/u', $this->donorName)) {
            $errors['donorName'] = 'Имя может содержать только буквы, пробелы и тире';
        }

        // Email - обязательное поле, наличие @
        if (empty(trim($this->email))) {
            $errors['email'] = 'Email обязателен';
        } elseif (strpos($this->email, '@') === false) {
            $errors['email'] = 'Email должен содержать символ @';
        } elseif (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Некорректный email';
        }

        // Комментарий - опциональное, макс 1500 символов
        if (strlen($this->comment) > 1500) {
            $errors['comment'] = 'Максимум 1500 символов';
        }

        return $errors;
    }

    /**
     * Валидна ли сущность
     */
    public function isValid(): bool
    {
        return empty($this->validate());
    }

    /**
     * Получить массив данных для сохранения
     */
    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'paymentType' => $this->paymentType,
            'donorName' => $this->donorName,
            'email' => $this->email,
            'comment' => $this->comment,
        ];
    }

    /**
     * Получить текстовое представление типа платежа (для БД)
     */
    public function getPaymentTypeEnum(): string
    {
        $map = [
            'Единоразовый платеж' => 'one_time',
            'Ежемесячный платеж' => 'monthly',
        ];
        return $map[$this->paymentType] ?? 'one_time';
    }
}
