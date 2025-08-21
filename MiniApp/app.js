// تنظیمات اولیه Telegram Web App
let tg = window.Telegram.WebApp;

// راه‌اندازی مینی اپ
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
});

function initializeApp() {
    // تنظیمات اولیه
    tg.ready();
    tg.expand();
    
    // تنظیم رنگ‌های اصلی
    tg.setHeaderColor('#6366f1');
    tg.setBackgroundColor('#ffffff');
    
    // نمایش اطلاعات کاربر
    displayUserInfo();
    
    // لود کردن آمار
    loadStats();
    
    // تنظیم event listeners
    setupEventListeners();
}

function displayUserInfo() {
    const user = tg.initDataUnsafe?.user;
    if (user) {
        // نمایش نام کاربر در عنوان صفحه
        const userName = user.first_name || user.username || 'کاربر Dakal';
        document.title = `${userName} - Dakal`;
        
        // نمایش آواتار
        const avatarImg = document.getElementById('userAvatarImg');
        const avatarPlaceholder = document.getElementById('userAvatarPlaceholder');
        
        if (user.photo_url) {
            // اگر عکس پروفایل موجود باشد
            avatarImg.src = user.photo_url;
            avatarImg.style.display = 'block';
            avatarPlaceholder.style.display = 'none';
            
            // اضافه کردن event listener برای خطا
            avatarImg.onerror = function() {
                // اگر عکس لود نشد، از placeholder استفاده کن
                avatarImg.style.display = 'none';
                avatarPlaceholder.style.display = 'flex';
                avatarPlaceholder.textContent = '❓';
            };
        } else {
            // اگر عکس پروفایل موجود نباشد
            avatarImg.style.display = 'none';
            avatarPlaceholder.style.display = 'flex';
            avatarPlaceholder.textContent = '❓';
        }
    }
}

function findAICard() {
    // کارت تنظیمات AI را پیدا می‌کند
    const cards = document.querySelectorAll('.menu-card');
    for (const card of cards) {
        if (card.textContent.includes('تنظیمات AI')) return card;
    }
    return null;
}

function loadStats() {
    // نمایش لودینگ
    showLoading();
    
    // درخواست آمار از سرور
    fetch('/api/stats.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            user_id: tg.initDataUnsafe?.user?.id
        })
    })
    .then(response => response.json())
    .then(data => {
        updateStats(data);
        hideLoading();
    })
    .catch(error => {
        console.error('خطا در بارگذاری آمار:', error);
        hideLoading();
        showNotification('خطا در بارگذاری آمار', 'error');
    });
}

function updateStats(response) {
    // پشتیبانی از هر دو ساختار: {data:{...}} یا فلت
    const data = response?.data || response || {};
    document.getElementById('totalUsers').textContent = formatNumber(data.total_users || 0);
    document.getElementById('activeBots').textContent = formatNumber(data.active_bots || 0);
    document.getElementById('totalMessages').textContent = formatNumber(data.total_messages || 0);
}

function formatNumber(num) {
    if (num >= 1000000) {
        return (num / 1000000).toFixed(1) + 'M';
    } else if (num >= 1000) {
        return (num / 1000).toFixed(1) + 'K';
    }
    return num.toString();
}

function setupEventListeners() {
    // کلیک روی آواتار کاربر
    document.getElementById('userAvatar').addEventListener('click', function() {
        showUserProfile();
    });

    // کنترل نمایش کارت تنظیمات AI بر اساس نقش ادمین
    try {
        const userId = tg.initDataUnsafe?.user?.id;
        if (userId) {
            fetch('/api/meta.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user_id: userId })
            })
            .then(r => r.json())
            .then(meta => {
                if (!meta.is_admin) {
                    const aiCard = findAICard();
                    if (aiCard) aiCard.style.display = 'none';
                }
            })
            .catch(() => {});
        }
    } catch (e) {}
}

// توابع ناوبری
function openDashboard() {
    window.location.href = 'dashboard.html';
}

function openBotsManager() {
    window.location.href = 'bots-manager.html';
}

function openAIChat() {
    window.location.href = 'ai-chat.html';
}

function openSendMessage() {
    window.location.href = 'send-message.html';
}

function openUsers() {
    window.location.href = 'users.html';
}

function openSettings() {
    window.location.href = 'settings.html';
}

function openAISettings() {
    const userId = tg.initDataUnsafe?.user?.id;
    if (!userId) return;
    fetch('/api/meta.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: userId })
    })
    .then(r => r.json())
    .then(meta => {
        if (meta.is_admin) {
            window.location.href = 'ai-settings.html';
        } else {
            showNotification('دسترسی فقط برای ادمین مجاز است', 'error');
        }
    })
    .catch(() => showNotification('خطا در بررسی دسترسی', 'error'));
}

function showUserProfile() {
    // هدایت به صفحه پروفایل کامل
    window.location.href = 'profile.html';
}



// توابع کمکی
function showLoading() {
    const stats = document.querySelectorAll('.stat-number');
    stats.forEach(stat => {
        stat.innerHTML = '<div class="loading"></div>';
    });
}

function hideLoading() {
    // لودینگ در updateStats پاک می‌شود
}

function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    // نمایش نوتیفیکیشن
    setTimeout(() => {
        notification.classList.add('show');
    }, 100);
    
    // حذف نوتیفیکیشن
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 300);
    }, 3000);
}

// تابع برای بازگشت به صفحه اصلی
function goBack() {
    if (window.location.pathname.includes('index.html') || window.location.pathname.endsWith('/')) {
        tg.close();
    } else {
        window.location.href = 'index.html';
    }
}

// تابع برای بستن مینی اپ
function closeApp() {
    tg.close();
}

// تابع برای نمایش منوی اصلی تلگرام
function showMainMenu() {
    tg.MainButton.setText('منوی اصلی');
    tg.MainButton.show();
    tg.MainButton.onClick(() => {
        tg.close();
    });
}

// تابع برای نمایش دکمه بازگشت
function showBackButton() {
    tg.BackButton.show();
    tg.BackButton.onClick(() => {
        goBack();
    });
}

// تابع برای مخفی کردن دکمه‌ها
function hideButtons() {
    tg.MainButton.hide();
    tg.BackButton.hide();
}

// تنظیم دکمه‌ها برای صفحات مختلف
function setupPageButtons() {
    const currentPage = window.location.pathname;
    
    if (currentPage.includes('index.html') || currentPage.endsWith('/')) {
        hideButtons();
    } else {
        showBackButton();
    }
}

// فراخوانی setupPageButtons در هر صفحه
setupPageButtons();

// تابع برای ارسال داده به سرور
function sendToServer(endpoint, data) {
    // اطمینان از .php
    const url = endpoint.endsWith('.php') ? endpoint : `${endpoint}.php`;
    return fetch(`/api/${url}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            ...data,
            user_id: tg.initDataUnsafe?.user?.id
        })
    })
    .then(response => response.json())
    .catch(error => {
        console.error('خطا در ارتباط با سرور:', error);
        throw error;
    });
}

// تابع برای بررسی وضعیت اتصال
function checkConnection() {
    if (!navigator.onLine) {
        showNotification('اتصال اینترنت برقرار نیست', 'error');
        return false;
    }
    return true;
}

// تابع برای ذخیره تنظیمات محلی
function saveLocalSetting(key, value) {
    localStorage.setItem(`dakal_${key}`, JSON.stringify(value));
}

// تابع برای خواندن تنظیمات محلی
function getLocalSetting(key, defaultValue = null) {
    const value = localStorage.getItem(`dakal_${key}`);
    return value ? JSON.parse(value) : defaultValue;
}

// تابع برای پاک کردن تنظیمات محلی
function clearLocalSettings() {
    const keys = Object.keys(localStorage);
    keys.forEach(key => {
        if (key.startsWith('dakal_')) {
            localStorage.removeItem(key);
        }
    });
}

// تابع برای نمایش تاریخ شمسی
function formatPersianDate(timestamp) {
    const date = new Date(timestamp * 1000);
    const options = {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    };
    return new Intl.DateTimeFormat('fa-IR', options).format(date);
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
        return formatPersianDate(timestamp);
    }
}

// تابع برای کپی کردن متن
function copyToClipboard(text) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
            showNotification('متن کپی شد');
        });
    } else {
        // Fallback برای مرورگرهای قدیمی
        const textArea = document.createElement('textarea');
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        showNotification('متن کپی شد');
    }
}

// تابع برای اشتراک‌گذاری
function shareContent(title, text, url) {
    if (navigator.share) {
        navigator.share({
            title: title,
            text: text,
            url: url
        });
    } else {
        copyToClipboard(url);
        showNotification('لینک کپی شد');
    }
}

// تنظیم event listener برای تغییر وضعیت اتصال
window.addEventListener('online', function() {
    showNotification('اتصال اینترنت برقرار شد', 'success');
});

window.addEventListener('offline', function() {
    showNotification('اتصال اینترنت قطع شد', 'error');
});

// تنظیم event listener برای تغییر اندازه صفحه
window.addEventListener('resize', function() {
    // تنظیم مجدد layout در صورت نیاز
    tg.expand();
});

// تنظیم event listener برای focus/blur
window.addEventListener('focus', function() {
    // بارگذاری مجدد داده‌ها در صورت نیاز
    loadStats();
});

// تابع برای نمایش modal
function showModal(content, title = '') {
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h3>${title}</h3>
                <button onclick="closeModal(this)" class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                ${content}
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // نمایش modal
    setTimeout(() => {
        modal.classList.add('show');
    }, 100);
}

// تابع برای بستن modal
function closeModal(button) {
    const modal = button.closest('.modal');
    modal.classList.remove('show');
    setTimeout(() => {
        document.body.removeChild(modal);
    }, 300);
}

// تنظیم event listener برای کلیک خارج از modal
document.addEventListener('click', function(event) {
    if (event.target.classList.contains('modal')) {
        closeModal(event.target.querySelector('.modal-close'));
    }
});

console.log('🚀 Dakal Mini App Loaded Successfully!');