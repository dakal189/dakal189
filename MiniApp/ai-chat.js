// تنظیمات اولیه Telegram Web App
let tg = window.Telegram.WebApp;

// راه‌اندازی چت
document.addEventListener('DOMContentLoaded', function() {
    initializeChat();
});

function initializeChat() {
    // تنظیمات اولیه
    tg.ready();
    tg.expand();
    
    // تنظیم رنگ‌های اصلی
    tg.setHeaderColor('#6366f1');
    tg.setBackgroundColor('#f8fafc');
    
    // نمایش دکمه بازگشت
    showBackButton();
    
    // تنظیم event listeners
    setupChatEventListeners();
    
    // اسکرول به پایین
    scrollToBottom();
}

function setupChatEventListeners() {
    // فوکوس روی input
    document.getElementById('messageInput').focus();
    
    // تنظیم auto-resize برای textarea
    const textarea = document.getElementById('messageInput');
    textarea.addEventListener('input', function() {
        autoResize(this);
    });
}

function autoResize(textarea) {
    textarea.style.height = 'auto';
    textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
}

function handleKeyPress(event) {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        sendMessage();
    }
}

function sendQuickMessage(message) {
    // اضافه کردن پیام کاربر
    addMessage(message, 'user');
    
    // ارسال به AI
    sendToAI(message);
}

function sendMessage() {
    const input = document.getElementById('messageInput');
    const message = input.value.trim();
    
    if (!message) return;
    
    // اضافه کردن پیام کاربر
    addMessage(message, 'user');
    
    // پاک کردن input
    input.value = '';
    autoResize(input);
    
    // ارسال به AI
    sendToAI(message);
}

function sendToAI(message) {
    // نمایش تایپینگ
    showTyping();
    
    // غیرفعال کردن دکمه ارسال
    const sendButton = document.getElementById('sendButton');
    sendButton.disabled = true;
    
    // ارسال به سرور
    fetch('/api/ai-chat.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            message: message,
            user_id: tg.initDataUnsafe?.user?.id,
            context: getChatContext()
        })
    })
    .then(response => response.json())
    .then(data => {
        hideTyping();
        
        if (data.success) {
            addMessage(data.response, 'ai');
        } else {
            addMessage('متأسفانه خطایی رخ داد. لطفاً دوباره تلاش کنید.', 'ai');
        }
        
        // فعال کردن دکمه ارسال
        sendButton.disabled = false;
    })
    .catch(error => {
        console.error('خطا در ارتباط با AI:', error);
        hideTyping();
        addMessage('خطا در ارتباط با سرور. لطفاً اتصال اینترنت خود را بررسی کنید.', 'ai');
        sendButton.disabled = false;
    });
}

function addMessage(text, sender) {
    const chatMessages = document.getElementById('chatMessages');
    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${sender}`;
    
    // تبدیل لینک‌ها به کلیک‌پذیر
    const formattedText = formatMessage(text);
    messageDiv.innerHTML = formattedText;
    
    // اضافه کردن زمان
    const timeDiv = document.createElement('div');
    timeDiv.className = 'message-time';
    timeDiv.textContent = formatRelativeTime(Math.floor(Date.now() / 1000));
    messageDiv.appendChild(timeDiv);
    
    chatMessages.appendChild(messageDiv);
    scrollToBottom();
    
    // حذف پیام‌های سریع بعد از اولین پیام کاربر
    if (sender === 'user') {
        const quickActions = document.querySelector('.quick-actions');
        if (quickActions) {
            quickActions.style.display = 'none';
        }
    }
}

function formatMessage(text) {
    // تبدیل لینک‌ها
    text = text.replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" target="_blank" style="color: inherit; text-decoration: underline;">$1</a>');
    
    // تبدیل خط جدید
    text = text.replace(/\n/g, '<br>');
    
    // تبدیل ایموجی‌ها
    text = text.replace(/:\)/g, '😊');
    text = text.replace(/:\(/g, '😢');
    text = text.replace(/;\)/g, '😉');
    text = text.replace(/:\|/g, '😐');
    
    return text;
}

function showTyping() {
    const chatMessages = document.getElementById('chatMessages');
    const typingDiv = document.createElement('div');
    typingDiv.className = 'message typing';
    typingDiv.id = 'typing';
    typingDiv.innerHTML = `
        <div class="typing-dots">
            <div class="typing-dot"></div>
            <div class="typing-dot"></div>
            <div class="typing-dot"></div>
        </div>
        در حال تایپ...
    `;
    
    chatMessages.appendChild(typingDiv);
    scrollToBottom();
}

function hideTyping() {
    const typing = document.getElementById('typing');
    if (typing) {
        typing.remove();
    }
}

function scrollToBottom() {
    const chatMessages = document.getElementById('chatMessages');
    setTimeout(() => {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }, 100);
}

function getChatContext() {
    // دریافت تاریخچه چت برای context بهتر
    const messages = document.querySelectorAll('.message');
    const context = [];
    
    messages.forEach(message => {
        if (message.classList.contains('user')) {
            context.push({ role: 'user', content: message.textContent });
        } else if (message.classList.contains('ai')) {
            context.push({ role: 'assistant', content: message.textContent });
        }
    });
    
    return context.slice(-10); // آخرین 10 پیام
}

// پاسخ‌های پیش‌فرض برای سوالات رایج
const defaultResponses = {
    'چطور ربات بسازم؟': `🤖 برای ساخت ربات در Dakal:

1️⃣ ابتدا به ربات اصلی @Creatorbotdakalbot مراجعه کنید
2️⃣ دستور /start را ارسال کنید
3️⃣ از منوی "ساخت ربات جدید" استفاده کنید
4️⃣ نام و توکن ربات خود را وارد کنید
5️⃣ تنظیمات اولیه را انجام دهید

✅ ربات شما آماده خواهد بود!`,

    'مشکلات رایج': `⚠️ مشکلات رایج و راه‌حل‌ها:

🔸 ربات پاسخ نمی‌دهد:
• توکن را بررسی کنید
• ربات را restart کنید
• اتصال اینترنت را چک کنید

🔸 خطای 403:
• ربات را unblock کنید
• دسترسی‌ها را بررسی کنید

🔸 پیام‌ها ارسال نمی‌شوند:
• محدودیت‌های تلگرام را بررسی کنید
• از flood control استفاده کنید`,

    'بهینه‌سازی': `⚡ نکات بهینه‌سازی ربات:

🚀 عملکرد:
• از کش استفاده کنید
• کوئری‌های دیتابیس را بهینه کنید
• فایل‌های بزرگ را فشرده کنید

🔒 امنیت:
• توکن‌ها را محافظت کنید
• ورودی‌ها را validate کنید
• از HTTPS استفاده کنید

📊 آمار:
• لاگ‌ها را بررسی کنید
• عملکرد را مانیتور کنید
• خطاها را رفع کنید`,

    'قیمت‌گذاری': `💰 قیمت‌های Dakal:

🆓 رایگان:
• 1 ربات
• 100 کاربر
• امکانات پایه

💎 VIP (20,000 تومان/ماه):
• 10 ربات
• کاربران نامحدود
• امکانات پیشرفته
• پشتیبانی 24/7

💎 Premium (50,000 تومان/ماه):
• ربات‌های نامحدود
• API اختصاصی
• سفارشی‌سازی کامل
• پشتیبانی فوری`
};

// تابع برای بررسی پاسخ‌های پیش‌فرض
function checkDefaultResponse(message) {
    const lowerMessage = message.toLowerCase();
    
    for (const [key, response] of Object.entries(defaultResponses)) {
        if (lowerMessage.includes(key.toLowerCase()) || 
            key.toLowerCase().includes(lowerMessage)) {
            return response;
        }
    }
    
    return null;
}

// تابع برای نمایش دکمه بازگشت
function showBackButton() {
    tg.BackButton.show();
    tg.BackButton.onClick(() => {
        goBack();
    });
}

// تابع برای بازگشت
function goBack() {
    window.location.href = 'index.html';
}

// تابع برای نمایش زمان نسبی
function formatRelativeTime(timestamp) {
    const now = Math.floor(Date.now() / 1000);
    const diff = now - timestamp;
    
    if (diff < 60) {
        return 'همین الان';
    } else if (diff < 3600) {
        return `${Math.floor(diff / 60)} دقیقه پیش`;
    } else if (diff < 86400) {
        return `${Math.floor(diff / 3600)} ساعت پیش`;
    } else if (diff < 2592000) {
        return `${Math.floor(diff / 86400)} روز پیش`;
    } else {
        const date = new Date(timestamp * 1000);
        return date.toLocaleDateString('fa-IR');
    }
}

// تابع برای کپی کردن متن
function copyMessage(text) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
            showNotification('متن کپی شد');
        });
    } else {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        showNotification('متن کپی شد');
    }
}

// تابع برای نمایش نوتیفیکیشن
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.classList.add('show');
    }, 100);
    
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 300);
    }, 3000);
}

// تابع برای اشتراک‌گذاری چت
function shareChat() {
    const messages = document.querySelectorAll('.message');
    let chatText = '💬 چت با Dakal AI:\n\n';
    
    messages.forEach(message => {
        if (message.classList.contains('user')) {
            chatText += `👤 شما: ${message.textContent}\n\n`;
        } else if (message.classList.contains('ai')) {
            chatText += `🤖 AI: ${message.textContent}\n\n`;
        }
    });
    
    if (navigator.share) {
        navigator.share({
            title: 'چت با Dakal AI',
            text: chatText,
            url: window.location.href
        });
    } else {
        copyMessage(chatText);
    }
}

// تابع برای پاک کردن چت
function clearChat() {
    const chatMessages = document.getElementById('chatMessages');
    const welcomeMessage = chatMessages.querySelector('.message.ai');
    const quickActions = chatMessages.querySelector('.quick-actions');
    
    // پاک کردن همه پیام‌ها به جز پیام خوشامدگویی
    const messages = chatMessages.querySelectorAll('.message:not(:first-child)');
    messages.forEach(message => message.remove());
    
    // نمایش مجدد پیام‌های سریع
    if (quickActions) {
        quickActions.style.display = 'flex';
    }
    
    showNotification('چت پاک شد');
}

// تنظیم event listener برای تغییر اندازه صفحه
window.addEventListener('resize', function() {
    tg.expand();
});

// تنظیم event listener برای focus/blur
window.addEventListener('focus', function() {
    // فوکوس روی input
    document.getElementById('messageInput').focus();
});

console.log('🧠 Dakal AI Chat Loaded Successfully!');