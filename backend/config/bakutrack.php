<?php

return [
    'internal_key' => env('BAKUTRACK_INTERNAL_KEY'),
    'scraper_base_url' => env('SCRAPER_BASE_URL', 'http://127.0.0.1:9000'),
    'scraper_python_bin' => env('SCRAPER_PYTHON_BIN', base_path('../scraper/.venv/Scripts/python.exe')),
    'scraper_workdir' => env('SCRAPER_WORKDIR', base_path('../scraper')),
    'whatsapp_provider' => env('WHATSAPP_PROVIDER', 'fonnte'),
    'whatsapp_webhook_secret' => env('WHATSAPP_WEBHOOK_SECRET'),
];
