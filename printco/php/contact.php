<?php
header('Content-Type: application/json; charset=utf-8');

$name    = trim($_POST['name'] ?? '');
$phone   = trim($_POST['phone'] ?? '');
$message = trim($_POST['message'] ?? '');

$errors = [];
if ($name === '') {
    $errors[] = 'სახელის ველი სავალდებულოა';
}
if ($phone === '' || !preg_match('/^[0-9+\s\-]{5,20}$/', $phone)) {
    $errors[] = 'მიუთითეთ სწორი ტელეფონის ნომერი';
}
if ($message === '') {
    $errors[] = 'შეტყობინების ველი სავალდებულოა';
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

$entry = sprintf(
    "[%s] %s | %s | %s\n",
    date('Y-m-d H:i:s'),
    $name,
    $phone,
    str_replace("\n", ' ', $message)
);
file_put_contents(__DIR__ . '/../data/messages.log', $entry, FILE_APPEND | LOCK_EX);

echo json_encode(['success' => true]);
