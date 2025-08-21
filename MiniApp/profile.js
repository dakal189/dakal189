// تنظیمات اولیه Telegram Web App
let tg = window.Telegram.WebApp;

// راه‌اندازی صفحه پروفایل
document.addEventListener('DOMContentLoaded', function() {
    initializeProfile();
});

function initializeProfile() {
    // تنظیمات اولیه
    tg.ready();
    tg.expand();
    
    // تنظیم رنگ‌های اصلی
    tg.setHeaderColor('#6366f1');
    tg.setBackgroundColor('#f8fafc');
    
    // نمایش دکمه بازگشت
    showBackButton();
    
    // بارگذاری اطلاعات کاربر
    loadUserProfile();
    
    // بارگذاری آمار کاربر
    loadUserStats();
}

function loadUserProfile() {
    const user = tg.initDataUnsafe?.user;
    if (user) {
        // نمایش اطلاعات اصلی
        document.getElementById('profileName').textContent = user.first_name || 'کاربر Dakal';
        document.getElementById('profileUsername').textContent = user.username ? '@' + user.username : 'بدون نام کاربری';
        document.getElementById('profileId').textContent = `ID: ${user.id}`;
        
        // نمایش اطلاعات شخصی
        document.getElementById('userId').textContent = user.id;
        document.getElementById('userFirstName').textContent = user.first_name || 'نامشخص';
        document.getElementById('userLastName').textContent = user.last_name || 'نامشخص';
        document.getElementById('userLanguage').textContent = user.language_code || 'نامشخص';
        
        // نمایش عکس پروفایل
        displayUserAvatar(user);
        
        // تغییر عنوان صفحه
        const userName = user.first_name || user.username || 'کاربر Dakal';
        document.title = `${userName} - پروفایل - Dakal`;
    } else {
        showNotification('خطا در بارگذاری اطلاعات کاربر.', 'error');
    }
}

function displayUserAvatar(user) {
    const avatarImg = document.getElementById('profileAvatarImg');
    const avatarPlaceholder = document.getElementById('profileAvatarPlaceholder');
    
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
        
        // اضافه کردن event listener برای لود موفق
        avatarImg.onload = function() {
            // عکس با موفقیت لود شد
            console.log('عکس پروفایل با موفقیت لود شد');
        };
    } else {
        // اگر عکس پروفایل موجود نباشد
        avatarImg.style.display = 'none';
        avatarPlaceholder.style.display = 'flex';
        avatarPlaceholder.textContent = '❓';
    }
}

function loadUserStats() {
    const user = tg.initDataUnsafe?.user;
    if (!user) return;

    fetch('/api/user-stats', {
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
            updateUserStats(data);
        } else {
            showNotification('خطا در بارگذاری آمار کاربر.', 'error');
        }
    })
    .catch(error => {
        console.error('خطا در بارگذاری آمار کاربر:', error);
        showNotification('خطا در ارتباط با سرور.', 'error');
    });
}

function updateUserStats(data) {
    // آپدیت آمار کاربر
    document.getElementById('userBotsCount').textContent = formatNumber(data.user_bots || 0);
    document.getElementById('userMembersCount').textContent = formatNumber(data.user_members || 0);
    document.getElementById('userMessagesCount').textContent = formatNumber(data.user_messages || 0);
    
    // آپدیت وضعیت VIP
    const vipStatus = document.getElementById('userVipStatus');
    if (data.is_vip) {
        vipStatus.textContent = '✅';
        vipStatus.style.color = '#10b981';
    } else {
        vipStatus.textContent = '❌';
        vipStatus.style.color = '#ef4444';
    }
}

function formatNumber(num) {
    if (num >= 1000000) {
        return (num / 1000000).toFixed(1) + 'M';
    } else if (num >= 1000) {
        return (num / 1000).toFixed(1) + 'K';
    }
    return num.toString();
}

// توابع ناوبری
function goBack() {
    window.location.href = 'index.html';
}

function openDashboard() {
    window.location.href = 'dashboard.html';
}

function openSettings() {
    window.location.href = 'settings.html';
}

// توابع کمکی
function showBackButton() {
    tg.BackButton.show();
    tg.BackButton.onClick(() => {
        goBack();
    });
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

// تنظیم event listener برای تغییر اندازه صفحه
window.addEventListener('resize', function() {
    tg.expand();
});

console.log('👤 Dakal Profile Page Loaded Successfully!');