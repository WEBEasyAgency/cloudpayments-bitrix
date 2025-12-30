# Публикация на GitHub

## Шаги для публикации

### 1. Создайте репозиторий на GitHub

1. Войдите на https://github.com
2. Нажмите **New repository**
3. Заполните:
   - Name: `cloudpayments-bitrix`
   - Description: `CloudPayments integration for Bitrix CMS (production-ready)`
   - Public
   - **НЕ добавляйте** README, .gitignore, license (уже есть)
4. **Create repository**

### 2. Подключите удаленный репозиторий

```bash
cd /d/CODE/cloudpayments-bitrix

# Добавьте remote (замените YOUR_USERNAME)
git remote add origin https://github.com/YOUR_USERNAME/cloudpayments-bitrix.git

# Переименуйте ветку в main
git branch -M main

# Отправьте код
git push -u origin main
```

### 3. Готово!

Теперь другие могут клонировать ваш репозиторий:

```bash
git clone https://github.com/YOUR_USERNAME/cloudpayments-bitrix.git
```

## Обновление репозитория

Когда вносите изменения:

```bash
git add .
git commit -m "Описание изменений"
git push
```

## Безопасность

⚠️ **ВАЖНО**: Убедитесь что `.env` в `.gitignore` и **НИКОГДА** не коммитьте реальные API ключи!
