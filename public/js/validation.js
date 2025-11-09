function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

function isValidPassword(password) {
    const minLength = 8;
    const hasUpperCase = /[A-Z]/.test(password);
    const hasLowerCase = /[a-z]/.test(password);
    const hasNumbers = /\d/.test(password);
    
    return password.length >= minLength && hasUpperCase && hasLowerCase && hasNumbers;
}

function isValidName(name) {
    return name.length >= 2 && /^[a-zA-Z\s-']+$/.test(name);
}

function isValidPhone(phone) {
    return /^\+?[\d\s-()]{10,}$/.test(phone);
}

function showError(fieldId, message) {
    const field = document.getElementById(fieldId);
    const existingError = field.nextElementSibling;
    
    if (existingError && existingError.classList.contains('error-message')) {
        existingError.textContent = message;
    } else {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-message';
        errorDiv.textContent = message;
        field.parentNode.insertBefore(errorDiv, field.nextSibling);
    }
    
    field.classList.add('error');
}

function clearError(fieldId) {
    const field = document.getElementById(fieldId);
    const errorDiv = field.nextElementSibling;
    
    if (errorDiv && errorDiv.classList.contains('error-message')) {
        errorDiv.remove();
    }
    
    field.classList.remove('error');
}

// Add event listeners to clear errors when user starts typing
document.addEventListener('DOMContentLoaded', function() {
    const inputs = document.querySelectorAll('input');
    inputs.forEach(input => {
        input.addEventListener('input', () => clearError(input.id));
    });
});