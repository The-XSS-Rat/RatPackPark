<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RatPack Park</title>
    <style>
        body {
            background: linear-gradient(to right, #fbc2eb, #a6c1ee);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
        }
        .global-banner {
            background: rgba(74, 20, 140, 0.9);
            color: #fff;
            padding: 8px 16px;
            text-align: right;
        }
        .global-banner a {
            color: #ffeb3b;
            font-weight: 600;
            text-decoration: none;
        }
        .global-banner a:hover {
            text-decoration: underline;
        }
        .form-container {
            background-color: #ffffffdd;
            padding: 2em;
            border-radius: 15px;
            box-shadow: 0 0 15px rgba(0,0,0,0.2);
            width: 300px;
            margin: 5% auto;
            text-align: center;
        }
        input, select {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        button {
            background-color: #6a1b9a;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
        }
        .message { color: green; font-weight: bold; }
        .error { color: red; }
    </style>
</head>
<body>
<div class="global-banner">
    <a href="https://www.youtube.com/playlist?list=PLd92v1QxPOprxnqslA9ho9egWvs4_3gDQ" target="_blank" rel="noopener noreferrer">Solutions</a>
</div>
<!-- 'your-secret-key' should be replaced with your actual JWT secret key -->
