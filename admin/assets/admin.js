/**
 * Admin Panel Interactive Scripts
 */

// Mobile Sidebar Drawer Toggle
function toggleSidebar(open) {
    const sidebar = document.querySelector('.sidebar');
    const backdrop = document.querySelector('.sidebar-backdrop');
    if (!sidebar) return;

    if (typeof open === 'boolean') {
        if (open) {
            sidebar.classList.add('open');
            if (backdrop) backdrop.classList.add('active');
            document.body.classList.add('sidebar-open');
        } else {
            sidebar.classList.remove('open');
            if (backdrop) backdrop.classList.remove('active');
            document.body.classList.remove('sidebar-open');
        }
    } else {
        const isOpen = sidebar.classList.toggle('open');
        if (backdrop) backdrop.classList.toggle('active', isOpen);
        document.body.classList.toggle('sidebar-open', isOpen);
    }
}

// Close sidebar on window resize to desktop
window.addEventListener('resize', () => {
    if (window.innerWidth > 992) {
        toggleSidebar(false);
    }
});

// Theme Toggle
function toggleTheme() {
    const isLight = document.body.classList.toggle('light-mode');
    localStorage.setItem('admin_theme', isLight ? 'light' : 'dark');
}

// Load saved theme
document.addEventListener('DOMContentLoaded', () => {
    if (localStorage.getItem('admin_theme') === 'light') {
        document.body.classList.add('light-mode');
    }
});

// Setup APK Drag & Drop Parser
function initApkDropzone(dropzoneId, fileInputId, onParsed) {
    const dropzone = document.getElementById(dropzoneId);
    const fileInput = document.getElementById(fileInputId);
    if (!dropzone || !fileInput) return;

    ['dragenter', 'dragover'].forEach(name => {
        dropzone.addEventListener(name, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.add('dragover');
        });
    });

    ['dragleave', 'drop'].forEach(name => {
        dropzone.addEventListener(name, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.remove('dragover');
        });
    });

    dropzone.addEventListener('drop', (e) => {
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            handleApkUpload(files[0], onParsed);
        }
    });

    dropzone.addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', () => {
        if (fileInput.files.length > 0) {
            handleApkUpload(fileInput.files[0], onParsed);
        }
    });
}

function handleApkUpload(file, callback) {
    if (!file.name.endsWith('.apk')) {
        alert('Please select an Android APK file (.apk)');
        return;
    }

    const dropzoneText = document.querySelector('.dropzone-text');
    const originalText = dropzoneText ? dropzoneText.textContent : '';
    if (dropzoneText) dropzoneText.textContent = 'Uploading & Parsing APK...';

    const formData = new FormData();
    formData.append('apk_file', file);
    formData.append('action', 'parse_apk');

    fetch('apps.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (dropzoneText) dropzoneText.textContent = originalText;
        if (data.success) {
            if (typeof callback === 'function') {
                callback(data);
            }
        } else {
            alert('APK Parse error: ' + (data.error || 'Unknown error occurred.'));
        }
    })
    .catch(err => {
        if (dropzoneText) dropzoneText.textContent = originalText;
        alert('Network error while uploading APK: ' + err.message);
    });
}

// DNS Server Ping Test
function testDnsServer(dnsId, button) {
    const originalHtml = button.innerHTML;
    button.innerHTML = 'Testing...';
    button.disabled = true;

    fetch('dns.php?action=test_ping&id=' + dnsId)
    .then(res => res.json())
    .then(data => {
        button.innerHTML = originalHtml;
        button.disabled = false;
        const badge = document.getElementById('dns-badge-' + dnsId);
        const msText = document.getElementById('dns-ms-' + dnsId);
        if (badge && msText) {
            if (data.success && data.status === 'online') {
                badge.className = 'badge badge-success';
                badge.textContent = 'Online';
                msText.textContent = data.response_time_ms + ' ms';
            } else {
                badge.className = 'badge badge-danger';
                badge.textContent = 'Offline';
                msText.textContent = 'Failed';
            }
        }
    })
    .catch(err => {
        button.innerHTML = originalHtml;
        button.disabled = false;
        alert('Ping test failed: ' + err.message);
    });
}
