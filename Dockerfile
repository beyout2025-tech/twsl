# 1. استخدام نسخة PHP الرسمية مع Apache
FROM php:8.2-apache

# 2. تحديث المستودعات وتثبيت المكتبات الضرورية لنظام تليجرام و cURL
RUN apt-get update && apt-get install -y \
    libicu-dev \
    libpq-dev \
    libzip-dev \
    unzip \
    zip \
    curl \
    && rm -rf /var/lib/apt/lists/*

# 3. تثبيت ملحقات PHP المطلوبة (cURL مدمج، ونضيف الملحقات الشائعة)
RUN docker-php-ext-install \
    intl \
    mysqli \
    pdo \
    pdo_mysql \
    zip

# 4. تفعيل موديل Apache Rewrite (مهم لمسارات الـ Webhook)
RUN a2enmod rewrite

# 5. ضبط إعدادات PHP للإنتاج (تحسين الأداء)
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# 6. تحديد مجلد العمل داخل الحاوية
WORKDIR /var/www/html

# 7. نسخ الكود المصدري إلى الحاوية
COPY . /var/www/html

# 8. ضبط الصلاحيات لمجلد data و cache (ضروري جداً لعمل البوت)
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/data \
    && chmod -R 775 /var/www/html/cache

# 9. فتح المنفذ 80 (Apache)
EXPOSE 80

# 10. تشغيل Apache في الواجهة
CMD ["apache2-foreground"]
