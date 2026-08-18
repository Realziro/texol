<?php
header('Content-Type: application/json');

$uploadDir = __DIR__ . '/uploads/jobcards/';

if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$uploadedFiles = [];

if (!empty($_FILES['files'])) {
    foreach ($_FILES['files']['tmp_name'] as $key => $tmpName) {

        $fileName = time() . '_' . basename($_FILES['files']['name'][$key]);
        $targetPath = $uploadDir . $fileName;

        if (move_uploaded_file($tmpName, $targetPath)) {
            $uploadedFiles[] = '/uploads/jobcards/' . $fileName;
        }
    }
}

echo json_encode([
    "success" => true,
    "files" => $uploadedFiles
]);
?>