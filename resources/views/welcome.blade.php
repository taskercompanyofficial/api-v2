<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome | Tasker Company API</title>
    <!-- Use CDN for Tailwind Play as fallback if .css not processed -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        /* Fallback in case Tailwind classes are not working */
        body {
            background: #f9fafb;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        header,
        footer {
            background: #fff;
            box-shadow: 0 1px 2px rgba(16, 22, 26, 0.04);
        }

        .container {
            width: 100%;
            max-width: 1000px;
            margin: 0 auto;
            padding: 1.5rem;
        }

        .api-title {
            font-size: 2rem;
            font-weight: bold;
            color: #4f46e5;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .api-link,
        .api-link-active {
            color: #4b5563;
            text-decoration: none;
            margin: 0 1rem;
            padding: 0.25rem 0;
            transition: color .2s;
        }

        .api-link:hover,
        .api-link-active {
            color: #4f46e5;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 500;
            text-decoration: none;
        }

        .btn-primary {
            background: #4f46e5;
            color: #fff;
            box-shadow: 0 2px 8px rgba(80, 61, 205, 0.1);
        }

        .btn-primary:hover {
            background: #4338ca;
        }

        .btn-outline {
            border: 1px solid #4f46e5;
            color: #4f46e5;
            background: #fff;
        }

        .btn-outline:hover {
            background: #eef2ff;
        }

        .version-badge {
            display: inline-block;
            padding: 0.5rem 1.25rem;
            background: #e0e7ff;
            color: #3730a3;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: .09em;
            text-transform: uppercase;
            margin-top: 2rem;
        }

        .main-center {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .footer-muted {
            color: #6b7280;
            text-align: center;
            font-size: 1rem;
        }
    </style>
</head>

<body>
    <header>
        <div class="container" style="display: flex; justify-content: space-between; align-items: center;">
            <span class="api-title">
                <svg width="35" height="35" fill="none" stroke="currentColor" stroke-width="2"
                    viewBox="0 0 24 24" style="color:#4f46e5;">
                    <rect x="3" y="7" width="18" height="10" rx="2" stroke="currentColor"></rect>
                    <path d="M7 7V5a2 2 0 012-2h6a2 2 0 012 2v2" stroke="currentColor"></path>
                </svg>
                Tasker Company API
            </span>
            <nav>
                <a href="#" class="api-link-active">API Home</a>
                <a href="https://tasker.company/docs" target="_blank" class="api-link">Documentation</a>
                <a href="mailto:support@tasker.company" class="api-link">Contact Support</a>
                <a href="https://tasker.company" target="_blank" class="btn btn-primary"
                    style="margin-left:1.5rem;">Company Site</a>
            </nav>
        </div>
    </header>
    <main class="main-center">
        <div class="text-center" style="max-width: 35rem; margin:auto; padding: 4rem 0;">
            <h1 style="font-size:2.5rem; font-weight:bold; color:#111827; margin-bottom:1.2rem;">
                Welcome to the <span style="color:#4f46e5;">Tasker Company API</span>
            </h1>
            <p style="color:#4b5563; font-size:1.125rem; margin-bottom:2rem;">
                Powering seamless integrations and developer productivity.<br>
                Explore the API documentation and get started building with Tasker Company.
            </p>
            <div style="display:flex; justify-content:center; gap:1rem; margin-bottom:1.5rem;">
                <a href="https://tasker.company/docs" target="_blank" class="btn btn-primary">
                    API Documentation
                </a>
                <a href="mailto:support@tasker.company" class="btn btn-outline">
                    Contact Support
                </a>
            </div>
            <div>
                <span class="version-badge">
                    Version 1.2.0
                </span>
            </div>
        </div>
    </main>
    <footer style="background:#fff;border-top: 1px solid #e5e7eb; padding:1.5rem 0;">
        <div class="footer-muted">
            &copy; {{ date('Y') }} Tasker Company &mdash; API for ambitious teams.
        </div>
    </footer>
</body>

</html>
