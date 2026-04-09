<?php
include_once('env_vars.php');

$pi_base_url = $pi_base_url ?? '';
$admin_url   = $pi_base_url !== ''
    ? rtrim($pi_base_url, '/') . '/adminNotes.php'
    : null;

echo json_encode([
    'show_notes_qr' => (bool)($show_notes_qr ?? true),
    'show_wifi_qr'  => (bool)($show_wifi_qr  ?? false),
    'wifi_ssid'     => $wifi_ssid   ?? '',
    'wifi_password' => $wifi_password ?? '',
    'admin_url'     => $admin_url,
]);
?>
