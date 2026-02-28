document.addEventListener('DOMContentLoaded', function() {
    // DOM Elements
    const modal = document.getElementById('news-modal');
    const addNewsBtn = document.getElementById('add-news-btn');
    const modalCloseBtns = document.querySelectorAll('.modal-close, .btn-cancel');
    const newsForm = document.getElementById('news-form');
    const modalTitle = document.getElementById('modal-title');
    const newsIdInput = document.getElementById('news-id');
    const newsTitle = document.getElementById('news-title');
    const newsContent = document.getElementById('news-content');
    const newsTableBody = document.getElementById('news-table-body');

    // Initialize
    setupEventListeners();
    loadNews();

    // Event Listeners
    function setupEventListeners() {
        addNewsBtn.addEventListener('click', openAddNewsModal);
        modalCloseBtns.forEach(btn => btn.addEventListener('click', closeModal));
        modal.addEventListener('click', (e) => e.target === modal && closeModal());
        newsForm.addEventListener('submit', handleFormSubmit);
        newsTableBody.addEventListener('click', handleTableActions);
    }

    // Load news articles
    function loadNews() {
        showLoading(true);
        
        fetch('/api/hk/news/list.php')
            .then(response => response.ok ? response.json() : Promise.reject('Network error'))
            .then(data => {
                if (data.success) {
                    renderNewsTable(data.news);
                } else {
                    throw new Error(data.error || 'Failed to load news');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('error', error.message);
                renderNewsTable([]);
            })
            .finally(() => showLoading(false));
    }

    // Render news table
    function renderNewsTable(news) {
        newsTableBody.innerHTML = news.length ? '' : 
            '<tr><td colspan="5" class="text-center">No news articles found</td></tr>';

        news.forEach(article => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${article.id}</td>
                <td>${article.title || 'N/A'}</td>
                <td>${article.author || 'N/A'}</td>
                <td>${formatDate(article.date)}</td>
                <td>
                    <button class="btn-admin btn-edit edit-news" data-id="${article.id}">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn-admin btn-delete delete-news" data-id="${article.id}">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
            newsTableBody.appendChild(tr);
        });
    }

    // Date formatting
    function formatDate(dateString) {
        if (!dateString) return 'N/A';
        try {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        } catch (e) {
            return 'N/A';
        }
    }

    // Table actions
    function handleTableActions(e) {
        const editBtn = e.target.closest('.edit-news');
        const deleteBtn = e.target.closest('.delete-news');
        
        if (editBtn) {
            openEditNewsModal(parseInt(editBtn.dataset.id));
        } else if (deleteBtn) {
            confirmDeleteNews(parseInt(deleteBtn.dataset.id));
        }
    }

    // Modal functions
    function openAddNewsModal() {
        resetForm();
        modalTitle.textContent = 'Add New Article';
        modal.style.display = 'flex';
    }

    function openEditNewsModal(newsId) {
        fetch(`/api/hk/news/read.php?id=${newsId}`)
            .then(response => response.ok ? response.json() : Promise.reject('Network error'))
            .then(data => {
                if (data.success) {
                    populateForm(data.news);
                    modalTitle.textContent = 'Edit Article';
                    modal.style.display = 'flex';
                } else {
                    throw new Error(data.error || 'Failed to load article');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('error', error.message);
            });
    }

    function closeModal() {
        modal.style.display = 'none';
    }

    function resetForm() {
        newsForm.reset();
        newsIdInput.value = '';
    }

    function populateForm(news) {
        newsIdInput.value = news.id;
        newsTitle.value = news.title || '';
        newsContent.value = news.content || '';
    }

    // Form submission
    async function handleFormSubmit(e) {
        e.preventDefault();
        
        const submitBtn = newsForm.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;
        
        try {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            
            const newsId = newsIdInput.value;
            const endpoint = newsId ? `/api/hk/news/update.php` : `/api/hk/news/create.php`;
            const method = newsId ? 'PUT' : 'POST';
            
            const response = await fetch(endpoint, {
                method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    ...(newsId && { id: newsId }),
                    title: newsTitle.value,
                    content: newsContent.value
                }),
                credentials: 'include'
            });

            const data = await response.json();
            if (!data.success) throw new Error(data.error || 'Operation failed');

            showNotification('success', data.message || 'Article saved');
            closeModal();
            loadNews();
        } catch (error) {
            console.error('Error:', error);
            showNotification('error', error.message || 'Failed to save article');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnText;
        }
    }

    // Delete confirmation
    function confirmDeleteNews(newsId) {
        if (confirm('Are you sure you want to delete this article?')) {
            deleteNews(newsId);
        }
    }

    // Delete article
    async function deleteNews(newsId) {
        try {
            const response = await fetch('/api/hk/news/delete.php', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: newsId }),
                credentials: 'include'
            });

            const data = await response.json();
            if (!data.success) throw new Error(data.error || 'Delete failed');

            showNotification('success', data.message || 'Article deleted');
            loadNews();
        } catch (error) {
            console.error('Error:', error);
            showNotification('error', error.message || 'Failed to delete article');
        }
    }

    // Loading indicator
    function showLoading(show) {
        let loader = document.getElementById('loading-indicator');
        if (!loader) {
            loader = document.createElement('div');
            loader.id = 'loading-indicator';
            loader.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0,0,0,0.5);
                z-index: 9999;
                display: flex;
                justify-content: center;
                align-items: center;
            `;
            loader.innerHTML = '<div class="spinner"></div>';
            document.body.appendChild(loader);
        }
        loader.style.display = show ? 'flex' : 'none';
    }

    // Notification system
    function showNotification(type, message) {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-times-circle'}"></i>
                <span>${message}</span>
            </div>
        `;
        
        document.body.appendChild(notification);
        setTimeout(() => notification.remove(), 5000);
    }
});