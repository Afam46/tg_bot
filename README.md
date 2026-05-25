# 🤖 AI Telegram Assistant

**Многофункциональный Telegram-бот с AI-ассистентом, системой задач и прогнозом погоды.**

## 🚀 Попробовать
**[@ai_afam_bot](https://t.me/ai_afam_bot)**

## ✨ Возможности

### 🤖 AI-ассистент
- Интеграция с **DeepSeek-V4** (через внешний API)
- Генерация ответов в режиме диалога
- Имитация печати (`typing` action)

### 📋 Система задач
- Создание, выполнение, список активных задач (CRUD)
- Экспорт задач в Excel
- Импорт задач из Excel
- Асинхронная обработка через Laravel Queue + Supervisor
- Inline-клавиатуры для выполнения задач
- Хранение в БД (MySQL)

### 🌤️ Погода
- Интеграция с **OpenWeatherMap API**
- Текущая температура, ощущения, влажность, ветер

### ⚙️ Backend архитектура
- Telegram webhook
- State machine
- Supervisor workers
- VPS deployment
- HTTPS + Nginx

## 🛠 Технологии
- PHP 8.4 (Laravel 13)
- MySQL
- Laravel Queue
- Supervisor
- Telegram Bot API
- DeepSeek API
- OpenWeatherMap API
- Nginx + SSL
