<?php
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	echo json_encode(['error' => 'Method not allowed']);
	exit;
}
$html = $_POST['html'] ?? '';
if (file_put_contents('notes/note.html', $html) !== false) {
	echo json_encode(['ok' => true]);
} else {
	http_response_code(500);
	echo json_encode(['error' => 'Could not write notes/note.html — check file permissions']);
}
?>
