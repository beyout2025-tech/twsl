<?php

// --- الإعدادات الأساسية ---
$token = "8532487667:AAGeWhNyLZri9BxZMCw3AQZaJmOI5OVdxkE";
$admin_id = "7607952642";
$api_url = "https://api.telegram.org/bot" . $token;

// استقبال البيانات
$content = file_get_contents("php://input");
$update = json_decode($content, true);
if (!$update) exit;

$message = $update['message'] ?? null;
if (!$message) exit;

$chat_id = $message['chat']['id'];
$user_id = $message['from']['id'];
$user_name = htmlspecialchars($message['from']['first_name'] ?? "مستخدم");
$text = $message['text'] ?? "";
$reply_to = $message['reply_to_message'] ?? null;

// --- 1. نظام منع السبام (Cooldown - 3 ثوانٍ) ---
if ($chat_id != $admin_id) {
    $cooldown_file = "cache/cooldown_$user_id.txt";
    if (!is_dir('cache')) mkdir('cache', 0777, true);
    
    if (file_exists($cooldown_file) && (time() - file_get_contents($cooldown_file)) < 3) {
        // بصمت ننهي السكربت لمنع استهلاك الموارد
        exit;
    }
    file_put_contents($cooldown_file, time());
}

// --- 2. إرسال حالة "جاري الكتابة" ---
sendTelegram('sendChatAction', ['chat_id' => $chat_id, 'action' => 'typing']);

// --- 3. منطق الأدمن (المطور) ---
if ($chat_id == $admin_id) {
    // تصحيح الـ Regex ليكون أدق وأبسط كما اقترحت
    if ($reply_to && preg_match('/ID:\s*`?(\d+)`?/', $reply_to['text'], $matches)) {
        $target_user_id = $matches[1];
        
        $res = sendTelegram('sendMessage', [
            'chat_id' => $target_user_id,
            'text' => "💬 **رد من الدعم الفني:**\n\n" . $text . "\n\n—\n🙏 إذا كان لديك استفسار إضافي، لا تتردد بالارسال.",
            'parse_mode' => 'Markdown'
        ]);

        $res_arr = json_decode($res, true);
        if ($res_arr['ok']) {
            sendTelegram('sendMessage', ['chat_id' => $admin_id, 'text' => "✅ تم إرسال الرد بنجاح للمستخدم.", 'reply_to_message_id' => $message['message_id']]);
        } else {
            sendTelegram('sendMessage', ['chat_id' => $admin_id, 'text' => "❌ فشل الإرسال: " . $res_arr['description']]);
        }
    } else {
        if ($text != "/start") {
            sendTelegram('sendMessage', ['chat_id' => $admin_id, 'text' => "💡 للرد، قم بعمل Reply على رسالة معلومات المستخدم التي تحتوي على الـ ID."]);
        }
    }
    // في حال كان الأدمن يستخدم البوت كأدمن فقط ننهي التنفيذ هنا
    if ($text != "/start") exit; 
}

// --- 4. منطق الأزرار (UX Interaction) ---
if ($text == "📩 تواصل مع الدعم") {
    sendTelegram('sendMessage', [
        'chat_id' => $chat_id,
        'text' => "✍️ **تفضل بكتابة رسالتك الآن..**\nسيتم تحويل كل ما ترسل (نص، صور، ملفات) إلى فريق الإدارة مباشرة.",
        'parse_mode' => 'Markdown'
    ]);
    exit;
}

if ($text == "❌ إلغاء") {
    sendTelegram('sendMessage', [
        'chat_id' => $chat_id,
        'text' => "تم إلغاء العملية 👍\nيمكنك البدء من جديد عبر /start",
        'reply_markup' => json_encode(['remove_keyboard' => true])
    ]);
    exit;
}

// --- 5. منطق المستخدم العام ---

// رسالة البداية المحدثة (Professional UX)
if ($text == "/start") {
    $ticket_id = rand(10000, 99999); // رقم طلب شبه حقيقي لكل جلسة
    $welcome_msg = "👋 أهلاً بك يا $user_name في منصة التواصل.\n\n"
                 . "📩 يمكنك إرسال استفسارك مباشرة، وسيتم الرد عليك من قبل الإدارة.\n"
                 . "⚡ حاول شرح مشكلتك بوضوح لضمان سرعة الاستجابة.\n\n"
                 . "🎫 **رقم طلبك الحالي:** #$ticket_id";
                 
    sendTelegram('sendMessage', [
        'chat_id' => $chat_id,
        'text' => $welcome_msg,
        'parse_mode' => 'Markdown',
        'reply_markup' => json_encode([
            'keyboard' => [
                [['text' => "📩 تواصل مع الدعم"]],
                [['text' => "❌ إلغاء"]]
            ],
            'resize_keyboard' => true
        ])
    ]);
    exit;
}

// --- 6. تحويل الرسالة للأدمن (دمج الـ UX للأدمن) ---
// أولاً: نقوم بعمل Forward للرسالة الأصلية (لحفظ المرفقات والوسائط)
$forward_res = sendTelegram('forwardMessage', [
    'chat_id' => $admin_id,
    'from_chat_id' => $chat_id,
    'message_id' => $message['message_id']
]);
$forward_data = json_decode($forward_res, true);

// ثانياً: نرسل رسالة المعلومات ونربطها بالـ Forward (Reply)
if ($forward_data['ok']) {
    $info_header = "📩 **رسالة جديدة واردة**\n"
                 . "👤 المستخدم: $user_name\n"
                 . "🆔 ID: `$user_id`\n"
                 . "—\n"
                 . "⚠️ **للرد:** استخدم خاصية الـ Reply على هذه الرسالة حصراً.";

    sendTelegram('sendMessage', [
        'chat_id' => $admin_id,
        'text' => $info_header,
        'parse_mode' => 'Markdown',
        'reply_to_message_id' => $forward_data['result']['message_id']
    ]);
}

// --- 7. تأكيد الاستلام النفسي للمستخدم ---
sendTelegram('sendMessage', [
    'chat_id' => $chat_id,
    'text' => "✅ **تم استلام رسالتك بنجاح**\n👨‍💻 فريق الدعم يراجع طلبك الآن..\n⏳ الرد عادة يكون خلال دقائق قليلة.",
    'parse_mode' => 'Markdown'
]);

// دالة الإرسال المركزية مع معالجة بسيطة للأخطاء
function sendTelegram($method, $data) {
    global $api_url;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url . "/" . $method);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    $res = curl_exec($ch);
    if (curl_errno($ch)) {
        return json_encode(['ok' => false, 'description' => curl_error($ch)]);
    }
    curl_close($ch);
    return $res;
}

