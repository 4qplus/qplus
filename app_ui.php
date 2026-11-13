<?php
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(403);
    exit;
}
$isAdmin = ($_SESSION['role'] ?? 'guest') === 'admin';
$serverLang = $config['language'] ?? 'en';
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($serverLang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>qplus</title>
    <link rel="icon" type="image/png" href="/assets/qplus_symbol.png">
    <style>
        :root {
            --bg-app: #f5f5f5;
            --bg-surface: #ffffff;
            --bg-subtle: #efefef;
            --border-color: #e4e4e4;
            --text-primary: #141414;
            --text-secondary: #4a4a4a;
            --text-muted: #888888;
            --brand-blue: #0284c7;
            --brand-blue-hover: #0369a1;
            --brand-blue-soft: #e0f2fe;
            --brand-accent: #0284c7;
            --brand-accent-soft: #e0f2fe;
            --danger: #dc2626;
            --danger-soft: #fee2e2;
            --sidebar-width: 248px;
            --topbar-height: 62px;
            --radius-md: 10px;
            --radius-lg: 14px;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.04);
            --shadow-md: 0 8px 24px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 18px 42px rgba(0, 0, 0, 0.14);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 0;
            height: 100vh;
            height: 100dvh;
            overflow: hidden;
            background: var(--bg-app);
            color: var(--text-primary);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Noto Sans KR", "Apple SD Gothic Neo", sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        .app-shell {
            display: flex;
            height: 100%;
            width: 100%;
            position: relative;
        }

        /* Left Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--bg-surface);
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            z-index: 40;
            transition: transform 0.25s cubic-bezier(0.22, 1, 0.36, 1);
        }

        .sidebar-header {
            height: var(--topbar-height);
            padding: 0 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }

        .brand-symbol-img {
            height: 24px;
            width: auto;
            display: block;
        }

        .brand-wordmark-img {
            height: 20px;
            width: auto;
            display: block;
        }

        /* Flat Upload CTA */
        .upload-cta-wrap {
            padding: 10px 16px 14px;
            position: relative;
        }

        .btn-upload-cta {
            width: 100%;
            height: 44px;
            border: none;
            border-radius: 11px;
            background: var(--brand-blue);
            color: #ffffff;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: background 0.15s;
        }

        .btn-upload-cta:hover {
            background: var(--brand-blue-hover);
        }

        .upload-dropdown {
            position: absolute;
            top: 60px;
            left: 16px;
            right: 16px;
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            padding: 6px;
            display: none;
            z-index: 60;
        }

        .upload-dropdown.open { display: block; animation: popFade 0.15s ease; }

        @keyframes popFade {
            from { opacity: 0; transform: translateY(-4px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .upload-dropdown button {
            width: 100%;
            padding: 10px 12px;
            border: none;
            background: transparent;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-primary);
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .upload-dropdown button:hover {
            background: var(--brand-blue-soft);
            color: var(--brand-blue);
        }

        /* Sidebar Navigation */
        .sidebar-scroll {
            flex: 1;
            overflow-y: auto;
            padding: 4px 12px 16px;
        }

        .nav-group-label {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-muted);
            padding: 12px 10px 6px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 600;
            color: var(--text-secondary);
            cursor: pointer;
            transition: background 0.15s, color 0.15s;
            margin-bottom: 2px;
            border: none;
            background: transparent;
            width: 100%;
            text-align: left;
        }

        .nav-item:hover {
            background: var(--bg-subtle);
            color: var(--text-primary);
        }

        .nav-item.active {
            background: var(--brand-blue-soft);
            color: var(--brand-blue);
        }

        .nav-icon {
            width: 18px;
            height: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .nav-label {
            flex: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .nav-lock {
            display: inline-flex;
            align-items: center;
            color: var(--text-muted);
        }

        .nav-divider {
            height: 1px;
            background: var(--border-color);
            margin: 10px 8px;
        }

        /* Storage Gauge */
        .sidebar-storage {
            padding: 16px;
            border-top: 1px solid var(--border-color);
            background: #fafafa;
        }

        .storage-title-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            font-weight: 700;
            color: var(--text-secondary);
            margin-bottom: 8px;
        }

        .storage-bar-bg {
            width: 100%;
            height: 7px;
            background: #e2e2e2;
            border-radius: 99px;
            overflow: hidden;
            margin-bottom: 7px;
        }

        .storage-bar-fill {
            height: 100%;
            width: 0%;
            background: var(--brand-blue);
            border-radius: 99px;
            transition: width 0.6s ease;
        }

        .storage-sub {
            font-size: 11px;
            color: var(--text-muted);
        }

        /* Main Area */
        .main-wrapper {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            height: 100%;
            position: relative;
        }

        /* Top Bar */
        .topbar {
            height: var(--topbar-height);
            background: var(--bg-surface);
            border-bottom: 1px solid var(--border-color);
            padding: 0 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-shrink: 0;
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
            min-width: 0;
        }

        .mobile-menu-btn {
            display: none;
            width: 36px;
            height: 36px;
            border: none;
            background: var(--bg-subtle);
            border-radius: 9px;
            cursor: pointer;
            color: var(--text-primary);
            align-items: center;
            justify-content: center;
        }

        .search-box {
            position: relative;
            flex: 1;
            max-width: 440px;
        }

        .search-box input {
            width: 100%;
            height: 38px;
            padding: 0 36px 0 38px;
            border: none;
            border-radius: 10px;
            background: var(--bg-subtle);
            font-size: 13.5px;
            color: var(--text-primary);
            outline: none;
            transition: background 0.15s, box-shadow 0.15s;
        }

        .search-box input:focus {
            background: #ffffff;
            box-shadow: 0 0 0 2px rgba(2, 132, 199, 0.22);
        }

        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            pointer-events: none;
            display: inline-flex;
        }

        .search-clear {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            border: none;
            background: transparent;
            color: var(--text-muted);
            cursor: pointer;
            display: none;
            padding: 4px;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }

        .session-pill {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: var(--bg-subtle);
            border-radius: 99px;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
        }

        .session-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--brand-accent);
        }

        .role-pill {
            padding: 6px 10px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 700;
            background: #141414;
            color: #ffffff;
        }

        .role-pill.guest {
            background: var(--bg-subtle);
            color: var(--text-secondary);
        }

        
        .lang-toggle-btn {
            height: 32px;
            padding: 0 10px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: var(--bg-surface);
            color: var(--text-primary);
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            letter-spacing: 0.5px;
            transition: all 0.15s;
        }
        .lang-toggle-btn:hover {
            background: var(--brand-blue-soft);
            border-color: var(--brand-blue);
            color: var(--brand-blue);
        }

        .btn-logout {
            height: 36px;
            padding: 0 12px;
            border: none;
            background: var(--bg-subtle);
            color: var(--text-secondary);
            border-radius: 9px;
            font-size: 12.5px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.15s, color 0.15s;
        }

        .btn-logout:hover {
            background: var(--danger-soft);
            color: var(--danger);
        }

        /* Views Container */
        .content-stage {
            flex: 1;
            min-height: 0;
            position: relative;
            display: flex;
            flex-direction: column;
        }

        .app-view {
            display: none;
            flex: 1;
            min-height: 0;
            flex-direction: column;
        }

        .app-view.active { display: flex; }

        /* Drive Toolbar */
        .drive-toolbar {
            background: var(--bg-surface);
            border-bottom: 1px solid var(--border-color);
            padding: 12px 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            flex-shrink: 0;
        }

        .breadcrumb-wrap {
            display: flex;
            align-items: center;
            gap: 6px;
            min-width: 0;
            flex: 1;
            flex-wrap: wrap;
        }

        .btn-up {
            height: 32px;
            padding: 0 10px;
            border: none;
            background: var(--bg-subtle);
            border-radius: 8px;
            font-size: 12.5px;
            font-weight: 600;
            color: var(--text-secondary);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .btn-up:hover { background: #e4e4e4; color: var(--text-primary); }

        .breadcrumb-list {
            display: flex;
            align-items: center;
            gap: 4px;
            flex-wrap: wrap;
            font-size: 14px;
            font-weight: 600;
        }

        .crumb-btn {
            border: none;
            background: transparent;
            padding: 4px 8px;
            border-radius: 6px;
            color: var(--text-secondary);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }

        .crumb-btn:hover { background: var(--bg-subtle); color: var(--text-primary); }
        .crumb-btn.current { color: var(--text-primary); font-weight: 700; }
        .crumb-sep { color: var(--text-muted); font-size: 12px; }

        .btn-new-folder {
            height: 32px;
            padding: 0 12px;
            border: 1px solid var(--border-color);
            background: #ffffff;
            color: var(--text-primary);
            border-radius: 8px;
            font-size: 12.5px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.15s ease;
            box-shadow: var(--shadow-sm);
        }

        .btn-new-folder:hover {
            background: var(--brand-blue-soft);
            border-color: var(--brand-blue);
            color: var(--brand-blue);
        }

        .btn-new-folder svg {
            color: var(--brand-blue);
        }

        .current-path-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            background: var(--bg-subtle);
            border: 1px solid var(--border-color);
            border-radius: 7px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
            max-width: 320px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .current-path-badge svg {
            color: var(--text-muted);
            flex-shrink: 0;
        }

        .toolbar-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .selection-bar {
            display: none;
            align-items: center;
            gap: 6px;
            padding: 4px 8px;
            background: var(--brand-blue-soft);
            border-radius: 9px;
        }

        .selection-bar.visible { display: flex; }

        .selection-count {
            font-size: 12.5px;
            font-weight: 700;
            color: var(--brand-blue);
            padding: 0 6px;
        }

        .t-btn {
            height: 32px;
            padding: 0 11px;
            border: none;
            background: var(--bg-subtle);
            color: var(--text-secondary);
            border-radius: 8px;
            font-size: 12.5px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: background 0.15s, color 0.15s;
        }

        .t-btn:hover { background: #e4e4e4; color: var(--text-primary); }
        .t-btn.primary { background: var(--brand-blue); color: #ffffff; }
        .t-btn.primary:hover { background: var(--brand-blue-hover); }
        .t-btn.danger { background: var(--danger-soft); color: var(--danger); }
        .t-btn.danger:hover { background: var(--danger); color: #ffffff; }

        .sort-select {
            height: 32px;
            padding: 0 10px;
            border: none;
            border-radius: 8px;
            background: var(--bg-subtle);
            color: var(--text-secondary);
            font-size: 12.5px;
            font-weight: 600;
            outline: none;
            cursor: pointer;
        }

        .view-toggle {
            display: inline-flex;
            border-radius: 8px;
            overflow: hidden;
            background: var(--bg-subtle);
            padding: 2px;
            gap: 2px;
        }

        .view-toggle button {
            height: 28px;
            width: 32px;
            padding: 0;
            border: none;
            border-radius: 6px;
            background: transparent;
            color: var(--text-muted);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .view-toggle button.active {
            background: #ffffff;
            color: var(--brand-blue);
        }

        /* Drive Body */
        .drive-body {
            flex: 1;
            overflow-y: auto;
            padding: 20px 22px 80px;
            position: relative;
        }

        .drag-overlay {
            position: absolute;
            inset: 14px;
            background: rgba(224, 242, 254, 0.94);
            border: 2px dashed var(--brand-blue);
            border-radius: 16px;
            z-index: 50;
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 10px;
            pointer-events: none;
        }

        .drag-overlay.active { display: flex; }
        .drag-overlay-text { font-size: 16px; font-weight: 700; color: var(--brand-blue); }
        .drag-overlay-sub { font-size: 13px; color: var(--text-secondary); }

        .drive-notice {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 9px 14px;
            background: #ffffff;
            border-radius: 10px;
            margin-bottom: 16px;
            font-size: 12px;
            color: var(--text-secondary);
        }

        /* List View */
        .list-table-wrap {
            background: var(--bg-surface);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .list-header {
            display: grid;
            grid-template-columns: 42px minmax(180px, 1fr) 110px 145px 190px;
            align-items: center;
            padding: 12px 16px;
            background: #fafafa;
            border-bottom: 1px solid var(--border-color);
            font-size: 12px;
            font-weight: 700;
            color: var(--text-muted);
        }

        .list-row {
            display: grid;
            grid-template-columns: 42px minmax(180px, 1fr) 110px 145px 190px;
            align-items: center;
            padding: 11px 16px;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.12s;
            font-size: 13.5px;
        }

        .list-row:last-child { border-bottom: none; }
        .list-row:hover { background: #fafafa; }
        .list-row.selected { background: var(--brand-blue-soft); }
        .list-row.drop-target { background: #e0f2fe; outline: 2px dashed var(--brand-blue); outline-offset: -2px; }

        .row-name-cell {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
            cursor: pointer;
        }

        .row-name-text {
            font-weight: 600;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .row-name-cell:hover .row-name-text { color: var(--brand-blue); }
        .row-meta { font-size: 12.5px; color: var(--text-muted); }

        .row-actions {
            display: flex;
            justify-content: flex-end;
            gap: 6px;
        }

        /* Flat Borderless Action Buttons */
        .mini-btn {
            padding: 5px 10px;
            border-radius: 7px;
            border: none;
            background: var(--bg-subtle);
            color: var(--text-secondary);
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            white-space: nowrap;
        }

        .mini-btn:hover { background: #e4e4e4; color: var(--text-primary); }
        .mini-btn.preview { color: var(--brand-blue); background: var(--brand-blue-soft); }
        .mini-btn.preview:hover { background: var(--brand-blue); color: #ffffff; }
        .mini-btn.del { color: var(--danger); background: var(--danger-soft); }
        .mini-btn.del:hover { background: var(--danger); color: #ffffff; }

        /* Grid View */
        .grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(176px, 1fr));
            gap: 14px;
        }

        .grid-card {
            background: var(--bg-surface);
            border: none;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: transform 0.15s, box-shadow 0.15s, background 0.15s;
            position: relative;
            display: flex;
            flex-direction: column;
            cursor: pointer;
        }

        .grid-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .grid-card.selected {
            background: var(--brand-blue-soft);
            box-shadow: inset 0 0 0 2px var(--brand-blue);
        }

        .grid-card.drop-target {
            outline: 2px dashed var(--brand-blue);
            background: #e0f2fe;
        }

        .grid-thumb {
            height: 124px;
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .grid-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .grid-checkbox-wrap {
            position: absolute;
            top: 8px;
            left: 8px;
            z-index: 3;
            background: rgba(255, 255, 255, 0.92);
            border-radius: 6px;
            padding: 3px;
            display: flex;
            opacity: 0;
            transition: opacity 0.15s;
        }

        .grid-card:hover .grid-checkbox-wrap,
        .grid-card.selected .grid-checkbox-wrap {
            opacity: 1;
        }

        .grid-quick-dl {
            position: absolute;
            top: 8px;
            right: 8px;
            z-index: 3;
            width: 28px;
            height: 28px;
            border-radius: 7px;
            background: rgba(255, 255, 255, 0.92);
            border: none;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            opacity: 0;
            transition: opacity 0.15s, background 0.15s, color 0.15s;
        }

        .grid-card:hover .grid-quick-dl { opacity: 1; }
        .grid-quick-dl:hover { background: var(--brand-blue); color: #ffffff; }

        .grid-info { padding: 11px 12px; }

        .grid-title {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 4px;
        }

        .grid-meta {
            font-size: 11px;
            color: var(--text-muted);
            display: flex;
            justify-content: space-between;
        }

        /* Borderless Flat File Icons */
        .flat-file-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            position: relative;
        }

        .flat-lock-tag {
            position: absolute;
            right: -3px;
            bottom: -2px;
            color: #475569;
            display: inline-flex;
        }

        .file-checkbox {
            width: 16px;
            height: 16px;
            cursor: pointer;
            accent-color: var(--brand-blue);
        }

        .empty-state {
            background: var(--bg-surface);
            border-radius: var(--radius-lg);
            padding: 60px 20px;
            text-align: center;
            color: var(--text-muted);
            font-size: 14px;
        }

        /* Secondary Views */
        .page-scroll {
            flex: 1;
            overflow-y: auto;
            padding: 24px 28px 80px;
        }

        .page-inner { max-width: 920px; margin: 0 auto; }

        .page-heading {
            font-size: 20px;
            font-weight: 800;
            color: var(--text-primary);
            margin: 0 0 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .surface-card {
            background: var(--bg-surface);
            border-radius: var(--radius-lg);
            padding: 20px 24px;
            margin-bottom: 16px;
            box-shadow: var(--shadow-sm);
        }

        .surface-card h3 {
            margin: 0 0 14px;
            font-size: 16px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .form-input, .form-textarea, .form-select {
            width: 100%;
            padding: 11px 13px;
            border: 1px solid var(--border-color);
            border-radius: 9px;
            font-size: 14px;
            font-family: inherit;
            color: var(--text-primary);
            background: #ffffff;
            outline: none;
            margin-bottom: 12px;
        }

        .form-input:focus, .form-textarea:focus, .form-select:focus {
            border-color: var(--brand-blue);
            box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.14);
        }

        .form-textarea { min-height: 110px; resize: vertical; }

        .btn-primary {
            padding: 10px 18px;
            border: none;
            border-radius: 9px;
            background: var(--brand-blue);
            color: #ffffff;
            font-size: 13.5px;
            font-weight: 700;
            cursor: pointer;
        }

        .btn-primary:hover { background: var(--brand-blue-hover); }

        .btn-secondary {
            padding: 10px 16px;
            border: none;
            border-radius: 9px;
            background: var(--bg-subtle);
            color: var(--text-secondary);
            font-size: 13.5px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-secondary:hover { background: #e4e4e4; }

        .btn-danger-solid {
            padding: 10px 18px;
            border: none;
            border-radius: 9px;
            background: var(--danger);
            color: #ffffff;
            font-size: 13.5px;
            font-weight: 700;
            cursor: pointer;
        }

        .board-meta { font-size: 12px; color: var(--text-muted); margin-bottom: 10px; }
        .board-body { font-size: 14px; line-height: 1.65; color: var(--text-secondary); white-space: pre-wrap; word-break: break-word; }

        .status-badge {
            display: inline-block;
            margin-top: 10px;
            padding: 3px 9px;
            border-radius: 6px;
            background: var(--brand-blue-soft);
            color: var(--brand-blue-hover);
            font-size: 11px;
            font-weight: 700;
        }

        .settings-tabs { display: flex; gap: 8px; margin-bottom: 18px; flex-wrap: wrap; }

        .settings-tab-btn {
            padding: 9px 15px;
            border: none;
            background: var(--bg-subtle);
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            cursor: pointer;
        }

        .settings-tab-btn.active { background: var(--brand-blue); color: #ffffff; }
        .cp-section { display: none; }
        .cp-section.active { display: block; }

        /* Floating Upload Toast */
        .upload-toast {
            position: fixed;
            right: 22px;
            bottom: 22px;
            width: 340px;
            max-width: calc(100vw - 32px);
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 14px;
            box-shadow: var(--shadow-lg);
            padding: 14px 16px;
            z-index: 90;
            display: none;
        }

        .upload-toast.visible { display: block; animation: popFade 0.2s ease; }
        .toast-head { display: flex; justify-content: space-between; align-items: center; font-size: 13px; font-weight: 700; margin-bottom: 8px; }
        .toast-file { font-size: 12px; color: var(--text-secondary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 8px; }
        .toast-bar-bg { height: 7px; background: var(--bg-subtle); border-radius: 99px; overflow: hidden; margin-bottom: 8px; }
        .toast-bar-fill { height: 100%; width: 0%; background: var(--brand-blue); transition: width 0.2s; }
        .toast-foot { display: flex; justify-content: space-between; align-items: center; font-size: 11.5px; color: var(--text-muted); }

        /* Context Menu */
        .context-menu {
            position: fixed;
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 11px;
            box-shadow: var(--shadow-lg);
            padding: 6px;
            min-width: 175px;
            z-index: 200;
            display: none;
        }

        .context-menu button {
            width: 100%;
            padding: 9px 12px;
            border: none;
            background: transparent;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-primary);
            border-radius: 7px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 9px;
        }

        .context-menu button:hover { background: var(--brand-blue-soft); color: var(--brand-blue); }
        .context-menu button.danger { color: var(--danger); }
        .context-menu button.danger:hover { background: var(--danger-soft); color: var(--danger); }

        /* Modals */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.7);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            padding: 20px;
        }

        .modal-content {
            background: #ffffff;
            width: 100%;
            max-width: 980px;
            height: 85vh;
            border-radius: 16px;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: var(--shadow-lg);
        }

        .modal-close {
            position: absolute;
            top: 14px;
            right: 16px;
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: var(--bg-subtle);
            color: var(--text-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: none;
            z-index: 10;
        }

        .modal-close:hover { background: var(--danger); color: #ffffff; }

        #folder-auth-modal {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1005;
            padding: 16px;
        }

        .folder-auth-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 28px;
            width: 100%;
            max-width: 360px;
            box-shadow: var(--shadow-lg);
            text-align: center;
        }

        .folder-auth-title { margin: 0 0 8px; font-size: 18px; font-weight: 800; }
        .folder-auth-sub { margin: 0 0 18px; font-size: 13px; color: var(--text-muted); }
        .folder-auth-btns { display: flex; gap: 10px; }
        .folder-auth-btns button { flex: 1; }

        .sidebar-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.45);
            z-index: 35;
        }

        @media screen and (max-width: 900px) {
            .list-header { display: none; }
            .list-row {
                grid-template-columns: 34px 1fr;
                gap: 6px;
                padding: 12px 14px;
            }
            .row-meta { display: none; }
            .row-actions { grid-column: 2; justify-content: flex-start; margin-top: 4px; }
        }

        @media screen and (max-width: 768px) {
            .sidebar {
                position: fixed;
                top: 0; left: 0; bottom: 0;
                transform: translateX(-100%);
            }
            .sidebar.open { transform: translateX(0); }
            .sidebar-backdrop.open { display: block; }
            .mobile-menu-btn { display: inline-flex; }
            .topbar { padding: 0 12px; gap: 8px; }
            .session-pill, .role-pill { display: none; }
            .drive-toolbar { padding: 10px 14px; }
            .drive-body { padding: 14px 14px 90px; }
            .grid-container { grid-template-columns: repeat(auto-fill, minmax(145px, 1fr)); gap: 10px; }
            .page-scroll { padding: 16px 14px 80px; }
        }
    </style>
</head>
<body>
    <script>
        const USER_ROLE = <?php echo json_encode($_SESSION["role"] ?? "guest"); ?>;
        const SERVER_LANG = <?php echo json_encode($serverLang); ?>;
        const CSRF_TOKEN = <?php echo json_encode($_SESSION['csrf_token']); ?>;
    </script>

    <div class="sidebar-backdrop" id="sidebar-backdrop" onclick="toggleMobileSidebar(false)"></div>

    <div class="app-shell">
        <!-- Left Sidebar -->
        <aside class="sidebar" id="app-sidebar">
            <div class="sidebar-header" onclick="openDriveRoot()">
                <img src="/assets/qplus_symbol.png" alt="q+" class="brand-symbol-img">
                <img src="/assets/qplus_logo.png" alt="qplus" class="brand-wordmark-img">
            </div>

            <div class="upload-cta-wrap">
                <button class="btn-upload-cta" type="button" onclick="toggleUploadDropdown(event)">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                    <span data-i18n="upload">올리기</span>
                </button>
                <div class="upload-dropdown" id="upload-dropdown">
                    <button type="button" onclick="triggerUploadFile()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><polyline points="9 15 12 12 15 15"/></svg>
                        <span data-i18n="upload_file">파일 업로드</span>
                    </button>
                    <button type="button" onclick="triggerUploadFolder()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/><line x1="12" y1="11" x2="12" y2="17"/><line x1="9" y1="14" x2="15" y2="14"/></svg>
                        <span data-i18n="upload_folder">폴더 업로드</span>
                    </button>
                    <button type="button" onclick="createFolder()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                        <span data-i18n="new_folder">새 폴더 만들기</span>
                    </button>
                </div>
            </div>

            <div class="sidebar-scroll">
                <div class="nav-group-label" data-i18n="drive">드라이브</div>
                <button class="nav-item active" id="nav-drive-root" onclick="openDriveRoot()">
                    <span class="nav-icon">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                    </span>
                    <span class="nav-label" data-i18n="my_drive">내 드라이브 (전체)</span>
                </button>
                <div id="sidebar-root-folders"></div>

                <div class="nav-divider"></div>
                <div class="nav-group-label" data-i18n="workspace">워크스페이스</div>
                <button class="nav-item" id="nav-tips" onclick="switchAppView('tips')">
                    <span class="nav-icon">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    </span>
                    <span class="nav-label" data-i18n="tips">Tips & 공지</span>
                </button>
                <button class="nav-item" id="nav-requests" onclick="switchAppView('requests')">
                    <span class="nav-icon">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    </span>
                    <span class="nav-label" data-i18n="requests">요청사항</span>
                </button>
                <?php if ($isAdmin): ?>
                <button class="nav-item" id="nav-settings" onclick="switchAppView('settings')">
                    <span class="nav-icon">
                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                    </span>
                    <span class="nav-label" data-i18n="settings">관리자 설정</span>
                </button>
                <?php endif; ?>
            </div>

            <div class="sidebar-storage">
                <div class="storage-title-row">
                    <span data-i18n="storage_cap">스토리지 용량</span>
                    <span id="sidebar-disk-pct">0%</span>
                </div>
                <div class="storage-bar-bg">
                    <div class="storage-bar-fill" id="sidebar-disk-bar"></div>
                </div>
                <div class="storage-sub" id="sidebar-disk-text" data-i18n="storage_checking">용량 확인 중...</div>
            </div>
        </aside>

        <!-- Main Workspace -->
        <div class="main-wrapper">
            <header class="topbar">
                <div class="topbar-left">
                    <button class="mobile-menu-btn" type="button" onclick="toggleMobileSidebar(true)" aria-label="메뉴">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                    </button>
                    <div class="search-box">
                        <span class="search-icon">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        </span>
                        <input type="text" id="file-search-input" placeholder="현재 폴더 내 파일·폴더 검색..." data-i18n-ph="search_ph" oninput="handleSearchInput()">
                        <button type="button" class="search-clear" id="search-clear-btn" onclick="clearSearch()">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>
                </div>
                <div class="topbar-right">
                    <button type="button" class="lang-toggle-btn" id="lang-toggle-btn" onclick="toggleLanguage()" title="Language / 언어 전환">EN</button>
                    <div class="session-pill" title="Active users">
                        <span class="session-dot"></span>
                        <span><span data-i18n="online">접속</span> <strong id="active-session-number">-</strong><span data-i18n="online_unit">명</span></span>
                    </div>
                    <span class="role-pill <?php echo $isAdmin ? '' : 'guest'; ?>">
                        <?php echo $isAdmin ? 'ADMIN' : 'GUEST'; ?>
                    </span>
                    <button type="button" class="btn-logout" onclick="location.href='?logout=1'" data-i18n="logout">로그아웃</button>
                </div>
            </header>

            <main class="content-stage">
                <!-- VIEW 1: DRIVE -->
                <section class="app-view active" id="view-drive">
                    <div class="drive-toolbar">
                        <div class="breadcrumb-wrap">
                            <button class="btn-up" id="btn-go-up" style="display:none;" onclick="goUpDirectory()">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                                <span data-i18n="up">상위</span>
                            </button>
                            <div class="breadcrumb-list" id="breadcrumb-list"></div>
                            <div class="current-path-badge" id="current-path-badge" title="현재 서버 디렉터리 경로">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                                <span id="current-path-text">/</span>
                            </div>
                        </div>

                        <div class="toolbar-actions">
                            <button type="button" class="btn-new-folder" onclick="openNewFolderModal()" title="현재 위치에 새 폴더를 만듭니다">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/><line x1="12" y1="11" x2="12" y2="17"/><line x1="9" y1="14" x2="15" y2="14"/></svg>
                                <span data-i18n="new_folder">새 폴더</span>
                            </button>
                            <div class="selection-bar" id="selection-bar">
                                <span class="selection-count" id="selection-count">0개 선택</span>
                                <button type="button" class="t-btn primary" onclick="downloadSelectedFiles()" data-i18n="download">다운로드</button>
                                <?php if ($isAdmin): ?>
                                <button type="button" class="t-btn" onclick="copySelectedForMove()" data-i18n="move_copy">이동 복사</button>
                                <button type="button" class="t-btn danger" onclick="deleteSelectedFiles()" data-i18n="delete">삭제</button>
                                <?php endif; ?>
                            </div>
                            <?php if ($isAdmin): ?>
                            <button type="button" class="t-btn" id="btn-paste-move" style="display:none;" onclick="pasteMovedItems()">여기로 이동 (<span id="move-clip-count">0</span>)</button>
                            <?php endif; ?>
                            <button type="button" class="t-btn" onclick="toggleSelectAll()" data-i18n="select_all">전체선택</button>
                            <select class="sort-select" id="sort-select" onchange="renderCurrentItems()">
                                <option value="name" data-i18n="sort_name">이름순</option>
                                <option value="mtime_desc" data-i18n="sort_date">최신순</option>
                                <option value="size_desc" data-i18n="sort_size">용량순</option>
                            </select>
                            <div class="view-toggle">
                                <button type="button" id="btn-view-list" onclick="setViewMode('list')" title="리스트 보기">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                                </button>
                                <button type="button" id="btn-view-grid" onclick="setViewMode('grid')" title="그리드 보기">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="drive-body" id="drive-drop-area">
                        <div class="drag-overlay" id="drag-overlay">
                            <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="#0284c7" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                            <div class="drag-overlay-text" data-i18n="drag_overlay">여기에 파일이나 폴더를 놓아 업로드하세요</div>
                            <div class="drag-overlay-sub" data-i18n="drag_overlay_sub">현재 위치에 바로 저장됩니다</div>
                        </div>

                        <div class="drive-notice">
                            <span data-i18n="drag_hint">화면 어디든 파일·폴더를 끌어다 놓으면 업로드됩니다. (실행 파일 .exe, .cmd 등 공유 시 실행 주의)</span>
                            <span id="upload-status-inline" style="font-weight:700;"></span>
                        </div>

                        <div id="file-list-area"></div>
                    </div>
                </section>

                <!-- VIEW 2: TIPS -->
                <section class="app-view" id="view-tips">
                    <div class="page-scroll">
                        <div class="page-inner">
                            <h2 class="page-heading">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0284c7" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                                <span data-i18n="tips_title">Tips & 공지사항</span>
                            </h2>
                            <?php if ($isAdmin): ?>
                            <form class="surface-card" onsubmit="saveTip(event)">
                                <input type="hidden" id="tip-edit-id" value="">
                                <input class="form-input" id="tip-title-input" type="text" maxlength="200" placeholder="공지 또는 TIP 제목" data-i18n-ph="tip_title_ph" required>
                                <textarea class="form-textarea" id="tip-body-input" maxlength="10000" placeholder="사용법이나 공지 내용을 입력하세요." data-i18n-ph="tip_body_ph" required></textarea>
                                <button class="btn-primary" id="tip-submit-btn" type="submit" data-i18n="tip_post">TIP 게시</button>
                                <button class="btn-secondary" id="tip-cancel-btn" type="button" onclick="cancelTipEdit()" style="display:none;margin-left:8px;">수정 취소</button>
                                <span id="tip-save-status" style="font-size:12px;margin-left:10px;color:var(--text-muted);"></span>
                            </form>
                            <?php endif; ?>
                            <div id="tip-list"><div class="empty-state">불러오는 중...</div></div>
                        </div>
                    </div>
                </section>

                <!-- VIEW 3: REQUESTS -->
                <section class="app-view" id="view-requests">
                    <div class="page-scroll">
                        <div class="page-inner">
                            <h2 class="page-heading">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0284c7" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                <span data-i18n="req_title">요청사항 게시판</span>
                            </h2>
                            <form class="surface-card" onsubmit="saveRequest(event)">
                                <input class="form-input" id="request-title-input" type="text" maxlength="200" placeholder="요청 제목" data-i18n-ph="req_title_ph" required>
                                <textarea class="form-textarea" id="request-body-input" maxlength="10000" placeholder="필요한 프로그램, 자료 또는 개선 요청사항을 입력하세요." data-i18n-ph="req_body_ph" required></textarea>
                                <button class="btn-primary" type="submit" data-i18n="req_post">요청 등록</button>
                                <span id="request-save-status" style="font-size:12px;margin-left:10px;color:var(--text-muted);"></span>
                            </form>
                            <div id="request-list"><div class="empty-state">불러오는 중...</div></div>
                        </div>
                    </div>
                </section>

                <!-- VIEW 4: SETTINGS (Admin Only) -->
                <?php if ($isAdmin): ?>
                <section class="app-view" id="view-settings">
                    <div class="page-scroll">
                        <div class="page-inner">
                            <h2 class="page-heading">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0284c7" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                                <span>관리자 설정</span>
                            </h2>
                            <div class="settings-tabs">
                                <button class="settings-tab-btn active" onclick="switchCpTab('sys-monitor', this)" data-i18n="tab_storage">스토리지 및 시스템</button>
                                <button class="settings-tab-btn" onclick="switchCpTab('sys-folder-auth', this)" data-i18n="tab_folders">폴더별 보안</button>
                                <button class="settings-tab-btn" onclick="switchCpTab('sys-security', this)" data-i18n="tab_passwords">계정 비밀번호</button>
                                <button class="settings-tab-btn" onclick="switchCpTab('sys-presence', this)" data-i18n="tab_presence">접속 현황</button>
                            </div>

                            <div id="sys-monitor" class="cp-section active">
                                <div class="surface-card">
                                    <h3 data-i18n="drive_status">드라이브 상태 (D:)</h3>
                                    <div class="storage-bar-bg" style="height:14px;margin-bottom:10px;">
                                        <div class="storage-bar-fill" id="disk-bar" style="font-size:10px;color:#fff;display:flex;align-items:center;justify-content:flex-end;padding-right:6px;">0%</div>
                                    </div>
                                    <div style="display:flex;justify-content:space-between;font-size:13px;color:var(--text-secondary);font-weight:600;">
                                        <span id="disk-used-text">사용 중: 0 GB</span>
                                        <span id="disk-free-text">남은 공간: 0 GB / 총 0 GB</span>
                                    </div>
                                </div>

                                <div class="surface-card">
                                    <h3 data-i18n="cleanup_title">시스템 정리 (임시 업로드 캐시)</h3>
                                    <p style="font-size:13px;color:var(--text-muted);margin:0 0 14px;">업로드 취소나 오류로 남겨진 임시 파일 조각(.tmp)을 삭제하여 용량을 확보합니다.</p>
                                    <button class="btn-danger-solid" type="button" onclick="clearTempData()" data-i18n="cleanup_btn">임시 데이터 정리</button>
                                    <p id="clear-temp-msg" style="margin:12px 0 0;font-size:13px;font-weight:600;"></p>
                                </div>
                            </div>

                            <div id="sys-folder-auth" class="cp-section">
                                <div class="surface-card">
                                    <h3 data-i18n="folder_pw_title">폴더별 비밀번호 설정 / 해제</h3>
                                    <p style="font-size:13px;color:var(--text-muted);margin:0 0 14px;">대상 폴더 경로를 입력하고 비밀번호를 지정하세요. (비밀번호 공란 저장 시 잠금 해제)</p>
                                    <form id="folder-auth-form" onsubmit="saveFolderPassword(event)">
                                        <label style="display:block;font-size:12.5px;font-weight:700;margin-bottom:6px;">대상 폴더 경로</label>
                                        <input class="form-input" type="text" id="target_folder_name" placeholder="예: 컴퓨터 계열/운영부" required>
                                        <label style="display:block;font-size:12.5px;font-weight:700;margin-bottom:6px;">폴더 비밀번호</label>
                                        <input class="form-input" type="password" id="target_folder_pw" placeholder="새 비밀번호 입력 (해제 시 비움)">
                                        <button type="submit" class="btn-primary" data-i18n="save_settings">설정 저장</button>
                                    </form>
                                    <p id="folder-auth-msg" style="margin:12px 0 0;font-size:13px;font-weight:600;"></p>
                                </div>
                            </div>

                            <div id="sys-security" class="cp-section">
                                <div class="surface-card">
                                    <h3 data-i18n="account_pw_title">계정 비밀번호 변경</h3>
                                    <form id="pwd-form" onsubmit="changePassword(event)">
                                        <label style="display:block;font-size:12.5px;font-weight:700;margin-bottom:6px;">변경 대상</label>
                                        <select class="form-select" id="target_role">
                                            <option value="admin">관리자 계정 (ADMIN)</option>
                                            <option value="guest">게스트 계정 (GUEST)</option>
                                        </select>
                                        <label style="display:block;font-size:12.5px;font-weight:700;margin-bottom:6px;">현재 관리자 비밀번호</label>
                                        <input class="form-input" type="password" id="old_pw" required>
                                        <label style="display:block;font-size:12.5px;font-weight:700;margin-bottom:6px;">새 비밀번호</label>
                                        <input class="form-input" type="password" id="new_pw" required>
                                        <button type="submit" class="btn-primary" data-i18n="change_pw_btn">비밀번호 변경</button>
                                    </form>
                                    <p id="pwd-msg" style="margin:12px 0 0;font-size:13px;font-weight:600;"></p>
                                </div>
                            </div>

                            <div id="sys-presence" class="cp-section">
                                <div class="surface-card">
                                    <h3 data-i18n="presence_title">현재 접속 세션</h3>
                                    <p style="font-size:13px;color:var(--text-muted);margin:0 0 12px;">최근 20분 이내 활동한 접속 기록입니다.</p>
                                    <div style="overflow-x:auto;">
                                        <table style="width:100%;border-collapse:collapse;text-align:left;font-size:13px;">
                                            <thead>
                                                <tr>
                                                    <th style="padding:10px;border-bottom:1px solid var(--border-color);">계정</th>
                                                    <th style="padding:10px;border-bottom:1px solid var(--border-color);">접속 IP</th>
                                                    <th style="padding:10px;border-bottom:1px solid var(--border-color);">로그인 시각</th>
                                                    <th style="padding:10px;border-bottom:1px solid var(--border-color);">최근 활동</th>
                                                    <th style="padding:10px;border-bottom:1px solid var(--border-color);">브라우저</th>
                                                </tr>
                                            </thead>
                                            <tbody id="session-presence-list">
                                                <tr><td colspan="5" style="padding:14px;color:var(--text-muted);">불러오는 중...</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <p id="session-presence-updated" style="font-size:12px;color:var(--text-muted);margin:12px 0 0;"></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Hidden File Inputs -->
    <input type="file" id="file-input" multiple style="display:none;">
    <input type="file" id="folder-input" multiple webkitdirectory style="display:none;">

    <!-- Floating Upload Toast -->
    <div class="upload-toast" id="upload-toast">
        <div class="toast-head">
            <span id="toast-title">업로드 준비 중...</span>
            <button type="button" id="btn-cancel-upload" onclick="cancelUpload()" style="border:none;background:#fee2e2;color:#dc2626;padding:3px 9px;border-radius:6px;font-size:11.5px;font-weight:700;cursor:pointer;">중지</button>
        </div>
        <div class="toast-file" id="toast-filename"></div>
        <div class="toast-bar-bg">
            <div class="toast-bar-fill" id="toast-progress-bar"></div>
        </div>
        <div class="toast-foot">
            <span id="toast-count">0 / 0</span>
            <span id="toast-percent">0%</span>
        </div>
    </div>

    <!-- Right-click Context Menu -->
    <div class="context-menu" id="context-menu"></div>

    <!-- Preview Modal -->
    <div class="modal-overlay" id="preview-modal" onclick="closePreview()">
        <div class="modal-content" onclick="event.stopPropagation()">
            <button type="button" class="modal-close" onclick="closePreview()" aria-label="닫기">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
            <div id="preview-body" style="width:100%;height:100%;display:flex;justify-content:center;align-items:center;background:#f8fafc;"></div>
        </div>
    </div>

    <!-- New Folder Modal -->
    <div id="new-folder-modal" class="modal-overlay" style="display:none;background:rgba(15,23,42,0.55);z-index:1006;" onclick="closeNewFolderModal()">
        <div class="folder-auth-card" onclick="event.stopPropagation()">
            <h3 class="folder-auth-title" data-i18n="new_folder_title">새 폴더 만들기</h3>
            <p class="folder-auth-sub" id="new-folder-modal-sub" data-i18n="new_folder_sub">현재 위치에 새 폴더를 생성합니다.</p>
            <input type="text" class="form-input" id="new-folder-input-name" placeholder="폴더 이름 입력" data-i18n-ph="folder_name_ph" maxlength="100" style="margin-bottom:16px;" onkeydown="if(event.key==='Enter') submitCreateFolder(); if(event.key==='Escape') closeNewFolderModal();">
            <div class="folder-auth-btns">
                <button type="button" class="btn-secondary" onclick="closeNewFolderModal()" data-i18n="cancel">취소</button>
                <button type="button" class="btn-primary" onclick="submitCreateFolder()" data-i18n="create">생성</button>
            </div>
        </div>
    </div>

    <!-- Folder Auth Modal -->
    <div id="folder-auth-modal">
        <div class="folder-auth-card">
            <h3 class="folder-auth-title" id="folder-auth-modal-title" data-i18n="sec_folder_title">보안 폴더 접근</h3>
            <p class="folder-auth-sub" data-i18n="sec_folder_sub">이 폴더는 비밀번호로 보호되어 있습니다.</p>
            <input type="password" class="form-input" id="folder-input-password" placeholder="비밀번호 입력" data-i18n-ph="folder_pw_input_ph" style="text-align:center;" onkeydown="if(event.key==='Enter') submitFolderAuth()">
            <div class="folder-auth-btns">
                <button type="button" class="btn-secondary" onclick="closeFolderAuthModal()" data-i18n="cancel">취소</button>
                <button type="button" class="btn-primary" onclick="submitFolderAuth()" data-i18n="confirm">확인</button>
            </div>
        </div>
    </div>

    <script>
        const I18N = {
            en: {
                upload: "Upload",
                upload_file: "Upload File",
                upload_folder: "Upload Folder",
                new_folder: "New Folder",
                drive: "Drive",
                my_drive: "My Drive (All)",
                workspace: "Workspace",
                tips: "Tips & Notices",
                requests: "Requests",
                settings: "Admin Settings",
                storage_cap: "Storage Capacity",
                storage_checking: "Checking storage...",
                search_ph: "Search files & folders in current directory...",
                online: "Online",
                online_unit: "",
                logout: "Logout",
                up: "Up",
                current_path_title: "Current server directory path",
                sel_count: "{0} selected",
                download: "Download",
                move_copy: "Move / Copy",
                delete: "Delete",
                move_here: "Move here ({0})",
                select_all: "Select All",
                sort_name: "Name",
                sort_date: "Date Modified",
                sort_size: "Size",
                list_view: "List View",
                grid_view: "Grid View",
                drag_overlay: "Drop files or folders here to upload",
                drag_overlay_sub: "Files will be saved in current directory",
                drag_hint: "Drag and drop files/folders anywhere to upload. (Caution when sharing .exe, .cmd)",
                tips_title: "Tips & Announcements",
                tip_title_ph: "Notice or TIP title",
                tip_body_ph: "Enter instructions or announcement details...",
                tip_post: "Post TIP",
                tip_update: "Save Changes",
                tip_cancel: "Cancel",
                req_title: "Request Board",
                req_title_ph: "Request title",
                req_body_ph: "Describe software, files, or assistance needed...",
                req_post: "Submit Request",
                loading: "Loading...",
                tab_storage: "Storage & System",
                tab_folders: "Folder Security",
                tab_passwords: "Account Passwords",
                tab_presence: "Active Sessions",
                drive_status: "Drive Status (D:)",
                disk_used: "Used: {0}",
                disk_free: "Free: {0} / Total: {1}",
                cleanup_title: "System Cleanup (Temporary Upload Cache)",
                cleanup_desc: "Deletes orphaned upload chunks (.tmp) from interrupted or cancelled uploads.",
                cleanup_btn: "Clean Temporary Files",
                folder_pw_title: "Folder Password Protection",
                folder_pw_desc: "Specify folder path and set a password. (Leave empty to remove password)",
                folder_target_lbl: "Target Folder Path",
                folder_target_ph: "e.g. Finance/Reports",
                folder_pw_lbl: "Folder Password",
                folder_pw_ph: "Enter new password (leave empty to clear)",
                save_settings: "Save Settings",
                account_pw_title: "Change Account Password",
                target_role_lbl: "Target Account",
                role_admin: "Admin Account (ADMIN)",
                role_guest: "Guest Account (GUEST)",
                current_admin_pw: "Current Admin Password",
                new_pw_lbl: "New Password",
                change_pw_btn: "Change Password",
                presence_title: "Active User Sessions",
                presence_desc: "Sessions with activity in the last 20 minutes.",
                col_account: "Account",
                col_ip: "IP Address",
                col_login_time: "Login Time",
                col_last_act: "Last Activity",
                col_browser: "Browser",
                new_folder_title: "Create New Folder",
                new_folder_sub: "Create a new folder in the current directory.",
                folder_name_ph: "Enter folder name",
                cancel: "Cancel",
                create: "Create",
                sec_folder_title: "Protected Folder",
                sec_folder_sub: "This folder is protected with a password.",
                folder_pw_input_ph: "Enter password",
                confirm: "Confirm",
                empty_folder: "No files or folders in this directory.",
                locked_folder: "Locked Folder",
                folder: "Folder",
                col_name: "Name",
                col_size: "Size",
                col_date: "Date Modified",
                col_action: "Action",
                quick_dl: "Download",
                open_folder: "Open folder",
                session_expired: "Session expired. Please log in again.",
                no_sel_download: "Please select files to download.",
                no_sel_delete: "Please select files to delete.",
                confirm_delete: "Are you sure you want to delete {0} item(s)?",
                items_copied_move: "Selected {0} item(s) to move. Go to destination and click 'Move here'.",
                folder_name_empty: "Please enter a folder name."
            },
            ko: {
                upload: "올리기",
                upload_file: "파일 업로드",
                upload_folder: "폴더 업로드",
                new_folder: "새 폴더 만들기",
                drive: "드라이브",
                my_drive: "내 드라이브 (전체)",
                workspace: "워크스페이스",
                tips: "Tips & 공지",
                requests: "요청사항",
                settings: "관리자 설정",
                storage_cap: "스토리지 용량",
                storage_checking: "용량 확인 중...",
                search_ph: "현재 폴더 내 파일·폴더 검색...",
                online: "접속",
                online_unit: "명",
                logout: "로그아웃",
                up: "상위",
                current_path_title: "현재 서버 디렉터리 경로",
                sel_count: "{0}개 선택",
                download: "다운로드",
                move_copy: "이동 복사",
                delete: "삭제",
                move_here: "여기로 이동 ({0})",
                select_all: "전체선택",
                sort_name: "이름순",
                sort_date: "최신순",
                sort_size: "용량순",
                list_view: "리스트 보기",
                grid_view: "그리드 보기",
                drag_overlay: "여기에 파일이나 폴더를 놓아 업로드하세요",
                drag_overlay_sub: "현재 위치에 바로 저장됩니다",
                drag_hint: "화면 어디든 파일·폴더를 끌어다 놓으면 업로드됩니다. (실행 파일 .exe, .cmd 등 공유 시 실행 주의)",
                tips_title: "Tips & 공지사항",
                tip_title_ph: "공지 또는 TIP 제목",
                tip_body_ph: "사용법이나 공지 내용을 입력하세요...",
                tip_post: "TIP 게시",
                tip_update: "수정 저장",
                tip_cancel: "취소",
                req_title: "요청사항 게시판",
                req_title_ph: "요청 제목",
                req_body_ph: "필요한 프로그램, 자료 또는 요청 내용을 적어주세요...",
                req_post: "요청 등록",
                loading: "불러오는 중...",
                tab_storage: "스토리지 및 시스템",
                tab_folders: "폴더별 보안",
                tab_passwords: "계정 비밀번호",
                tab_presence: "접속 현황",
                drive_status: "드라이브 상태 (D:)",
                disk_used: "사용 중: {0}",
                disk_free: "남은 공간: {0} / 총 {1}",
                cleanup_title: "시스템 정리 (임시 업로드 캐시)",
                cleanup_desc: "업로드 취소나 오류로 남겨진 임시 파일 조각(.tmp)을 삭제합니다.",
                cleanup_btn: "임시 데이터 정리",
                folder_pw_title: "폴더별 비밀번호 설정 / 해제",
                folder_pw_desc: "대상 폴더 경로를 입력하고 비밀번호를 지정하세요. (비밀번호를 비우면 잠금 해제)",
                folder_target_lbl: "대상 폴더 경로",
                folder_target_ph: "예: 컴퓨터 계열/운영부",
                folder_pw_lbl: "폴더 비밀번호",
                folder_pw_ph: "새 비밀번호 입력 (해제 시 비움)",
                save_settings: "설정 저장",
                account_pw_title: "계정 비밀번호 변경",
                target_role_lbl: "변경 대상",
                role_admin: "관리자 계정 (ADMIN)",
                role_guest: "게스트 계정 (GUEST)",
                current_admin_pw: "현재 관리자 비밀번호",
                new_pw_lbl: "새 비밀번호",
                change_pw_btn: "비밀번호 변경",
                presence_title: "현재 접속 세션",
                presence_desc: "최근 20분 이내 활동한 접속 기록입니다.",
                col_account: "계정",
                col_ip: "접속 IP",
                col_login_time: "로그인 시각",
                col_last_act: "최근 활동",
                col_browser: "브라우저",
                new_folder_title: "새 폴더 만들기",
                new_folder_sub: "현재 위치에 새 폴더를 생성합니다.",
                folder_name_ph: "폴더 이름 입력",
                cancel: "취소",
                create: "생성",
                sec_folder_title: "보안 폴더 접근",
                sec_folder_sub: "이 폴더는 비밀번호로 보호되어 있습니다.",
                folder_pw_input_ph: "비밀번호 입력",
                confirm: "확인",
                empty_folder: "표시할 파일이나 폴더가 없습니다.",
                locked_folder: "잠금 폴더",
                folder: "폴더",
                col_name: "이름",
                col_size: "크기",
                col_date: "수정일",
                col_action: "작업",
                quick_dl: "다운로드",
                open_folder: "폴더 열기",
                session_expired: "세션이 만료되었습니다. 다시 로그인해주세요.",
                no_sel_download: "다운로드할 파일을 선택하세요.",
                no_sel_delete: "삭제할 항목을 선택하세요.",
                confirm_delete: "선택한 {0}개 항목을 삭제하시겠습니까?",
                items_copied_move: "{0}개 항목을 이동 대상으로 선택했습니다. 이동할 폴더로 이동 후 '여기로 이동'을 클릭하세요.",
                folder_name_empty: "폴더 이름을 입력하세요."
            }
        };

        let currentLang = localStorage.getItem('qplus_lang') || SERVER_LANG || 'en';

        function t(key, ...args) {
            const dict = I18N[currentLang] || I18N['en'];
            let str = dict[key] || I18N['en'][key] || key;
            if (args.length > 0) {
                args.forEach((val, idx) => {
                    str = str.replace(new RegExp('\\{' + idx + '\\}', 'g'), val);
                });
            }
            return str;
        }

        function toggleLanguage() {
            currentLang = (currentLang === 'en') ? 'ko' : 'en';
            localStorage.setItem('qplus_lang', currentLang);
            applyLanguage(currentLang);
            if (typeof renderFileList === 'function') {
                renderFileList();
            }
            if (typeof updateDiskUsageUI === 'function') {
                updateDiskUsageUI();
            }
        }

        function applyLanguage(lang) {
            const langBtn = document.getElementById('lang-toggle-btn');
            if (langBtn) {
                langBtn.textContent = (lang === 'en') ? 'EN' : 'KO';
                langBtn.title = (lang === 'en') ? 'Language: English (Click to switch to 한국어)' : '언어: 한국어 (클릭 시 English로 전환)';
            }
            document.querySelectorAll('[data-i18n]').forEach(el => {
                const k = el.getAttribute('data-i18n');
                if (k && I18N[lang] && I18N[lang][k]) {
                    el.textContent = I18N[lang][k];
                }
            });
            document.querySelectorAll('[data-i18n-ph]').forEach(el => {
                const k = el.getAttribute('data-i18n-ph');
                if (k && I18N[lang] && I18N[lang][k]) {
                    el.setAttribute('placeholder', I18N[lang][k]);
                }
            });
            document.querySelectorAll('[data-i18n-title]').forEach(el => {
                const k = el.getAttribute('data-i18n-title');
                if (k && I18N[lang] && I18N[lang][k]) {
                    el.setAttribute('title', I18N[lang][k]);
                }
            });
            const selCount = document.getElementById('selection-count');
            if (selCount) {
                const count = document.querySelectorAll('.item-check:checked').length;
                selCount.textContent = t('sel_count', count);
            }
        }

        let currentPath = '';
        let currentItems = [];
        let viewMode = localStorage.getItem('qplus_view_mode') || 'list';
        let rootFoldersLoaded = false;
        let isAllSelected = false;
        let moveClipboard = [];
        let draggedPaths = [];
        let pendingTargetFolder = '';

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>'"]/g, c => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
            }[c]));
        }

        function flatSvgIcon(type, size = 22) {
            const s = size;
            switch (type) {
                case 'folder':
                    return `<svg width="${s}" height="${s}" viewBox="0 0 24 24" fill="#0284c7"><path d="M10 4H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-8l-2-2z"/></svg>`;
                case 'lock':
                    return `<svg width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>`;
                case 'img':
                    return `<svg width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="#262626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5" fill="#262626"/><polyline points="21 15 16 10 5 21"/></svg>`;
                case 'vid':
                    return `<svg width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="#262626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><polygon points="10 9 15 12 10 15 10 9" fill="#262626"/></svg>`;
                case 'zip':
                    return `<svg width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="#404040" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8v13H3V8"/><path d="M1 3h22v5H1z"/><path d="M10 12h4"/></svg>`;
                case 'pdf':
                    return `<svg width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="#262626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="15" x2="15" y2="15"/></svg>`;
                case 'doc':
                    return `<svg width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="#404040" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>`;
                case 'exe':
                    return `<svg width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="#141414" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 17 10 11 4 5"/><line x1="12" y1="19" x2="20" y2="19"/></svg>`;
                default:
                    return `<svg width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="#525252" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>`;
            }
        }

        function toggleMobileSidebar(open) {
            const sb = document.getElementById('app-sidebar');
            const bd = document.getElementById('sidebar-backdrop');
            if (!sb || !bd) return;
            sb.classList.toggle('open', open);
            bd.classList.toggle('open', open);
        }

        function toggleUploadDropdown(e) {
            e.stopPropagation();
            document.getElementById('upload-dropdown').classList.toggle('open');
        }

        document.addEventListener('click', (e) => {
            const dd = document.getElementById('upload-dropdown');
            if (dd && !e.target.closest('.upload-cta-wrap')) dd.classList.remove('open');
            const ctx = document.getElementById('context-menu');
            if (ctx && !e.target.closest('#context-menu')) ctx.style.display = 'none';
        });

        function triggerUploadFile() {
            document.getElementById('upload-dropdown').classList.remove('open');
            switchAppView('drive');
            document.getElementById('file-input').click();
        }

        function triggerUploadFolder() {
            document.getElementById('upload-dropdown').classList.remove('open');
            switchAppView('drive');
            document.getElementById('folder-input').click();
        }

        function switchAppView(viewName) {
            document.querySelectorAll('.app-view').forEach(v => v.classList.remove('active'));
            const target = document.getElementById('view-' + viewName);
            if (target) target.classList.add('active');

            document.querySelectorAll('.sidebar .nav-item').forEach(btn => btn.classList.remove('active'));
            if (viewName === 'drive') {
                const rootBtn = document.getElementById('nav-drive-root');
                if (rootBtn && currentPath === '') rootBtn.classList.add('active');
            } else {
                const navBtn = document.getElementById('nav-' + viewName);
                if (navBtn) navBtn.classList.add('active');
            }

            if (viewName === 'tips') loadTips();
            if (viewName === 'requests') loadRequests();
            if (viewName === 'settings') { loadSysInfo(); loadSessionPresence(); }
            toggleMobileSidebar(false);
        }

        function openDriveRoot() {
            switchAppView('drive');
            loadDirectory('');
        }

        function setViewMode(mode) {
            viewMode = mode === 'grid' ? 'grid' : 'list';
            localStorage.setItem('qplus_view_mode', viewMode);
            document.getElementById('btn-view-list').classList.toggle('active', viewMode === 'list');
            document.getElementById('btn-view-grid').classList.toggle('active', viewMode === 'grid');
            renderCurrentItems();
        }

        function renderBreadcrumb(path) {
            const list = document.getElementById('breadcrumb-list');
            const pathText = document.getElementById('current-path-text');
            const pathBadge = document.getElementById('current-path-badge');
            const normalized = String(path || '').replace(/\\/g, '/').replace(/^\/+|\/+$/g, '');
            const parts = normalized ? normalized.split('/') : [];

            let html = `<button class="crumb-btn ${parts.length === 0 ? 'current' : ''}" onclick="loadDirectory('')">내 드라이브</button>`;
            let acc = '';
            parts.forEach((part, idx) => {
                acc += part + '/';
                const isLast = idx === parts.length - 1;
                const target = acc;
                html += `<span class="crumb-sep">›</span><button class="crumb-btn ${isLast ? 'current' : ''}" data-crumb="${escapeHtml(target)}" onclick="loadDirectory(this.dataset.crumb)">${escapeHtml(part)}</button>`;
            });
            list.innerHTML = html;

            const fullDisplay = parts.length === 0 ? '/' : '/' + parts.join('/');
            if (pathText) pathText.innerText = fullDisplay;
            if (pathBadge) pathBadge.title = '현재 디렉터리: ' + fullDisplay;
        }

        function goUpDirectory() {
            const parts = currentPath.split('/').filter(Boolean);
            if (parts.length > 0) {
                parts.pop();
                loadDirectory(parts.length > 0 ? parts.join('/') + '/' : '');
            }
        }

        function getFileBadgeInfo(item) {
            if (item.isDir) {
                return { iconType: 'folder', locked: (item.isProtected && !item.isUnlocked), isImage: false };
            }
            const m = (item.rawName || '').match(/\.([a-z0-9_-]+)$/i);
            const ext = m ? m[1].toLowerCase() : 'file';
            if (['jpg','jpeg','png','gif','webp','bmp','ico','heic'].includes(ext)) return { iconType: 'img', locked: false, isImage: ['jpg','jpeg','png','gif','webp'].includes(ext) };
            if (['mp4','mkv','avi','mov','wmv','webm','m4v'].includes(ext)) return { iconType: 'vid', locked: false, isImage: false };
            if (['zip','7z','rar','tar','gz','tgz','iso','img'].includes(ext)) return { iconType: 'zip', locked: false, isImage: false };
            if (ext === 'pdf') return { iconType: 'pdf', locked: false, isImage: false };
            if (['doc','docx','hwp','hwpx','txt','md','csv','xls','xlsx','ppt','pptx'].includes(ext)) return { iconType: 'doc', locked: false, isImage: false };
            if (['exe','msi','cmd','bat','apk','apks'].includes(ext)) return { iconType: 'exe', locked: false, isImage: false };
            return { iconType: 'file', locked: false, isImage: false };
        }

        function populateSidebarRootFolders(items) {
            const container = document.getElementById('sidebar-root-folders');
            if (!container) return;
            const dirs = items.filter(it => it.isDir);
            container.innerHTML = dirs.map((dir, i) => `
                <button class="nav-item" data-href="${escapeHtml(dir.href)}" onclick="openRootShortcut(${i})">
                    <span class="nav-icon" style="color:#0284c7;">${flatSvgIcon('folder', 17)}</span>
                    <span class="nav-label">${dir.name}</span>
                    ${dir.isProtected && !dir.isUnlocked ? `<span class="nav-lock">${flatSvgIcon('lock', 13)}</span>` : ''}
                </button>
            `).join('');
            window.__rootShortcutItems = dirs;
        }

        function openRootShortcut(idx) {
            const dir = (window.__rootShortcutItems || [])[idx];
            if (!dir) return;
            switchAppView('drive');
            handleFolderOpen(dir.rawName, dir.href, dir.isProtected, dir.isUnlocked);
        }

        async function loadDirectory(targetPath) {
            currentPath = targetPath;
            renderBreadcrumb(targetPath);
            isAllSelected = false;
            updateSelectionBar();

            const upBtn = document.getElementById('btn-go-up');
            if (upBtn) upBtn.style.display = currentPath === '' ? 'none' : 'inline-flex';

            const rootNav = document.getElementById('nav-drive-root');
            if (rootNav) rootNav.classList.toggle('active', currentPath === '');

            try {
                const response = await fetch('?action=list&dir=' + encodeURIComponent(targetPath));
                if (response.status === 401) { alert('세션이 만료되었습니다.'); location.reload(); return; }
                const items = await response.json();

                if (response.status === 403 && items.error === 'AUTH_REQUIRED') {
                    promptFolderAuth(items.folder);
                    return;
                }
                if (items.error) {
                    document.getElementById('file-list-area').innerHTML = `<div class="empty-state" style="color:var(--danger);">${escapeHtml(items.error)}</div>`;
                    return;
                }

                currentItems = Array.isArray(items) ? items : [];
                if (targetPath === '' || !rootFoldersLoaded) {
                    if (targetPath === '') {
                        populateSidebarRootFolders(currentItems);
                        rootFoldersLoaded = true;
                    }
                }
                renderCurrentItems();
            } catch (e) {
                document.getElementById('file-list-area').innerHTML = '<div class="empty-state" style="color:var(--danger);">데이터를 불러올 수 없습니다.</div>';
            }
        }

        function handleSearchInput() {
            const input = document.getElementById('file-search-input');
            const clearBtn = document.getElementById('search-clear-btn');
            if (clearBtn) clearBtn.style.display = input.value ? 'block' : 'none';
            renderCurrentItems();
        }

        function clearSearch() {
            document.getElementById('file-search-input').value = '';
            document.getElementById('search-clear-btn').style.display = 'none';
            renderCurrentItems();
        }

        function getFilteredSortedItems() {
            const q = (document.getElementById('file-search-input')?.value || '').trim().toLowerCase();
            const sortBy = document.getElementById('sort-select')?.value || 'name';

            let list = currentItems.filter(item => !q || (item.rawName || '').toLowerCase().includes(q));
            list.sort((a, b) => {
                if (a.isDir !== b.isDir) return a.isDir ? -1 : 1;
                if (sortBy === 'mtime_desc') return (b.mtime || 0) - (a.mtime || 0);
                if (sortBy === 'size_desc') return (b.size || 0) - (a.size || 0);
                return (a.rawName || '').localeCompare(b.rawName || '', 'ko', { numeric: true, sensitivity: 'base' });
            });
            return list;
        }

        function renderCurrentItems() {
            const area = document.getElementById('file-list-area');
            const items = getFilteredSortedItems();
            window.__renderedItems = items;

            if (!items.length) {
                area.innerHTML = `<div class="empty-state">${t('empty_folder')}</div>`;
                updateSelectionBar();
                return;
            }

            if (viewMode === 'grid') {
                area.innerHTML = `<div class="grid-container">` + items.map((item, idx) => {
                    const info = getFileBadgeInfo(item);
                    const thumbHtml = info.isImage
                        ? `<img src="/download.php?file=${encodeURIComponent(item.href)}&view=1" loading="lazy" alt="${item.name}">`
                        : `<span class="flat-file-icon">${flatSvgIcon(info.iconType, 46)}${info.locked ? `<span class="flat-lock-tag">${flatSvgIcon('lock', 15)}</span>` : ''}</span>`;

                    const dragAttrs = USER_ROLE === 'admin'
                        ? `draggable="true" ondragstart="beginItemDragByIdx(event, ${idx})"` : '';
                    const dropAttrs = USER_ROLE === 'admin' && item.isDir
                        ? `ondragover="allowItemDrop(event)" ondragleave="leaveItemDrop(event)" ondrop="dropItemByIdx(event, ${idx})"` : '';

                    return `<div class="grid-card" id="item_${idx}" ${dragAttrs} ${dropAttrs}
                                 onclick="handleCardClick(${idx}, event)"
                                 oncontextmenu="openContextMenu(event, ${idx})">
                        <div class="grid-thumb">
                            <div class="grid-checkbox-wrap" onclick="event.stopPropagation()">
                                <input type="checkbox" class="file-checkbox" id="file_cb_${idx}" data-href="${escapeHtml(item.href)}" onchange="toggleHighlight(this, 'item_${idx}')">
                            </div>
                            <a class="grid-quick-dl" href="/download.php?file=${encodeURIComponent(item.href)}" onclick="event.stopPropagation()" title="다운로드">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            </a>
                            ${thumbHtml}
                        </div>
                        <div class="grid-info">
                            <div class="grid-title" title="${item.name}">${item.name}</div>
                            <div class="grid-meta">
                                <span>${item.isDir ? (info.locked ? t('locked_folder') : t('folder')) : escapeHtml(item.sizeFormatted || '-')}</span>
                                <span>${escapeHtml((item.mtimeFormatted || '').slice(0, 10))}</span>
                            </div>
                        </div>
                    </div>`;
                }).join('') + `</div>`;
            } else {
                area.innerHTML = `<div class="list-table-wrap">
                    <div class="list-header">
                        <div></div>
                        <div>이름</div>
                        <div>크기</div>
                        <div>수정한 날짜</div>
                        <div style="text-align:right;">작업</div>
                    </div>` + items.map((item, idx) => {
                    const info = getFileBadgeInfo(item);
                    const iconHtml = `<span class="flat-file-icon">${flatSvgIcon(info.iconType, 21)}${info.locked ? `<span class="flat-lock-tag">${flatSvgIcon('lock', 11)}</span>` : ''}</span>`;
                    const dragAttrs = USER_ROLE === 'admin'
                        ? `draggable="true" ondragstart="beginItemDragByIdx(event, ${idx})"` : '';
                    const dropAttrs = USER_ROLE === 'admin' && item.isDir
                        ? `ondragover="allowItemDrop(event)" ondragleave="leaveItemDrop(event)" ondrop="dropItemByIdx(event, ${idx})"` : '';

                    return `<div class="list-row" id="item_${idx}" ${dragAttrs} ${dropAttrs}
                                 oncontextmenu="openContextMenu(event, ${idx})">
                        <div>
                            <input type="checkbox" class="file-checkbox" id="file_cb_${idx}" data-href="${escapeHtml(item.href)}" onchange="toggleHighlight(this, 'item_${idx}')">
                        </div>
                        <div class="row-name-cell" onclick="openItemByIdx(${idx})">
                            ${iconHtml}
                            <span class="row-name-text" title="${item.name}">${item.name}</span>
                        </div>
                        <div class="row-meta">${item.isDir ? '-' : escapeHtml(item.sizeFormatted || '-')}</div>
                        <div class="row-meta">${escapeHtml(item.mtimeFormatted || '-')}</div>
                        <div class="row-actions">
                            ${item.isDir
                                ? `<button type="button" class="mini-btn preview" onclick="openItemByIdx(${idx})">열기</button>
                                   <a href="/download.php?file=${encodeURIComponent(item.href)}" class="mini-btn">ZIP</a>`
                                : `<button type="button" class="mini-btn preview" onclick="openItemByIdx(${idx})">미리보기</button>
                                   <a href="/download.php?file=${encodeURIComponent(item.href)}" class="mini-btn">다운로드</a>`
                            }
                            ${USER_ROLE === 'admin' ? `<button type="button" class="mini-btn del" onclick="deleteItemByIdx(${idx})">삭제</button>` : ''}
                        </div>
                    </div>`;
                }).join('') + `</div>`;
            }
            updateSelectionBar();
        }

        function handleCardClick(idx, event) {
            if (event && event.target.closest('input, a, button')) return;
            openItemByIdx(idx);
        }

        function openItemByIdx(idx) {
            const item = (window.__renderedItems || [])[idx];
            if (!item) return;
            if (item.isDir) {
                handleFolderOpen(item.rawName, item.href, item.isProtected, item.isUnlocked);
            } else {
                openPreview(item.rawName, item.href);
            }
        }

        function deleteItemByIdx(idx) {
            const item = (window.__renderedItems || [])[idx];
            if (!item) return;
            deleteFile(item.href);
        }

        function handleFolderOpen(rawName, safePath, isProtected, isUnlocked) {
            if (isProtected && !isUnlocked && USER_ROLE !== 'admin') {
                promptFolderAuth(safePath);
            } else {
                loadDirectory(safePath);
            }
        }

        function toggleHighlight(checkbox, itemId) {
            const el = document.getElementById(itemId);
            if (el) el.classList.toggle('selected', checkbox.checked);
            updateSelectionBar();
        }

        function updateSelectionBar() {
            const checked = document.querySelectorAll('.file-checkbox:checked');
            const bar = document.getElementById('selection-bar');
            const countEl = document.getElementById('selection-count');
            if (bar && countEl) {
                bar.classList.toggle('visible', checked.length > 0);
                countEl.innerText = `${checked.length}개 선택`;
            }
        }

        function toggleSelectAll() {
            isAllSelected = !isAllSelected;
            document.querySelectorAll('.file-checkbox').forEach((cb, index) => {
                cb.checked = isAllSelected;
                const el = document.getElementById(`item_${index}`);
                if (el) el.classList.toggle('selected', isAllSelected);
            });
            updateSelectionBar();
        }

        /* Context Menu */
        function openContextMenu(e, idx) {
            e.preventDefault();
            const item = (window.__renderedItems || [])[idx];
            if (!item) return;
            const menu = document.getElementById('context-menu');
            const openSvg = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>`;
            const dlSvg = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>`;
            const copySvg = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>`;
            const delSvg = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>`;

            let html = `
                <button type="button" onclick="openItemByIdx(${idx})">${openSvg}<span>${item.isDir ? '폴더 열기' : '미리보기'}</span></button>
                <button type="button" onclick="window.location.href='/download.php?file=${encodeURIComponent(item.href)}'">${dlSvg}<span>${item.isDir ? 'ZIP 다운로드' : '다운로드'}</span></button>
            `;
            if (USER_ROLE === 'admin') {
                html += `
                    <button type="button" onclick="copySingleForMove(${idx})">${copySvg}<span>이동용 복사</span></button>
                    <button type="button" class="danger" onclick="deleteItemByIdx(${idx})">${delSvg}<span>삭제</span></button>
                `;
            }
            menu.innerHTML = html;
            menu.style.display = 'block';
            const x = Math.min(e.clientX, window.innerWidth - 185);
            const y = Math.min(e.clientY, window.innerHeight - 180);
            menu.style.left = x + 'px';
            menu.style.top = y + 'px';
        }

        function copySingleForMove(idx) {
            const item = (window.__renderedItems || [])[idx];
            if (!item) return;
            moveClipboard = [item.href];
            updateMoveClipboardUI();
        }

        function copySelectedForMove() {
            const selected = getSelectedMovePaths();
            if (!selected.length) return;
            moveClipboard = selected;
            updateMoveClipboardUI();
        }

        function updateMoveClipboardUI() {
            const pasteBtn = document.getElementById('btn-paste-move');
            const countSpan = document.getElementById('move-clip-count');
            if (pasteBtn && countSpan) {
                pasteBtn.style.display = moveClipboard.length > 0 ? 'inline-flex' : 'none';
                countSpan.innerText = moveClipboard.length;
            }
        }

        function pasteMovedItems() {
            if (moveClipboard.length) moveItems(moveClipboard, currentPath);
        }

        function getSelectedMovePaths() {
            return Array.from(document.querySelectorAll('.file-checkbox:checked'))
                .map(cb => cb.dataset.href).filter(Boolean);
        }

        async function moveItems(sources, destination) {
            if (USER_ROLE !== 'admin' || !sources.length) return;
            const fd = new URLSearchParams();
            fd.append('action', 'move_items');
            fd.append('sources', JSON.stringify(sources));
            fd.append('destination', destination);
            fd.append('csrf_token', CSRF_TOKEN);
            try {
                const res = await fetch('index.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: fd.toString()
                });
                const result = await res.json();
                if (!res.ok || !result.success) { alert(result.msg || '이동에 실패했습니다.'); return; }
                moveClipboard = [];
                updateMoveClipboardUI();
                loadDirectory(currentPath);
            } catch (err) {
                alert('이동 중 서버 오류가 발생했습니다.');
            }
        }

        function beginItemDragByIdx(event, idx) {
            if (USER_ROLE !== 'admin') return;
            const item = (window.__renderedItems || [])[idx];
            if (!item) return;
            const selected = getSelectedMovePaths();
            draggedPaths = selected.includes(item.href) ? selected : [item.href];
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', JSON.stringify(draggedPaths));
        }

        function allowItemDrop(event) {
            if (USER_ROLE !== 'admin') return;
            event.preventDefault();
            event.currentTarget.classList.add('drop-target');
            event.dataTransfer.dropEffect = 'move';
        }

        function leaveItemDrop(event) {
            event.currentTarget.classList.remove('drop-target');
        }

        function dropItemByIdx(event, idx) {
            if (USER_ROLE !== 'admin') return;
            event.preventDefault();
            event.stopPropagation();
            event.currentTarget.classList.remove('drop-target');
            const destItem = (window.__renderedItems || [])[idx];
            if (!destItem || !destItem.isDir) return;
            const sources = draggedPaths.length ? draggedPaths : JSON.parse(event.dataTransfer.getData('text/plain') || '[]');
            draggedPaths = [];
            moveItems(sources, destItem.href);
        }

        document.addEventListener('keydown', (event) => {
            if (USER_ROLE !== 'admin' || event.target.matches('input, textarea, select')) return;
            if (event.ctrlKey && event.key.toLowerCase() === 'c') {
                const selected = getSelectedMovePaths();
                if (!selected.length) return;
                event.preventDefault();
                moveClipboard = selected;
                updateMoveClipboardUI();
            }
            if (event.ctrlKey && event.key.toLowerCase() === 'v') {
                if (!moveClipboard.length) return;
                event.preventDefault();
                moveItems(moveClipboard, currentPath);
            }
        });

        /* Download & Delete */
        function downloadSelectedFiles() {
            const checkboxes = document.querySelectorAll('.file-checkbox:checked');
            if (!checkboxes.length) { alert('다운로드할 대상을 선택하세요.'); return; }
            if (!confirm(`선택한 ${checkboxes.length}개 항목을 다운로드합니다.`)) return;
            let delay = 0;
            checkboxes.forEach(cb => {
                const url = '/download.php?file=' + encodeURIComponent(cb.dataset.href);
                setTimeout(() => {
                    const a = document.createElement('a'); a.href = url; a.style.display = 'none';
                    document.body.appendChild(a); a.click(); document.body.removeChild(a);
                }, delay);
                delay += 450;
            });
            toggleSelectAll();
        }

        function deleteFile(filePath) {
            if (USER_ROLE !== 'admin') { alert('삭제 권한이 없습니다.'); return; }
            if (!confirm('삭제를 진행하시겠습니까?')) return;
            fetch('/delete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                body: JSON.stringify({ path: filePath })
            }).then(res => { if (res.ok) loadDirectory(currentPath); else alert('삭제 실패'); })
              .catch(() => alert('오류 발생'));
        }

        async function deleteSelectedFiles() {
            if (USER_ROLE !== 'admin') return;
            const checkboxes = Array.from(document.querySelectorAll('.file-checkbox:checked'));
            if (!checkboxes.length) { alert('삭제할 대상을 선택하세요.'); return; }
            if (!confirm(`선택한 ${checkboxes.length}개 항목을 삭제하시겠습니까?\n복구할 수 없습니다.`)) return;

            const paths = checkboxes.map(cb => cb.dataset.href).sort((a, b) => b.length - a.length);
            let failed = 0;
            for (const path of paths) {
                try {
                    const res = await fetch('/delete.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
                        body: JSON.stringify({ path })
                    });
                    if (!res.ok) failed++;
                } catch (e) { failed++; }
            }
            isAllSelected = false;
            if (failed > 0) alert(`${failed}개 항목 삭제에 실패했습니다.`);
            loadDirectory(currentPath);
        }

        function openNewFolderModal() {
            const dd = document.getElementById('upload-dropdown');
            if (dd) dd.classList.remove('open');
            const sub = document.getElementById('new-folder-modal-sub');
            const normalized = String(currentPath || '').replace(/\\/g, '/').replace(/^\/+|\/+$/g, '');
            const locText = normalized ? `/${normalized}/` : '내 드라이브 최상위(/)';
            if (sub) sub.innerText = `위치: ${locText}`;
            const input = document.getElementById('new-folder-input-name');
            if (input) input.value = '';
            const modal = document.getElementById('new-folder-modal');
            if (modal) modal.style.display = 'flex';
            setTimeout(() => { if (input) input.focus(); }, 60);
        }

        function closeNewFolderModal() {
            const modal = document.getElementById('new-folder-modal');
            if (modal) modal.style.display = 'none';
        }

        async function submitCreateFolder() {
            const input = document.getElementById('new-folder-input-name');
            const folderName = input ? input.value.trim() : '';
            if (!folderName) {
                alert('폴더 이름을 입력하세요.');
                if (input) input.focus();
                return;
            }
            if (/[<>:"\/\\|?*]/.test(folderName)) {
                alert('폴더 이름에 사용할 수 없는 특수문자가 포함되어 있습니다. (< > : " / \\ | ? *)');
                if (input) input.focus();
                return;
            }
            const fd = new URLSearchParams();
            fd.append('action', 'create_folder');
            fd.append('path', currentPath);
            fd.append('folder_name', folderName);
            fd.append('csrf_token', CSRF_TOKEN);
            try {
                const res = await fetch('index.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: fd.toString()
                });
                const data = await res.json();
                if (data.success) {
                    closeNewFolderModal();
                    loadDirectory(currentPath);
                } else {
                    alert('오류: ' + (data.msg || '폴더 생성에 실패했습니다.'));
                }
            } catch (e) {
                alert('서버 통신 오류가 발생했습니다.');
            }
        }

        function createFolder() {
            openNewFolderModal();
        }

        /* Drag & Drop Upload + Floating Toast */
        const driveDropArea = document.getElementById('drive-drop-area');
        const dragOverlay = document.getElementById('drag-overlay');
        const fileInput = document.getElementById('file-input');
        const folderInput = document.getElementById('folder-input');

        fileInput.addEventListener('change', (e) => { if (e.target.files.length > 0) { uploadFiles(Array.from(e.target.files)); e.target.value = ''; } });
        folderInput.addEventListener('change', (e) => { if (e.target.files.length > 0) { uploadFiles(Array.from(e.target.files)); e.target.value = ''; } });

        let dragDepth = 0;
        driveDropArea.addEventListener('dragenter', (e) => {
            if (!e.dataTransfer || !Array.from(e.dataTransfer.types || []).includes('Files')) return;
            e.preventDefault();
            dragDepth++;
            dragOverlay.classList.add('active');
        });
        driveDropArea.addEventListener('dragover', (e) => {
            if (!e.dataTransfer || !Array.from(e.dataTransfer.types || []).includes('Files')) return;
            e.preventDefault();
        });
        driveDropArea.addEventListener('dragleave', (e) => {
            if (!e.dataTransfer || !Array.from(e.dataTransfer.types || []).includes('Files')) return;
            dragDepth = Math.max(0, dragDepth - 1);
            if (dragDepth === 0) dragOverlay.classList.remove('active');
        });
        driveDropArea.addEventListener('drop', async (e) => {
            if (!e.dataTransfer || !Array.from(e.dataTransfer.types || []).includes('Files')) return;
            e.preventDefault();
            dragDepth = 0;
            dragOverlay.classList.remove('active');

            const items = e.dataTransfer.items;
            if (!items || !items.length) return;
            let filesToUpload = [];

            async function readEntry(entry, path = '') {
                if (entry.isFile) {
                    return new Promise(resolve => {
                        entry.file(f => {
                            Object.defineProperty(f, 'webkitRelativePath', { value: path + f.name, writable: false });
                            filesToUpload.push(f);
                            resolve();
                        });
                    });
                } else if (entry.isDirectory) {
                    return new Promise(resolve => {
                        const dirReader = entry.createReader();
                        let entries = [];
                        const readEntries = () => {
                            dirReader.readEntries(async (res) => {
                                if (!res.length) {
                                    for (let i = 0; i < entries.length; i++) await readEntry(entries[i], path + entry.name + '/');
                                    resolve();
                                } else {
                                    entries = entries.concat(Array.from(res));
                                    readEntries();
                                }
                            });
                        };
                        readEntries();
                    });
                }
            }

            const promises = [];
            for (let i = 0; i < items.length; i++) {
                const entry = items[i].webkitGetAsEntry ? items[i].webkitGetAsEntry() : null;
                if (entry) promises.push(readEntry(entry));
            }
            await Promise.all(promises);
            if (filesToUpload.length > 0) uploadFiles(filesToUpload);
        });

        let isUploadCancelled = false;
        let activeUploadControllers = [];
        let currentUploadGuids = [];

        function cancelUpload() {
            if (!confirm('현재 진행 중인 업로드를 중지하시겠습니까?')) return;
            isUploadCancelled = true;
            activeUploadControllers.forEach(c => c.abort());
            activeUploadControllers = [];
            currentUploadGuids.forEach(guid => {
                const fd = new URLSearchParams();
                fd.append('action', 'cancel_upload');
                fd.append('fileGuid', guid);
                fd.append('csrf_token', CSRF_TOKEN);
                fetch('/upload.php', { method: 'POST', body: fd });
            });
            document.getElementById('upload-toast').classList.remove('visible');
        }

        function isDangerousExtension(fileName) {
            const ext = fileName.split('.').pop().toLowerCase();
            return ['bat', 'cmd', 'exe', 'msi', 'ps1', 'vbs', 'js', 'jar', 'scr', 'com'].includes(ext);
        }

        function warnDangerousFiles(files) {
            const dangerous = files.filter(f => isDangerousExtension(f.name));
            if (!dangerous.length) return true;
            const names = Array.from(new Set(dangerous.map(f => f.name))).join(', ');
            return confirm(`주의: 위험 확장자 파일이 포함되어 있습니다.\n\n${names}\n\n서버에서 실행하지 않도록 주의하세요. 계속 업로드하시겠습니까?`);
        }

        function updateUploadToast(title, filename, pct, countText) {
            const toast = document.getElementById('upload-toast');
            toast.classList.add('visible');
            document.getElementById('toast-title').innerText = title;
            document.getElementById('toast-filename').innerText = filename;
            document.getElementById('toast-progress-bar').style.width = pct + '%';
            document.getElementById('toast-count').innerText = countText;
            document.getElementById('toast-percent').innerText = pct + '%';
        }

        async function uploadFiles(files) {
            if (!warnDangerousFiles(files)) return;
            isUploadCancelled = false;
            activeUploadControllers = [];
            currentUploadGuids = [];
            const statusInline = document.getElementById('upload-status-inline');
            if (statusInline) statusInline.innerText = '';

            let successCount = 0;
            let failCount = 0;
            let overallError = null;
            const uploadTargetPath = currentPath;

            for (let i = 0; i < files.length; i++) {
                if (isUploadCancelled) break;
                const file = files[i];
                const chunkSize = 50 * 1024 * 1024;
                const totalChunks = Math.max(1, Math.ceil(file.size / chunkSize));
                const fileGuid = 'blob-' + Date.now() + '-' + Math.random().toString(36).substring(2, 11);
                currentUploadGuids.push(fileGuid);

                let uploadedChunks = 0;
                let hasError = false;
                let uploadError = null;

                updateUploadToast('파일 업로드 중...', file.name, 0, `${i + 1} / ${files.length}`);

                const uploadChunk = async (chunkIndex) => {
                    if (hasError || isUploadCancelled) return;
                    const start = chunkIndex * chunkSize;
                    const chunk = file.slice(start, Math.min(start + chunkSize, file.size));
                    const formData = new FormData();
                    formData.append('action', 'upload_chunk');
                    formData.append('file', chunk, file.name);
                    formData.append('csrf_token', CSRF_TOKEN);
                    formData.append('chunkIndex', chunkIndex);
                    formData.append('fileGuid', fileGuid);

                    const controller = new AbortController();
                    activeUploadControllers.push(controller);

                    try {
                        const res = await fetch('/upload.php', { method: 'POST', body: formData, signal: controller.signal });
                        const text = await res.text();
                        if (!res.ok || text.indexOf('Chunk OK') === -1) throw new Error(text || '청크 업로드 실패');
                        uploadedChunks++;
                        const pct = Math.round((uploadedChunks / totalChunks) * 100);
                        updateUploadToast('파일 업로드 중...', file.name, pct, `${i + 1} / ${files.length}`);
                    } catch (err) {
                        if (err.name !== 'AbortError') {
                            hasError = true;
                            uploadError = err.message || '청크 업로드 중 오류가 발생했습니다.';
                        }
                    } finally {
                        activeUploadControllers = activeUploadControllers.filter(c => c !== controller);
                    }
                };

                const queue = Array.from({ length: totalChunks }, (_, idx) => idx);
                const workers = Array(4).fill(Promise.resolve()).map(async () => {
                    while (queue.length > 0 && !hasError && !isUploadCancelled) await uploadChunk(queue.shift());
                });
                await Promise.all(workers);

                if (isUploadCancelled) { overallError = '업로드가 취소되었습니다.'; break; }

                if (!hasError) {
                    updateUploadToast('서버 병합 중...', file.name, 100, `${i + 1} / ${files.length}`);
                    const mergeFd = new FormData();
                    mergeFd.append('action', 'merge');
                    mergeFd.append('fileGuid', fileGuid);
                    mergeFd.append('totalChunks', totalChunks);
                    mergeFd.append('path', uploadTargetPath);
                    mergeFd.append('csrf_token', CSRF_TOKEN);
                    mergeFd.append('relPath', file.webkitRelativePath || file.name);

                    try {
                        const mergeRes = await fetch('/upload.php', { method: 'POST', body: mergeFd });
                        const mergeText = await mergeRes.text();
                        if (mergeRes.ok && mergeText.indexOf('Merge Success') !== -1) {
                            successCount++;
                        } else {
                            failCount++; hasError = true;
                            uploadError = mergeText || '병합 단계에서 오류가 발생했습니다.';
                        }
                    } catch (e) {
                        failCount++; hasError = true;
                        uploadError = e.message || '병합 요청 중 오류가 발생했습니다.';
                    }
                } else { failCount++; }

                if (hasError && uploadError) {
                    overallError = `파일 "${file.name}" 업로드 실패: ${uploadError}`;
                    break;
                }
            }

            document.getElementById('upload-toast').classList.remove('visible');
            if (!isUploadCancelled && !overallError) {
                if (statusInline) {
                    statusInline.style.color = '#0284c7';
                    statusInline.innerText = `업로드 완료 (${successCount}건)`;
                }
                loadSysInfo();
            } else if (overallError) {
                if (statusInline) {
                    statusInline.style.color = 'var(--danger)';
                    statusInline.innerText = overallError;
                }
                alert(overallError);
            }
            if (currentPath === uploadTargetPath) loadDirectory(currentPath);
        }

        /* Preview Modal */
        function openPreview(name, href) {
            const previewBody = document.getElementById('preview-body');
            const modal = document.getElementById('preview-modal');
            previewBody.innerHTML = '';
            const ext = name.split('.').pop().toLowerCase();
            const url = '/download.php?file=' + encodeURIComponent(href) + '&view=1';

            if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
                previewBody.innerHTML = `<img src="${url}" style="max-width:94%;max-height:94%;object-fit:contain;border-radius:8px;box-shadow:0 10px 30px rgba(0,0,0,0.12);">`;
            } else if (ext === 'pdf') {
                previewBody.innerHTML = `<iframe src="${url}" style="width:100%;height:100%;border:none;"></iframe>`;
            } else if (['mp4', 'webm', 'ogg'].includes(ext)) {
                previewBody.innerHTML = `<video src="${url}" controls autoplay style="max-width:95%;max-height:92%;border-radius:8px;background:#000;"></video>`;
            } else if (['txt', 'log', 'md', 'csv', 'json'].includes(ext)) {
                fetch(url).then(r => r.text()).then(text => {
                    const ta = document.createElement('textarea');
                    ta.readOnly = true;
                    ta.value = text;
                    ta.style.cssText = 'width:94%;height:90%;padding:20px;border:none;border-radius:10px;font-family:monospace;font-size:13.5px;background:#fff;resize:none;outline:none;';
                    previewBody.appendChild(ta);
                });
            } else {
                let iconType = 'file';
                if (['zip','7z','rar','tar','gz'].includes(ext)) iconType = 'zip';
                else if (['exe','msi','bat','cmd'].includes(ext)) iconType = 'exe';
                else if (['avi','mkv','mov'].includes(ext)) iconType = 'vid';
                previewBody.innerHTML = `
                    <div style="text-align:center;padding:30px;">
                        <div style="margin-bottom:16px;">${flatSvgIcon(iconType, 64)}</div>
                        <h3 style="margin:0 0 8px;font-size:18px;word-break:break-all;">${escapeHtml(name)}</h3>
                        <p style="font-size:13.5px;color:#64748b;margin:0 0 24px;">웹 브라우저에서 직접 미리보기를 지원하지 않는 형식입니다.</p>
                        <a href="/download.php?file=${encodeURIComponent(href)}" class="btn-primary" style="text-decoration:none;display:inline-flex;align-items:center;gap:8px;">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            <span>파일 다운로드</span>
                        </a>
                    </div>
                `;
            }
            modal.style.display = 'flex';
        }
        function closePreview() {
            document.getElementById('preview-body').innerHTML = '';
            document.getElementById('preview-modal').style.display = 'none';
        }

        /* Folder Auth Modal */
        function promptFolderAuth(folderPath) {
            pendingTargetFolder = folderPath;
            const displayPath = String(folderPath).replace(/\/{2,}/g, '/').replace(/\/+$/, '');
            document.getElementById('folder-auth-modal-title').innerText = `[${displayPath}] 보안 폴더`;
            const pwdInput = document.getElementById('folder-input-password');
            pwdInput.value = '';
            document.getElementById('folder-auth-modal').style.display = 'flex';
            pwdInput.focus();
        }
        function closeFolderAuthModal() {
            document.getElementById('folder-auth-modal').style.display = 'none';
            pendingTargetFolder = '';
        }
        async function submitFolderAuth() {
            const pwd = document.getElementById('folder-input-password').value;
            if (!pwd) { alert('비밀번호를 입력하세요.'); return; }
            const fd = new URLSearchParams();
            fd.append('action', 'verify_folder');
            fd.append('folder', pendingTargetFolder);
            fd.append('password', pwd);
            try {
                const res = await fetch('index.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: fd.toString() });
                const data = await res.json();
                if (data.success) {
                    const target = pendingTargetFolder;
                    closeFolderAuthModal();
                    loadDirectory(target.endsWith('/') ? target : target + '/');
                } else {
                    alert(data.msg || '비밀번호가 일치하지 않습니다.');
                    document.getElementById('folder-input-password').select();
                }
            } catch (e) { alert('인증 중 오류가 발생했습니다.'); }
        }

        /* Tips & Requests */
        async function loadTips() {
            const list = document.getElementById('tip-list');
            if (!list) return;
            try {
                const res = await fetch('?action=tips');
                const tips = await res.json();
                window.tipsForEditing = Array.isArray(tips) ? tips : [];
                if (!res.ok || !tips.length) { list.innerHTML = '<div class="empty-state">등록된 TIP이 없습니다.</div>'; return; }
                list.innerHTML = tips.map(tip => `
                    <article class="surface-card">
                        <h3>${escapeHtml(tip.title)}</h3>
                        <div class="board-meta">게시: ${escapeHtml(tip.created_at)}${tip.updated_at ? ` · 수정: ${escapeHtml(tip.updated_at)}` : ''}</div>
                        <div class="board-body">${escapeHtml(tip.body)}</div>
                        ${USER_ROLE === 'admin' ? `<div style="margin-top:14px;display:flex;gap:8px;">
                            <button class="btn-secondary" type="button" onclick="editTip('${escapeHtml(tip.id)}')">수정</button>
                            <button class="mini-btn del" type="button" onclick="deleteTip('${escapeHtml(tip.id)}')">삭제</button>
                        </div>` : ''}
                    </article>
                `).join('');
            } catch (e) { list.innerHTML = '<div class="empty-state">TIP을 불러오지 못했습니다.</div>'; }
        }

        function editTip(tipId) {
            const tip = (window.tipsForEditing || []).find(i => String(i.id) === String(tipId));
            if (!tip) return;
            document.getElementById('tip-edit-id').value = tip.id;
            document.getElementById('tip-title-input').value = tip.title;
            document.getElementById('tip-body-input').value = tip.body;
            document.getElementById('tip-submit-btn').innerText = 'TIP 수정';
            document.getElementById('tip-cancel-btn').style.display = 'inline-block';
            document.getElementById('tip-title-input').focus();
        }
        function cancelTipEdit() {
            document.getElementById('tip-edit-id').value = '';
            document.getElementById('tip-title-input').value = '';
            document.getElementById('tip-body-input').value = '';
            document.getElementById('tip-submit-btn').innerText = 'TIP 게시';
            document.getElementById('tip-cancel-btn').style.display = 'none';
            document.getElementById('tip-save-status').innerText = '';
        }
        async function saveTip(e) {
            e.preventDefault();
            const status = document.getElementById('tip-save-status');
            const editId = document.getElementById('tip-edit-id').value;
            const fd = new URLSearchParams();
            fd.append('action', editId ? 'update_tip' : 'save_tip');
            if (editId) fd.append('id', editId);
            fd.append('title', document.getElementById('tip-title-input').value);
            fd.append('body', document.getElementById('tip-body-input').value);
            fd.append('csrf_token', CSRF_TOKEN);
            if (status) status.innerText = '저장 중...';
            try {
                const res = await fetch('index.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: fd.toString() });
                const result = await res.json();
                if (!res.ok || !result.success) throw new Error(result.msg || '저장 실패');
                cancelTipEdit();
                if (status) status.innerText = result.msg;
                loadTips();
            } catch (err) { if (status) status.innerText = err.message || '저장 실패'; }
        }
        async function deleteTip(tipId) {
            if (!confirm('이 TIP을 삭제하시겠습니까?')) return;
            const fd = new URLSearchParams();
            fd.append('action', 'delete_tip');
            fd.append('id', tipId);
            fd.append('csrf_token', CSRF_TOKEN);
            try {
                const res = await fetch('index.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: fd.toString() });
                const result = await res.json();
                if (!res.ok || !result.success) throw new Error(result.msg || '삭제 실패');
                loadTips();
            } catch (err) { alert(err.message); }
        }

        async function loadRequests() {
            const list = document.getElementById('request-list');
            if (!list) return;
            try {
                const res = await fetch('?action=requests');
                const requests = await res.json();
                if (!res.ok || !requests.length) { list.innerHTML = '<div class="empty-state">등록된 요청이 없습니다.</div>'; return; }
                list.innerHTML = requests.map(r => `
                    <article class="surface-card">
                        <h3>${escapeHtml(r.title)}</h3>
                        <div class="board-meta">${escapeHtml(r.created_at)} · ${r.role === 'admin' ? '관리자' : '게스트'}</div>
                        <div class="board-body">${escapeHtml(r.body)}</div>
                        <span class="status-badge">${escapeHtml(r.status || '접수')}</span>
                        ${USER_ROLE === 'admin' ? `<div style="margin-top:12px;"><button class="mini-btn del" type="button" onclick="deleteRequest('${escapeHtml(r.id)}')">삭제</button></div>` : ''}
                    </article>
                `).join('');
            } catch (e) { list.innerHTML = '<div class="empty-state">요청을 불러오지 못했습니다.</div>'; }
        }
        async function saveRequest(e) {
            e.preventDefault();
            const status = document.getElementById('request-save-status');
            const fd = new URLSearchParams();
            fd.append('action', 'save_request');
            fd.append('title', document.getElementById('request-title-input').value);
            fd.append('body', document.getElementById('request-body-input').value);
            fd.append('csrf_token', CSRF_TOKEN);
            if (status) status.innerText = '저장 중...';
            try {
                const res = await fetch('index.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: fd.toString() });
                const result = await res.json();
                if (!res.ok || !result.success) throw new Error(result.msg || '등록 실패');
                document.getElementById('request-title-input').value = '';
                document.getElementById('request-body-input').value = '';
                if (status) status.innerText = result.msg;
                loadRequests();
            } catch (err) { if (status) status.innerText = err.message || '등록 실패'; }
        }
        async function deleteRequest(id) {
            if (USER_ROLE !== 'admin' || !confirm('이 요청을 삭제하시겠습니까?')) return;
            const fd = new URLSearchParams();
            fd.append('action', 'delete_request');
            fd.append('id', id);
            fd.append('csrf_token', CSRF_TOKEN);
            try {
                const res = await fetch('index.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: fd.toString() });
                const result = await res.json();
                if (!res.ok || !result.success) throw new Error(result.msg || '삭제 실패');
                loadRequests();
            } catch (err) { alert(err.message); }
        }

        /* Admin Settings & Monitoring */
        function switchCpTab(sectionId, btn) {
            document.querySelectorAll('.settings-tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.cp-section').forEach(s => s.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById(sectionId).classList.add('active');
            if (sectionId === 'sys-monitor') loadSysInfo();
            if (sectionId === 'sys-presence') loadSessionPresence();
        }

        async function loadSysInfo() {
            try {
                const res = await fetch('?action=sysinfo');
                if (!res.ok) return;
                const data = await res.json();
                const sideBar = document.getElementById('sidebar-disk-bar');
                const sidePct = document.getElementById('sidebar-disk-pct');
                const sideText = document.getElementById('sidebar-disk-text');
                if (sideBar) {
                    sideBar.style.width = data.percent + '%';
                    sideBar.style.backgroundColor = data.percent > 85 ? 'var(--danger)' : 'var(--brand-blue)';
                }
                if (sidePct) sidePct.innerText = data.percent + '%';
                if (sideText) sideText.innerText = `${data.used} / ${data.total} 사용 중`;

                const bar = document.getElementById('disk-bar');
                if (bar) {
                    bar.style.width = data.percent + '%';
                    bar.innerText = data.percent + '%';
                    bar.style.backgroundColor = data.percent > 85 ? 'var(--danger)' : 'var(--brand-blue)';
                    document.getElementById('disk-used-text').innerText = `사용 중: ${data.used}`;
                    document.getElementById('disk-free-text').innerText = `남은 공간: ${data.free} / 총 ${data.total}`;
                }
            } catch (e) {}
        }

        async function clearTempData() {
            if (!confirm('임시 파일을 모두 삭제하시겠습니까?\n진행 중인 업로드 파일도 삭제될 수 있습니다.')) return;
            const msgEl = document.getElementById('clear-temp-msg');
            if (msgEl) { msgEl.style.color = 'var(--text-secondary)'; msgEl.innerText = '청소 진행 중...'; }
            const fd = new URLSearchParams();
            fd.append('action', 'clear_temp');
            try {
                const res = await fetch('index.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: fd.toString() });
                const data = await res.json();
                if (msgEl) {
                    msgEl.style.color = data.success ? '#0284c7' : 'var(--danger)';
                    msgEl.innerText = data.msg;
                }
                if (data.success) loadSysInfo();
            } catch (err) { if (msgEl) msgEl.innerText = '오류가 발생했습니다.'; }
        }

        async function saveFolderPassword(e) {
            e.preventDefault();
            const msgEl = document.getElementById('folder-auth-msg');
            msgEl.style.color = 'var(--text-secondary)'; msgEl.innerText = '저장 중...';
            const fd = new URLSearchParams();
            fd.append('action', 'set_folder_password');
            fd.append('folder_name', document.getElementById('target_folder_name').value);
            fd.append('folder_pw', document.getElementById('target_folder_pw').value);
            try {
                const res = await fetch('index.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: fd.toString() });
                const data = await res.json();
                msgEl.style.color = data.success ? '#0284c7' : 'var(--danger)';
                msgEl.innerText = data.msg;
                if (data.success) { document.getElementById('folder-auth-form').reset(); loadDirectory(currentPath); }
            } catch (err) { msgEl.innerText = '서버 통신 오류가 발생했습니다.'; }
        }

        async function changePassword(e) {
            e.preventDefault();
            const msgEl = document.getElementById('pwd-msg');
            msgEl.style.color = 'var(--text-secondary)'; msgEl.innerText = '변경 중...';
            const fd = new URLSearchParams();
            fd.append('action', 'change_password');
            fd.append('target_role', document.getElementById('target_role').value);
            fd.append('old_pw', document.getElementById('old_pw').value);
            fd.append('new_pw', document.getElementById('new_pw').value);
            try {
                const res = await fetch('index.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: fd.toString() });
                const data = await res.json();
                msgEl.style.color = data.success ? '#0284c7' : 'var(--danger)';
                msgEl.innerText = data.msg;
                if (data.success) document.getElementById('pwd-form').reset();
            } catch (err) { msgEl.innerText = '서버 통신 오류가 발생했습니다.'; }
        }

        async function loadActiveSessionCount() {
            const el = document.getElementById('active-session-number');
            if (!el) return;
            try {
                const res = await fetch('?action=active_count', { cache: 'no-store' });
                if (!res.ok) throw new Error();
                const r = await res.json();
                el.textContent = Number.isInteger(r.count) ? r.count : '-';
            } catch (e) { el.textContent = '-'; }
        }

        async function loadSessionPresence() {
            const list = document.getElementById('session-presence-list');
            if (!list) return;
            try {
                const res = await fetch('?action=session_presence', { cache: 'no-store' });
                if (!res.ok) throw new Error('접속 현황을 불러오지 못했습니다.');
                const sessions = await res.json();
                list.replaceChildren();
                if (!Array.isArray(sessions) || !sessions.length) {
                    const row = list.insertRow();
                    const cell = row.insertCell();
                    cell.colSpan = 5;
                    cell.textContent = '현재 활동 중인 접속이 없습니다.';
                } else {
                    sessions.forEach(s => {
                        const row = list.insertRow();
                        const vals = [
                            s.role === 'admin' ? '관리자' : '게스트',
                            s.ip || '확인 불가',
                            new Date(s.login_at * 1000).toLocaleString('ko-KR'),
                            new Date(s.last_seen * 1000).toLocaleString('ko-KR'),
                            s.user_agent || '확인 불가'
                        ];
                        vals.forEach((v, i) => {
                            const c = row.insertCell();
                            c.style.cssText = 'padding:10px;border-bottom:1px solid #f1f4f8;';
                            c.textContent = v;
                            if (i === 4) c.title = v;
                        });
                    });
                }
                const upd = document.getElementById('session-presence-updated');
                if (upd) upd.textContent = `갱신: ${new Date().toLocaleTimeString('ko-KR')}`;
            } catch (err) {
                list.replaceChildren();
                const row = list.insertRow();
                const c = row.insertCell();
                c.colSpan = 5;
                c.textContent = err.message || '오류';
            }
        }

        /* Idle Timeout & Heartbeat */
        const SESSION_IDLE_TIMEOUT_MS = 30 * 60 * 1000;
        let idleLogoutTimer;
        let lastActivitySync = Date.now();
        function registerUserActivity() {
            clearTimeout(idleLogoutTimer);
            idleLogoutTimer = setTimeout(() => { location.href = '/?logout=1'; }, SESSION_IDLE_TIMEOUT_MS);
            const now = Date.now();
            if (now - lastActivitySync >= 60000) {
                lastActivitySync = now;
                fetch('?action=presence_heartbeat&activity=1', { cache: 'no-store' }).catch(() => {});
            }
        }
        ['pointerdown', 'keydown', 'touchstart', 'wheel'].forEach(ev => {
            document.addEventListener(ev, registerUserActivity, { passive: true });
        });
        registerUserActivity();

        /* Initial Boot */
        setViewMode(viewMode);
        loadDirectory('');
        loadSysInfo();
        loadActiveSessionCount();
        setInterval(loadActiveSessionCount, 15000);
        setInterval(() => { fetch('?action=presence_heartbeat', { cache: 'no-store' }).catch(() => {}); }, 60000);
        setInterval(() => {
            const sec = document.getElementById('sys-presence');
            if (sec && sec.classList.contains('active')) loadSessionPresence();
        }, 30000);
    </script>
</body>
</html>
