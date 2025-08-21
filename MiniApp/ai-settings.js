// تنظیمات اولیه Telegram Web App
let tg = window.Telegram.WebApp;

// راه‌اندازی تنظیمات
document.addEventListener('DOMContentLoaded', function() {
    initializeAISettings();
});

function initializeAISettings() {
    // تنظیمات اولیه
    tg.ready();
    tg.expand();
    
    // تنظیم رنگ‌های اصلی
    tg.setHeaderColor('#6366f1');
    tg.setBackgroundColor('#f8fafc');
    
    // نمایش دکمه بازگشت
    showBackButton();
    
    // لود کردن سوالات
    loadQuestions();
    
    // تنظیم event listeners
    setupEventListeners();
}

function setupEventListeners() {
    // فرم اضافه کردن سوال
    document.getElementById('addQuestionForm').addEventListener('submit', function(e) {
        e.preventDefault();
        addQuestion();
    });

    // فرم ویرایش سوال
    document.getElementById('editQuestionForm').addEventListener('submit', function(e) {
        e.preventDefault();
        editQuestion();
    });
}

function addQuestion() {
    const question = document.getElementById('question').value.trim();
    const keywords = document.getElementById('keywords').value.trim();
    const answer = document.getElementById('answer').value.trim();

    if (!question || !keywords || !answer) {
        showNotification('لطفاً تمام فیلدها را پر کنید.', 'error');
        return;
    }

    const user = tg.initDataUnsafe?.user;
    if (!user) {
        showNotification('خطا در احراز هویت.', 'error');
        return;
    }

    fetch('/api/ai-manage-questions', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'add',
            user_id: user.id,
            question: question,
            keywords: keywords,
            answer: answer
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('سوال با موفقیت اضافه شد.');
            document.getElementById('addQuestionForm').reset();
            loadQuestions(); // بارگذاری مجدد لیست
        } else {
            showNotification(data.error || 'خطا در اضافه کردن سوال.', 'error');
        }
    })
    .catch(error => {
        console.error('خطا:', error);
        showNotification('خطا در ارتباط با سرور.', 'error');
    });
}

function editQuestion() {
    const id = document.getElementById('editId').value;
    const question = document.getElementById('editQuestion').value.trim();
    const keywords = document.getElementById('editKeywords').value.trim();
    const answer = document.getElementById('editAnswer').value.trim();

    if (!question || !keywords || !answer) {
        showNotification('لطفاً تمام فیلدها را پر کنید.', 'error');
        return;
    }

    const user = tg.initDataUnsafe?.user;
    if (!user) {
        showNotification('خطا در احراز هویت.', 'error');
        return;
    }

    fetch('/api/ai-manage-questions', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'edit',
            user_id: user.id,
            id: id,
            question: question,
            keywords: keywords,
            answer: answer
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('سوال با موفقیت ویرایش شد.');
            closeModal();
            loadQuestions(); // بارگذاری مجدد لیست
        } else {
            showNotification(data.error || 'خطا در ویرایش سوال.', 'error');
        }
    })
    .catch(error => {
        console.error('خطا:', error);
        showNotification('خطا در ارتباط با سرور.', 'error');
    });
}

function loadQuestions() {
    const user = tg.initDataUnsafe?.user;
    if (!user) return;

    fetch('/api/ai-manage-questions', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'list',
            user_id: user.id
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayQuestions(data.questions);
        } else {
            showEmptyQuestionsState();
        }
    })
    .catch(error => {
        console.error('خطا در بارگذاری سوالات:', error);
        showEmptyQuestionsState();
    });
}

function displayQuestions(questions) {
    const questionsList = document.getElementById('questionsList');
    
    if (!questions || questions.length === 0) {
        showEmptyQuestionsState();
        return;
    }

    questionsList.innerHTML = '';
    
    questions.forEach(question => {
        const questionItem = createQuestionItem(question);
        questionsList.appendChild(questionItem);
    });
}

function createQuestionItem(question) {
    const questionItem = document.createElement('div');
    questionItem.className = 'question-item';
    
    const statusClass = question.is_active == 1 ? 'active' : 'inactive';
    const statusText = question.is_active == 1 ? 'فعال' : 'غیرفعال';
    
    questionItem.innerHTML = `
        <div class="question-header">
            <div>
                <div class="question-title">${question.question}</div>
                <div class="question-keywords">کلمات کلیدی: ${question.keywords}</div>
                <div class="question-status ${statusClass}">${statusText}</div>
            </div>
        </div>
        
        <div class="question-answer">${question.answer}</div>
        
        <div class="question-actions">
            <button class="btn btn-secondary" onclick="editQuestionModal(${question.id}, '${question.question}', '${question.keywords}', '${question.answer}')">
                ✏️ ویرایش
            </button>
            <button class="btn ${question.is_active == 1 ? 'btn-secondary' : 'btn-primary'}" onclick="toggleQuestion(${question.id}, ${question.is_active})">
                ${question.is_active == 1 ? '⏸️ غیرفعال' : '▶️ فعال'}
            </button>
            <button class="btn btn-danger" onclick="deleteQuestion(${question.id})">
                🗑️ حذف
            </button>
        </div>
    `;
    
    return questionItem;
}

function showEmptyQuestionsState() {
    const questionsList = document.getElementById('questionsList');
    questionsList.innerHTML = `
        <div class="empty-state">
            <div class="empty-state-icon">📝</div>
            <h3>هنوز سوالی اضافه نشده</h3>
            <p>برای شروع، سوال جدیدی اضافه کنید تا AI بتواند به آن پاسخ دهد.</p>
        </div>
    `;
}

function editQuestionModal(id, question, keywords, answer) {
    document.getElementById('editId').value = id;
    document.getElementById('editQuestion').value = question;
    document.getElementById('editKeywords').value = keywords;
    document.getElementById('editAnswer').value = answer;
    
    document.getElementById('editModal').classList.add('show');
}

function closeModal() {
    document.getElementById('editModal').classList.remove('show');
}

function toggleQuestion(id, isActive) {
    const user = tg.initDataUnsafe?.user;
    if (!user) return;

    const newStatus = isActive == 1 ? 0 : 1;
    const action = isActive == 1 ? 'غیرفعال' : 'فعال';

    if (confirm(`آیا می‌خواهید این سوال را ${action} کنید؟`)) {
        fetch('/api/ai-manage-questions', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'toggle',
                user_id: user.id,
                id: id,
                is_active: newStatus
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(`سوال با موفقیت ${action} شد.`);
                loadQuestions(); // بارگذاری مجدد
            } else {
                showNotification(data.error || 'خطا در تغییر وضعیت.', 'error');
            }
        })
        .catch(error => {
            console.error('خطا:', error);
            showNotification('خطا در ارتباط با سرور.', 'error');
        });
    }
}

function deleteQuestion(id) {
    const user = tg.initDataUnsafe?.user;
    if (!user) return;

    if (confirm('آیا مطمئن هستید که می‌خواهید این سوال را حذف کنید؟')) {
        fetch('/api/ai-manage-questions', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'delete',
                user_id: user.id,
                id: id
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('سوال با موفقیت حذف شد.');
                loadQuestions(); // بارگذاری مجدد
            } else {
                showNotification(data.error || 'خطا در حذف سوال.', 'error');
            }
        })
        .catch(error => {
            console.error('خطا:', error);
            showNotification('خطا در ارتباط با سرور.', 'error');
        });
    }
}

// توابع کمکی
function showBackButton() {
    tg.BackButton.show();
    tg.BackButton.onClick(() => {
        goBack();
    });
}

function goBack() {
    window.location.href = 'index.html';
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

// تنظیم event listener برای کلیک خارج از modal
document.addEventListener('click', function(event) {
    if (event.target.classList.contains('modal')) {
        closeModal();
    }
});

// تنظیم event listener برای تغییر اندازه صفحه
window.addEventListener('resize', function() {
    tg.expand();
});

console.log('⚙️ Dakal AI Settings Loaded Successfully!');