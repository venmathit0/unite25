<?php
header('Content-Type: application/json');

$DB_HOST = '192.168.0.100';
$DB_USER = 'unite25r_FEST';
$DB_PASS = 'xJnXVQYzrUJscRGwswvv';
$DB_NAME = 'unite25r_FEST';

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "DB connection failed", "error" => $conn->connect_error]);
    exit;
}

$data = json_decode($_POST['data'] ?? "", true);
if (!$data) {
    echo json_encode(["status" => "error", "message" => "No valid data received"]);
    exit;
}

$registration_id = $data['registration_id'];
$payment_mode = $data['payment_mode'];
$payment_total = $data['payment_total'];
$transaction_id = $data['transaction_id'] ?? "";

$invoice_blob = null;
if (isset($_FILES['invoice']) && $_FILES['invoice']['error'] === 0) {
    $invoice_blob = file_get_contents($_FILES['invoice']['tmp_name']);
}

$proof_blob = null;
if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === 0) {
    $proof_blob = file_get_contents($_FILES['payment_proof']['tmp_name']);
}

$inserted = [];
$errors = [];

foreach ($data['events'] as $event) {
    $event_name = $event['eventName'];
    $college_name = $event['college'];
    $team_id = $event['teamID'];
    $participant_names = is_array($event['participants']) ? implode(",", $event['participants']) : $event['participants'];
    $details = json_encode($event['details'] ?? []);

    $stmt = $conn->prepare("INSERT INTO registrations 
        (registration_id, event_name, college_name, team_id, participant_names, details, payment_mode, payment_total, payment_proof, transaction_id, invoice) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    if (!$stmt) {
        $errors[] = ["event_name" => $event_name, "error" => $conn->error];
        continue;
    }

    $null = NULL;
    $stmt->bind_param(
        "sssssssdbsb",
        $registration_id,
        $event_name,
        $college_name,
        $team_id,
        $participant_names,
        $details,
        $payment_mode,
        $payment_total,
        $null,
        $transaction_id,
        $null
    );

    if ($proof_blob) {
        $stmt->send_long_data(8, $proof_blob);
    }
    if ($invoice_blob) {
        $stmt->send_long_data(10, $invoice_blob);
    }

    if ($stmt->execute()) {
        $inserted[] = ["event_name" => $event_name, "id" => $stmt->insert_id];
    } else {
        $errors[] = ["event_name" => $event_name, "error" => $stmt->error];
    }
    $stmt->close();
}

$conn->close();

if (!empty($errors)) {
    echo json_encode([
        "status" => "partial",
        "message" => "Some events failed",
        "inserted" => $inserted,
        "errors" => $errors
    ]);
} else {
    echo json_encode([
        "status" => "success",
        "message" => "All registrations saved",
        "inserted" => $inserted
    ]);
}
?>
