# 1. استخدام صورة PHP مع Apache
FROM php:8.2-apache

# 2. تثبيت الإضافات اللازمة لعمل البوت (cURL و JSON مدمجة في هذه النسخة)
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    pkg-config \
    libssl-dev \
    && docker-php-ext-install curl

# 3. تفعيل مود الـ Rewrite في Apache (مهم لروابط الويب)
RUN a2enmod rewrite

# 4. نسخ ملفات المشروع إلى الحاوية
COPY . /var/www/html/

# 5. إنشاء المجلدات المطلوبة يدوياً للتأكد من وجودها قبل منح الصلاحيات
RUN mkdir -p /var/www/html/botmak \
             /var/www/html/user \
             /var/www/html/sudo \
             /var/www/html/wataw \
             /var/www/html/from_id \
             /var/www/html/data

# 6. منح الصلاحيات الكاملة للمجلدات (حل مشكلة chmod التي واجهتها)
RUN chmod -R 777 /var/www/html/botmak \
                 /var/www/html/user \
                 /var/www/html/sudo \
                 /var/www/html/wataw \
                 /var/www/html/from_id \
                 /var/www/html/data

# 7. تغيير مالك الملفات ليكون خادم Apache
RUN chown -R www-data:www-data /var/www/html

# 8. تحديد المنفذ (Railway يستخدم غالباً 8080 أو المتغير $PORT)
EXPOSE 80

# 9. أمر التشغيل الافتراضي
CMD ["apache2-foreground"]
