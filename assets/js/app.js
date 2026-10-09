// assets/js/app.js  - Shared JS for all pages

// ─── Auth helpers ─────────────────────────────────────────────────────────────

const basePath = (window.location.pathname.includes('/admin/') || window.location.pathname.includes('/citizen/')) ? '../' : '';
const API_URL = basePath + 'api.php';

function checkAuth(callback) {
    fetch(API_URL + '?action=auth_me')
        .then(r => r.json())
        .then(data => callback(data.authenticated ? data.user : null))
        .catch(() => callback(null));
}

function renderNavbar(user) {
    const nav = document.getElementById('authNav');
    if (!nav) return;
    if (user) {
        const adminLink = user.role === 'admin'
            ? `<li class="nav-item"><a class="nav-link" href="admin/dashboard.html">Admin Dashboard</a></li>`
            : `<li class="nav-item"><a class="nav-link" href="citizen/profile.html">My Profile</a></li>`;
        nav.innerHTML = `
            ${adminLink}
            <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle me-1"></i>${escHtml(user.name)}
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="#" id="logoutBtn">Logout</a></li>
                </ul>
            </li>`;
        document.getElementById('logoutBtn').addEventListener('click', function(e) {
            e.preventDefault();
            fetch(API_URL + '?action=auth_logout')
                .then(r => r.json())
                .then(() => window.location.href = basePath + 'index.html');
        });
    } else {
        nav.innerHTML = `
            <li class="nav-item"><a class="nav-link" href="login.html">Login</a></li>
            <li class="nav-item"><a class="btn btn-outline-light ms-2" href="register.html">Register</a></li>`;
    }
}

function renderFooterAuth(user) {
    const el = document.getElementById('footerAuthLink');
    if (!el) return;
    if (user) {
        el.innerHTML = `<a href="citizen/profile.html" class="btn btn-outline-light">My Profile</a>`;
    } else {
        el.innerHTML = `<a href="register.html" class="btn btn-primary">Register Now</a>`;
    }
}

// ─── Scheme card HTML ─────────────────────────────────────────────────────────

function createSchemeCard(s) {
    const imgHtml = s.image
        ? `<img src="${escHtml(s.image)}" class="card-img-top" alt="${escHtml(s.scheme_name)}">`
        : `<div class="card-img-top d-flex align-items-center justify-content-center text-muted bg-light"><i class="bi bi-image" style="font-size:3rem;"></i></div>`;
    return `
        <div class="col-md-4">
            <div class="card scheme-card h-100">
                ${imgHtml}
                <div class="card-body d-flex flex-column">
                    <span class="badge badge-category mb-2 align-self-start px-2 py-1">${escHtml(s.category)}</span>
                    <h5 class="card-title">${escHtml(s.scheme_name)}</h5>
                    <p class="card-text text-muted flex-grow-1">${escHtml(s.short_description.substring(0, 120))}...</p>
                    <a href="scheme-details.html?id=${s.id}" class="btn btn-primary w-100 mt-3">View Details</a>
                </div>
            </div>
        </div>`;
}

// ─── Utility ──────────────────────────────────────────────────────────────────

function escHtml(str) {
    if (!str) return '';
    return str.toString()
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function showAlert(parentId, type, message) {
    const el = document.getElementById(parentId);
    if (!el) return;
    el.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
}

function getUrlParam(name) {
    return new URLSearchParams(window.location.search).get(name) || '';
}

