<?php
session_start();

// Rutas y constantes
define('DATA_FILE', __DIR__ . '/products.json');
define('SETTINGS_FILE', __DIR__ . '/settings.json');
define('WHATSAPP_NUMBER', '5493534227418');

// Inicializa archivos si no existen
if (!file_exists(DATA_FILE)) {
    file_put_contents(DATA_FILE, json_encode([], JSON_PRETTY_PRINT));
}
if (!file_exists(SETTINGS_FILE)) {
    file_put_contents(SETTINGS_FILE, json_encode(['shipping_fee' => 0], JSON_PRETTY_PRINT));
}

// Productos
function load_products(): array {
    $data = json_decode(file_get_contents(DATA_FILE), true);
    return is_array($data) ? $data : [];
}
function save_products(array $products): void {
    file_put_contents(DATA_FILE, json_encode($products, JSON_PRETTY_PRINT));
}

// Configuraciones
function load_settings(): array {
    $data = json_decode(file_get_contents(SETTINGS_FILE), true);
    return is_array($data) ? $data : ['shipping_fee' => 0];
}
function save_settings(array $settings): void {
    file_put_contents(SETTINGS_FILE, json_encode($settings, JSON_PRETTY_PRINT));
}
