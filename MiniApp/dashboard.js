// تنظیمات اولیه Telegram Web App
let tg = window.Telegram.WebApp;

// راه‌اندازی داشبورد
document.addEventListener('DOMContentLoaded', function() {
    initializeDashboard();
});

function initializeDashboard() {
    // تنظیمات اولیه
    tg.ready();
    tg.expand();
    
    // تنظیم رنگ‌های اصلی
    tg.setHeaderColor('#6366f1');
    tg.setBackgroundColor('#f8fafc');
    
    // نمایش دکمه بازگشت
    showBackButton();
    
    // لود کردن داده‌ها
    loadUserData();
    loadUserBots();
    // loadRecentActivity(); // موقتا غیرفعال تا زمانی که API آن پیاده‌سازی شود
}

function loadUserData() {
    const user = tg.initDataUnsafe?.user;
    if (user) {
        // نمایش نام کاربر
        const userName = document.getElementById('userName');
        userName.textContent = user.first_name || 'کاربر عزیز';
        
        // درخواست آمار کاربر
        fetch('/api/user-stats.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                user_id: user.id
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateUserStats(data.data);
            }
        })
        .catch(error => {
            console.error('خطا در بارگذاری آمار کاربر:', error);
        });
    }
}

function updateUserStats(data) {
    document.getElementById('userBots').textContent = data.user_bots || 0;
    document.getElementById('totalUsers').textContent = formatNumber(data.total_users || 0);
    document.getElementById('sentMessages').textContent = formatNumber(data.sent_messages || 0);
    
    // وضعیت VIP
    const vipStatus = document.getElementById('vipStatus');
    if (data.is_vip) {
        vipStatus.textContent = 'VIP';
        vipStatus.style.color = '#8b5cf6';
    } else {
        vipStatus.textContent = 'رایگان';
        vipStatus.style.color = '#64748b';
    }
}

function loadUserBots() {
    const user = tg.initDataUnsafe?.user;
    if (!user) return;

    fetch('/api/user-bots.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            user_id: user.id
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayUserBots(data.bots);
        } else {
            showEmptyBotsState();
        }
    })
    .catch(error => {
        console.error('خطا در بارگذاری ربات‌ها:', error);
        showEmptyBotsState();
    });
}

function displayUserBots(bots) {
    const botsList = document.getElementById('botsList');
    
    if (!bots || bots.length === 0) {
        showEmptyBotsState();
        return;
    }

    botsList.innerHTML = '';
    
    bots.forEach(bot => {
        const botCard = createBotCard(bot);
        botsList.appendChild(botCard);
    });
}

function createBotCard(bot) {
    const botCard = document.createElement('div');
    botCard.className = 'bot-card';
    
    botCard.innerHTML = `
        <div class="bot-header">
            <div class="bot-name">@${bot.username}</div>
            <div class="bot-status ${bot.is_active ? 'active' : 'inactive'}">
                ${bot.is_active ? 'فعال' : 'غیرفعال'}
            </div>
        </div>
        
        <div class="bot-stats">
            <div class="bot-stat">
                <div class="bot-stat-number">${formatNumber(bot.members_count)}</div>
                <div class="bot-stat-label">کاربران</div>
            </div>
            <div class="bot-stat">
                <div class="bot-stat-number">${formatNumber(bot.messages_sent)}</div>
                <div class="bot-stat-label">پیام‌ها</div>
            </div>
            <div class="bot-stat">
                <div class="bot-stat-number">${bot.created_date}</div>
                <div class="bot-stat-label">تاریخ ساخت</div>
            </div>
        </div>
        
        <div class="bot-actions">
            <button class="bot-action-btn" onclick="manageBot('${bot.username}')">
                ⚙️ مدیریت
            </button>
            <button class="bot-action-btn" onclick="sendMessageToBot('${bot.username}')">
                📝 ارسال پیام
            </button>
            <button class="bot-action-btn ${bot.is_active ? '' : 'primary'}" onclick="toggleBot('${bot.username}', ${bot.is_active})">
                ${bot.is_active ? '⏸️ توقف' : '▶️ شروع'}
            </button>
        </div>
    `;
    
    return botCard;
}

function showEmptyBotsState() {
    const botsList = document.getElementById('botsList');
    botsList.innerHTML = `
        <div class="empty-state">
            <div class="empty-state-icon">🤖</div>
            <h3>هنوز رباتی نساخته‌اید</h3>
            <p>برای شروع، ربات جدیدی بسازید و از امکانات Dakal استفاده کنید.</p>
        </div>
    `;
}

function loadRecentActivity() {
    const user = tg.initDataUnsafe?.user;
    if (!user) return;

    fetch('/api/user-activity', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            user_id: user.id
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayRecentActivity(data.activities);
        } else {
            showEmptyActivityState();
        }
    })
    .catch(error => {
        console.error('خطا در بارگذاری فعالیت‌ها:', error);
        showEmptyActivityState();
    });
}

function displayRecentActivity(activities) {
    const activityContainer = document.getElementById('recentActivity');
    
    if (!activities || activities.length === 0) {
        showEmptyActivityState();
        return;
    }

    activityContainer.innerHTML = '';
    
    activities.forEach(activity => {
        const activityItem = createActivityItem(activity);
        activityContainer.appendChild(activityItem);
    });
}

function createActivityItem(activity) {
    const activityItem = document.createElement('div');
    activityItem.className = 'activity-item';
    
    const icons = {
        'bot_created': '🤖',
        'message_sent': '📝',
        'bot_activated': '✅',
        'bot_deactivated': '⏸️',
        'user_blocked': '🚫',
        'user_unblocked': '✅'
    };
    
    activityItem.innerHTML = `
        <div class="activity-icon" style="background: ${getActivityColor(activity.type)}20; color: ${getActivityColor(activity.type)};">
            ${icons[activity.type] || '📊'}
        </div>
        <div class="activity-content">
            <div class="activity-title">${activity.title}</div>
            <div class="activity-time">${formatRelativeTime(activity.timestamp)}</div>
        </div>
    `;
    
    return activityItem;
}

function getActivityColor(type) {
    const colors = {
        'bot_created': '#10b981',
        'message_sent': '#6366f1',
        'bot_activated': '#10b981',
        'bot_deactivated': '#f59e0b',
        'user_blocked': '#ef4444',
        'user_unblocked': '#10b981'
    };
    
    return colors[type] || '#64748b';
}

function showEmptyActivityState() {
    const activityContainer = document.getElementById('recentActivity');
    activityContainer.innerHTML = `
        <div class="empty-state">
            <div class="empty-state-icon">📊</div>
            <h3>هنوز فعالیتی ثبت نشده</h3>
            <p>فعالیت‌های شما اینجا نمایش داده خواهد شد.</p>
        </div>
    `;
}

// توابع عملیاتی
function createNewBot() {
    tg.showAlert('برای ساخت ربات جدید، به ربات اصلی @Creatorbotdakalbot مراجعه کنید.');
}

function manageBot(botUsername) {
    // انتقال به صفحه مدیریت ربات
    window.location.href = `bot-manager.html?bot=${botUsername}`;
}

function sendMessageToBot(botUsername) {
    // انتقال به صفحه ارسال پیام
    window.location.href = `send-message.html?bot=${botUsername}`;
}

function toggleBot(botUsername, isActive) {
    showNotification('این قابلیت به زودی اضافه می‌شود.', 'info');
}

// توابع کمکی
function formatNumber(num) {
    if (num >= 1000000) {
        return (num / 1000000).toFixed(1) + 'M';
    } else if (num >= 1000) {
        return (num / 1000).toFixed(1) + 'K';
    }
    return num.toString();
}

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

function showBackButton() {
    tg.BackButton.show();
    tg.BackButton.onClick(() => {
        goBack();
    });
}

function goBack() {
    window.location.href = 'index.html';
}

// تنظیم event listener برای تغییر اندازه صفحه
window.addEventListener('resize', function() {
    tg.expand();
});

console.log('📊 Dakal Dashboard Loaded Successfully!');