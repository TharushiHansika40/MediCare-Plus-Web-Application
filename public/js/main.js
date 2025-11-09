// Main JavaScript functionality for Medicare

// Mobile menu toggle
document.addEventListener('DOMContentLoaded', function() {
    const menuToggle = document.querySelector('.menu-toggle');
    const navLinks = document.querySelector('.nav-links');

    if (menuToggle && navLinks) {
        menuToggle.addEventListener('click', function() {
            navLinks.classList.toggle('active');
        });
    }

    // Close mobile menu when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.nav-main')) {
            navLinks.classList.remove('active');
        }
    });
});

// AJAX request helper function
function ajaxRequest(url, method = 'GET', data = null) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open(method, url);
        
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        if (method === 'POST') {
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        }

        xhr.onload = function() {
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    resolve(response);
                } catch (e) {
                    resolve(xhr.responseText);
                }
            } else {
                reject({
                    status: xhr.status,
                    statusText: xhr.statusText
                });
            }
        };

        xhr.onerror = function() {
            reject({
                status: xhr.status,
                statusText: xhr.statusText
            });
        };

        if (data) {
            const params = typeof data === 'string' ? data :
                Object.keys(data)
                    .map(key => `${encodeURIComponent(key)}=${encodeURIComponent(data[key])}`)
                    .join('&');
            xhr.send(params);
        } else {
            xhr.send();
        }
    });
}

// Show notification message
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;

    document.body.appendChild(notification);

    // Auto-hide after 3 seconds
    setTimeout(() => {
        notification.classList.add('fade-out');
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Form validation helper functions
function showError(inputElement, message) {
    const errorDiv = document.createElement('div');
    errorDiv.className = 'error-message';
    errorDiv.textContent = message;

    const existingError = inputElement.parentNode.querySelector('.error-message');
    if (existingError) {
        existingError.remove();
    }

    inputElement.classList.add('error');
    inputElement.parentNode.appendChild(errorDiv);
}

function clearError(inputElement) {
    const errorDiv = inputElement.parentNode.querySelector('.error-message');
    if (errorDiv) {
        errorDiv.remove();
    }
    inputElement.classList.remove('error');
}

// File upload with progress
function uploadFile(fileInput, url, progressCallback, completeCallback) {
    const formData = new FormData();
    formData.append('file', fileInput.files[0]);

    const xhr = new XMLHttpRequest();
    xhr.open('POST', url);

    xhr.upload.onprogress = function(e) {
        if (e.lengthComputable) {
            const percentComplete = (e.loaded / e.total) * 100;
            progressCallback(percentComplete);
        }
    };

    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                const response = JSON.parse(xhr.responseText);
                completeCallback(null, response);
            } catch (e) {
                completeCallback('Invalid response from server');
            }
        } else {
            completeCallback('Upload failed');
        }
    };

    xhr.onerror = function() {
        completeCallback('Upload failed');
    };

    xhr.send(formData);
}

// Debounce helper for search inputs
function debounce(func, wait) {
    let timeout;
    return function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

// Format date helper
function formatDate(date) {
    const options = { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    };
    return new Date(date).toLocaleDateString('en-US', options);
}

// Rating component
class StarRating {
    constructor(container, options = {}) {
        this.container = container;
        this.options = {
            max: options.max || 5,
            initialRating: options.initialRating || 0,
            readOnly: options.readOnly || false,
            onChange: options.onChange || function(){}
        };
        this.rating = this.options.initialRating;
        this.render();
    }

    render() {
        this.container.innerHTML = '';
        this.container.classList.add('star-rating');
        
        for (let i = 1; i <= this.options.max; i++) {
            const star = document.createElement('span');
            star.className = `star ${i <= this.rating ? 'active' : ''}`;
            star.innerHTML = '★';
            
            if (!this.options.readOnly) {
                star.addEventListener('click', () => this.setRating(i));
                star.addEventListener('mouseover', () => this.highlightStars(i));
                star.addEventListener('mouseout', () => this.highlightStars(this.rating));
            }
            
            this.container.appendChild(star);
        }
    }

    setRating(rating) {
        this.rating = rating;
        this.highlightStars(rating);
        this.options.onChange(rating);
    }

    highlightStars(rating) {
        const stars = this.container.querySelectorAll('.star');
        stars.forEach((star, index) => {
            star.classList.toggle('active', index < rating);
        });
    }
}

// Initialize components when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize any star rating components
    document.querySelectorAll('.star-rating-container').forEach(container => {
        new StarRating(container, {
            readOnly: container.dataset.readonly === 'true',
            initialRating: parseInt(container.dataset.rating || 0),
            onChange: function(rating) {
                // Handle rating change
                if (container.dataset.url) {
                    ajaxRequest(container.dataset.url, 'POST', { rating })
                        .then(response => showNotification('Rating updated successfully'))
                        .catch(error => showNotification('Failed to update rating', 'error'));
                }
            }
        });
    });

    // Initialize any file upload components
    document.querySelectorAll('.file-upload').forEach(upload => {
        const input = upload.querySelector('input[type="file"]');
        const progress = upload.querySelector('.progress');
        
        if (input && progress) {
            input.addEventListener('change', function() {
                uploadFile(
                    input,
                    upload.dataset.url,
                    percentage => {
                        progress.style.width = percentage + '%';
                    },
                    (error, response) => {
                        if (error) {
                            showNotification(error, 'error');
                        } else {
                            showNotification('File uploaded successfully');
                            progress.style.width = '0';
                        }
                    }
                );
            });
        }
    });
});