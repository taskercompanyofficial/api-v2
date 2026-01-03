<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Test Job Sheet</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
        }

        h1 {
            color: #2563eb;
        }
    </style>
</head>

<body>
    <h1>TEST JOB SHEET</h1>
    <p><strong>Work Order #:</strong> TEST-001</p>
    <p><strong>Customer:</strong> John Doe</p>
    <p><strong>Date:</strong> {{ date('Y-m-d') }}</p>
    <p>This is a test PDF to verify DomPDF is working correctly.</p>
</body>

</html>
