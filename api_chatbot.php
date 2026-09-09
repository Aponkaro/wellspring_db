<?php
header('Content-Type: application/json');
require 'db.php';

$message = strtolower(trim($_POST['message'] ?? ''));

if (!$message) {
    echo json_encode(['reply' => 'Please type a valid message.']);
    exit;
}

try {
    $stmt = $pdo->query("SELECT question, answer FROM chatbot_training_data WHERE is_active = 1");
    $dataset = $stmt->fetchAll();

    $response = "Thank you for reaching out. A support representative will review your request shortly.";

    foreach ($dataset as $row) {
        if (strpos($message, strtolower($row['question'])) !== false) {
            $response = $row['answer'];
            break;
        }
    }
    echo json_encode(['reply' => $response]);
} catch (PDOException $e) {
    echo json_encode(['reply' => 'An error occurred while fetching support options.']);
}
?>