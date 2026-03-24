<?php

// --- الإعدادات الأساسية ---
$token = "8532487667:AAGeWhNyLZri9BxZMCw3AQZaJmOI5OVdxkE";
$admin_group_id = "-1002447990422"; // ضع هنا آيدي المجموعة (يجب أن يبدأ بـ -100)
$api_url = "https://api.telegram.org/bot" . $token;

// استقبال البيانات
$content = file_get_contents("php://input");
$update = json_decode($content, true);
if (!$update) exit;

// معالجة الضغط على أزرار لوحة التحكم (Callback Query)
if (isset($update['callback_query'])) {
    handleCallback($update['callback_query']);
    exit;
}

$message = $update['message'] ?? null;
if (!$message) exit;

$chat_id = $message['chat']['id'];
$user_id = $message['from']['id'];
$user_name = htmlspecialchars($message['from']['first_name'] ?? "مستخدم");
$text = $message['text'] ?? "";
$reply_to = $message['reply_to_message'] ?? null;

// إنشاء المجلدات اللازمة
if (!is_dir('data')) mkdir('data', 0777, true);

// --- 1. نظام منع السبام ---
if ($chat_id > 0 && $chat_id != $admin_group_id) {
    $cooldown_file = "data/cooldown_$user_id.txt";
    if (file_exists($cooldown_file) && (time() - file_get_contents($cooldown_file)) < 3) exit;
    file_put_contents($cooldown_file, time());
}

// --- 2. منطق الإدارة (داخل المجموعة) ---
if ($chat_id == $admin_group_id) {
    // فتح لوحة التحكم عند كتابة "اللوحة" أو /admin
    if ($text == "/admin" || $text == "اللوحة") {
        sendAdminDashboard($admin_group_id);
    }
    
    // الرد على المستخدمين عبر الـ Reply
    if ($reply_to && preg_match('/ID:\s*`?(\d+)`?/', $reply_to['text'], $matches)) {
        $target_user_id = $matches[1];
        $res = sendTelegram('sendMessage', [
            'chat_id' => $target_user_id,
            'text' => "💬 **رد من الإدارة:**\n\n" . $text . "\n\n—\n🙏 نسعد بخدمتك دائماً.",
            'parse_mode' => 'Markdown'
        ]);
        
        $res_arr = json_decode($res, true);
        $status = $res_arr['ok'] ? "✅ تم إرسال الرد." : "❌ فشل: " . $res_arr['description'];
        sendTelegram('sendMessage', ['chat_id' => $admin_group_id, 'text' => $status, 'reply_to_message_id' => $message['message_id']]);
    }
    exit;
}

// --- 3. منطق المستخدم ---

// أ. إشعار دخول مستخدم جديد + رسالة الترحيب
if ($text == "/start") {
    // تسجيل المستخدم في القائمة
    saveUser($user_id, $user_name);
    
    // إرسال إشعار للمجموعة
    sendTelegram('sendMessage', [
        'chat_id' => $admin_group_id,
        'text' => "🔔 **إشعار دخول جديد**\n👤 الاسم: $user_name\n🆔 ID: `$user_id`\n🕒 الوقت: " . date("Y-m-d H:i"),
        'parse_mode' => 'Markdown'
    ]);

    $welcome_msg = "👋 أهلاً بك يا $user_name في بوت التواصل.\n\n"
                 . "📩 أرسل رسالتك الآن وسيتم الرد عليك من قبل الفريق الإداري.";
                 
    sendTelegram('sendMessage', [
        'chat_id' => $chat_id,
        'text' => $welcome_msg,
        'parse_mode' => 'Markdown',
        'reply_markup' => json_encode([
            'keyboard' => [[['text' => "📩 تواصل مع الدعم"]], [['text' => "❌ إلغاء"]]],
            'resize_keyboard' => true
        ])
    ]);
    exit;
}

// ب. الأزرار الوظيفية للمستخدم
if ($text == "📩 تواصل مع الدعم") {
    sendTelegram('sendMessage', ['chat_id' => $chat_id, 'text' => "✍️ اكتب رسالتك الآن..", 'parse_mode' => 'Markdown']);
    exit;
}

// ج. تحويل الرسالة للمجموعة
sendTelegram('sendChatAction', ['chat_id' => $chat_id, 'action' => 'typing']);

$forward_res = sendTelegram('forwardMessage', [
    'chat_id' => $admin_group_id,
    'from_chat_id' => $chat_id,
    'message_id' => $message['message_id']
]);
$forward_data = json_decode($forward_res, true);

if ($forward_data['ok']) {
    sendTelegram('sendMessage', [
        'chat_id' => $admin_group_id,
        'text' => "📩 **رسالة من:** $user_name\n🆔 ID: `$user_id`\n—\n⚠️ الرد عبر الـ Reply على هذه الرسالة.",
        'parse_mode' => 'Markdown',
        'reply_to_message_id' => $forward_data['result']['message_id']
    ]);
}

sendTelegram('sendMessage', [
    'chat_id' => $chat_id,
    'text' => "✅ تم استلام رسالتك وجاري مراجعتها..",
    'parse_mode' => 'Markdown'
]);

// --- وظائف لوحة التحكم والإدارة ---

function sendAdminDashboard($chat_id) {
    $stats = getStats();
    $keyboard = [
        'inline_keyboard' => [
            [['text' => "📊 الإحصائيات", 'callback_data' => 'stats'], ['text' => "👥 المستخدمين", 'callback_data' => 'users']],
            [['text' => "📢 إرسال جماعي", 'callback_data' => 'broadcast'], ['text' => "⚙️ الإعدادات", 'callback_data' => 'settings']],
            [['text' => "🔒 إغلاق اللوحة", 'callback_data' => 'close']]
        ]
    ];
    
    global $api_url;
    file_get_contents($api_url . "/sendMessage?chat_id=$chat_id&text=" . urlencode("🎮 **لوحة تحكم الإدارة**\nمرحباً بك في وحدة التحكم المركزية.") . "&parse_mode=Markdown&reply_markup=" . json_encode($keyboard));
}

function handleCallback($query) {
    $data = $query['data'];
    $chat_id = $query['message']['chat']['id'];
    $msg_id = $query['message']['message_id'];
    global $api_url;

    if ($data == 'stats') {
        $stats = getStats();
        $txt = "📈 **إحصائيات البوت:**\n\n👥 عدد المشتركين: " . $stats['total_users'];
        file_get_contents($api_url . "/answerCallbackQuery?callback_query_id=" . $query['id'] . "&text=تم التحديث");
        file_get_contents($api_url . "/editMessageText?chat_id=$chat_id&message_id=$msg_id&text=" . urlencode($txt) . "&parse_mode=Markdown&reply_markup=" . $query['message']['reply_markup']);
    }
    if ($data == 'close') {
        file_get_contents($api_url . "/deleteMessage?chat_id=$chat_id&message_id=$msg_id");
    }
}

function saveUser($id, $name) {
    $file = 'data/users.json';
    $users = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
    $users[$id] = ['name' => $name, 'date' => date("Y-m-d")];
    file_put_contents($file, json_encode($users));
}

function getStats() {
    $file = 'data/users.json';
    $users = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
    return ['total_users' => count($users)];
}

function sendTelegram($method, $data) {
    global $api_url;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url . "/" . $method);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res;
}
