<!-- Dashboard Card Component -->
<div class="dashboard-card">
    <div class="card-icon">
        <i class="fas fa-{icon}"></i>
    </div>
    <div class="card-content">
        <h3 class="card-title">{title}</h3>
        <p class="card-value">{value}</p>
    </div>
</div>

<!-- List Item Component -->
<div class="list-item">
    <div class="list-item-main">
        <div class="list-item-icon">
            <i class="fas fa-{icon}"></i>
        </div>
        <div class="list-item-content">
            <h4 class="list-item-title">{title}</h4>
            <p class="list-item-description">{description}</p>
        </div>
    </div>
    <div class="list-item-actions">
        {actions}
    </div>
</div>

<!-- Status Badge Component -->
<span class="status-badge status-{status}">
    {status}
</span>

<!-- Search Bar Component -->
<div class="search-bar">
    <i class="fas fa-search search-icon"></i>
    <input type="text" placeholder="Search..." class="search-input">
    <button class="search-filter-btn">
        <i class="fas fa-filter"></i>
    </button>
</div>

<!-- Filter Panel Component -->
<div class="filter-panel">
    <h4 class="filter-title">Filters</h4>
    <form class="filter-form">
        {filter_fields}
    </form>
    <div class="filter-actions">
        <button class="btn btn-secondary">Reset</button>
        <button class="btn btn-primary">Apply</button>
    </div>
</div>

<!-- Pagination Component -->
<div class="pagination">
    <button class="pagination-btn" disabled={isPrevDisabled}>
        <i class="fas fa-chevron-left"></i>
    </button>
    <div class="pagination-numbers">
        {page_numbers}
    </div>
    <button class="pagination-btn" disabled={isNextDisabled}>
        <i class="fas fa-chevron-right"></i>
    </button>
</div>

<!-- Modal Component -->
<div class="modal" id="{modalId}">
    <div class="modal-overlay"></div>
    <div class="modal-container">
        <div class="modal-header">
            <h3 class="modal-title">{title}</h3>
            <button class="modal-close">×</button>
        </div>
        <div class="modal-body">
            {content}
        </div>
        <div class="modal-footer">
            {actions}
        </div>
    </div>
</div>

<!-- Loading Spinner Component -->
<div class="loading-spinner">
    <div class="spinner"></div>
    <p class="loading-text">{loadingText}</p>
</div>

<!-- Toast Notification Component -->
<div class="toast-notification toast-{type}">
    <div class="toast-icon">
        <i class="fas fa-{icon}"></i>
    </div>
    <div class="toast-content">
        <p class="toast-message">{message}</p>
    </div>
    <button class="toast-close">×</button>
</div>

<!-- File Upload Component -->
<div class="file-upload">
    <div class="file-upload-preview">
        <i class="fas fa-cloud-upload-alt"></i>
        <p>Drag and drop files here or click to browse</p>
    </div>
    <input type="file" class="file-upload-input" multiple={allowMultiple}>
    <div class="file-upload-progress">
        <div class="progress-bar"></div>
    </div>
</div>

<!-- Rating Component -->
<div class="rating-component" data-rating="{rating}">
    <div class="stars">
        {stars}
    </div>
    <p class="rating-text">({rating} out of 5)</p>
</div>

<!-- Comment/Review Component -->
<div class="review-item">
    <div class="review-header">
        <img src="{avatar}" alt="{name}" class="review-avatar">
        <div class="review-meta">
            <h4 class="review-author">{name}</h4>
            <div class="review-rating">
                {rating_stars}
            </div>
            <span class="review-date">{date}</span>
        </div>
    </div>
    <div class="review-content">
        <p>{content}</p>
    </div>
</div>

<!-- Calendar Component -->
<div class="calendar">
    <div class="calendar-header">
        <button class="calendar-nav prev">
            <i class="fas fa-chevron-left"></i>
        </button>
        <h4 class="calendar-title">{month} {year}</h4>
        <button class="calendar-nav next">
            <i class="fas fa-chevron-right"></i>
        </button>
    </div>
    <div class="calendar-body">
        <div class="calendar-weekdays">
            {weekday_names}
        </div>
        <div class="calendar-days">
            {day_cells}
        </div>
    </div>
</div>