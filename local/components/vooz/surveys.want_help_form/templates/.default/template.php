<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

// Подключаем CloudPayments Widget SDK
$APPLICATION->AddHeadScript('https://widget.cloudpayments.ru/bundles/cloudpayments.js');
?>

<div class="bank-card">
        <div class="container">
            <div class="title">
                <h4>Банковской картой</h4>
            </div>
            <div class="bank-card-inner form-block">
                <form id="<?= htmlspecialchars($arResult['FORM_ID']) ?>" method="post">
                    <div class="grid">
                        <div class="form-inner">
                            <div class="caption">Выберите тип платежа</div>
                            <div class="period-list">
                                <label class="radio">
                                    <input type="radio" name="paymentType" value="Ежемесячный платеж" checked>
                                    <span class="label">Ежемесячный платеж</span>
                                </label>
                                <label class="radio">
                                    <input type="radio" name="paymentType" value="Единоразовый платеж">
                                    <span class="label">Единоразовый платеж</span>
                                </label>
                            </div>
                            <div class="sum-list">
                                <?php foreach ($arResult['PRESET_AMOUNTS'] as $amount): ?>
                                    <label class="radio">
                                        <input type="radio" name="amount" value="<?= htmlspecialchars($amount) ?>" <?= ($amount === 500) ? 'checked' : '' ?>>
                                        <span class="label"><?= $amount ?>р</span>
                                    </label>
                                <?php endforeach; ?>
                                <div class="form-field other-sum">
                                    <input type="number" name="customAmount" placeholder="Другая сумма" min="1">
                                </div>
                            </div>
                            <div class="field-list">
                                <div class="form-field">
                                    <input type="text" name="donorName" placeholder="Ваше Имя" required>
                                </div>
                                <div class="form-field">
                                    <input type="email" name="email" placeholder="E-mail" required>
                                </div>
                                <div class="form-field textarea-field">
                                    <textarea name="comment" placeholder="Ваш комментарий" maxlength="1500"></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="thanx-block">
                            <div class="inner">
                                <div class="logo">
                                    <img src="/local/assets/img/dest/want-help-bank-logo.png" alt="">
                                </div>
                                <div class="text">
                                    <h3>Спасибо, что помогаете</h3>
                                </div>
                                <div class="img">
                                    <picture>
                                        <source media="(max-width: 1279px)" srcset="/local/assets/img/dest/want-help-bank-img-1024.png">
                                        <img src="/local/assets/img/dest/want-help-bank-img.png" alt="">
                                    </picture>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="policy-text">Нажимая на кнопку, я принимаю условия оферты и соглашаюсь с <a href="#">политикой обработки персональных данных</a></div>
                    <div class="btn-block">
                        <button type="submit" class="btn">Перейти к оплате</button>
                    </div>
                </form>
            </div>
        </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('<?= htmlspecialchars($arResult['FORM_ID']) ?>');
    if (!form) return;

    // Обработка выбора предустановленной суммы
    const amountRadios = form.querySelectorAll('input[name="amount"]');
    const customAmountInput = form.querySelector('input[name="customAmount"]');

    amountRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (customAmountInput) {
                customAmountInput.value = '';
            }
        });
    });

    // Обработка ввода произвольной суммы
    if (customAmountInput) {
        customAmountInput.addEventListener('input', function() {
            if (this.value) {
                // Снимаем выделение с предустановленных сумм
                amountRadios.forEach(radio => {
                    radio.checked = false;
                });
            }
        });
    }

    // Ограничение ввода для имени - только буквы, пробелы, тире
    const nameInput = form.querySelector('input[name="donorName"]');
    if (nameInput) {
        nameInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^а-яА-ЯёЁa-zA-Z\s\-]/g, '');
        });
    }

    // AJAX отправка формы
    form.addEventListener('submit', function(e) {
        e.preventDefault();

        // Определяем финальную сумму
        const customAmount = customAmountInput ? customAmountInput.value : '';
        const selectedAmount = form.querySelector('input[name="amount"]:checked');
        const amount = customAmount || (selectedAmount ? selectedAmount.value : '500');

        const formData = new FormData(form);
        formData.set('amount', amount);

        // Отключаем кнопку отправки
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Обработка...';

        fetch('<?= htmlspecialchars($arResult['FORM_ACTION']) ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.paymentData) {
                // Запускаем CloudPayments виджет для оплаты
                openCloudPaymentsWidget(data.paymentData, submitBtn, originalText);
            } else if (data.errors) {
                // Показываем ошибки валидации
                const errorMessages = Object.values(data.errors).join('\n');
                alert('Пожалуйста, исправьте ошибки:\n\n' + errorMessages);
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            } else {
                alert(data.message || 'Произошла ошибка при отправке заявки');
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Произошла ошибка при отправке заявки');
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        });
    });

    // Функция открытия CloudPayments виджета
    function openCloudPaymentsWidget(paymentData, submitBtn, originalText) {
        // Проверяем наличие CloudPayments SDK
        if (typeof cp === 'undefined') {
            console.error('CloudPayments SDK не загружен');
            alert('Ошибка загрузки платежной системы. Обновите страницу и попробуйте снова.');
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
            return;
        }

        // Создаем виджет CloudPayments
        const widget = new cp.CloudPayments();

        // Опции виджета
        const options = {
            publicId: paymentData.publicId,
            description: paymentData.description,
            amount: paymentData.amount,
            currency: paymentData.currency,
            invoiceId: paymentData.invoiceId,
            accountId: paymentData.accountId,
            email: paymentData.email,
            skin: paymentData.skin,
            language: paymentData.language,
            requireEmail: false,
            data: paymentData.data
        };

        // Обработчики событий
        const handlers = {
            onSuccess: function(options) {
                // Платеж успешен
                console.log('Payment success:', options);

                // Сбрасываем форму
                form.reset();
                if (customAmountInput) {
                    customAmountInput.value = '';
                }
                amountRadios[0].checked = true;

                // Показываем сообщение об успехе
                showSuccessPopup('Спасибо за пожертвование! Платеж успешно проведен. Вы получите подтверждение на email.');

                // Восстанавливаем кнопку
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            },
            onFail: function(reason, options) {
                // Платеж не прошел
                console.error('Payment failed:', reason, options);

                // Показываем сообщение об ошибке
                showErrorPopup('К сожалению, платеж не прошел. ' + (reason || 'Попробуйте другую карту или повторите попытку позже.'));

                // Восстанавливаем кнопку
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            },
            onComplete: function(paymentResult, options) {
                // Виджет закрыт (успех или неудача уже обработаны выше)
                console.log('Payment complete:', paymentResult, options);
            }
        };

        // Запускаем виджет
        widget.pay('charge', options, handlers);
    }

    // Простой popup для отображения ошибки
    function showErrorPopup(message) {
        const popup = document.createElement('div');
        popup.className = 'error-popup';
        popup.style.cssText = `
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            z-index: 10000;
            max-width: 500px;
            text-align: center;
            animation: slideIn 0.3s ease-out;
        `;
        popup.innerHTML = `
            <h3 style="margin: 0 0 15px 0; color: #dc3545;">Ошибка платежа</h3>
            <p style="margin: 0 0 20px 0; color: #666;">${message}</p>
            <button onclick="this.closest('.error-popup').remove()" style="
                padding: 10px 30px;
                background: #dc3545;
                color: white;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 14px;
            ">Закрыть</button>
        `;

        document.body.appendChild(popup);

        // Автоматически закрываем через 7 секунд
        setTimeout(() => {
            if (popup.parentNode) {
                popup.remove();
            }
        }, 7000);
    }

    // Простой popup для отображения успеха
    function showSuccessPopup(message) {
        const popup = document.createElement('div');
        popup.className = 'success-popup';
        popup.style.cssText = `
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            z-index: 10000;
            max-width: 500px;
            text-align: center;
            animation: slideIn 0.3s ease-out;
        `;
        popup.innerHTML = `
            <h3 style="margin: 0 0 15px 0; color: #282D3C;">Спасибо за помощь!</h3>
            <p style="margin: 0 0 20px 0; color: #666;">${message}</p>
            <button onclick="this.closest('.success-popup').remove()" style="
                padding: 10px 30px;
                background: #3E7ABE;
                color: white;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 14px;
            ">Закрыть</button>
        `;

        document.body.appendChild(popup);

        // Автоматически закрываем через 5 секунд
        setTimeout(() => {
            if (popup.parentNode) {
                popup.remove();
            }
        }, 5000);
    }
});
</script>

<style>
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translate(-50%, -60%);
    }
    to {
        opacity: 1;
        transform: translate(-50%, -50%);
    }
}
</style>
