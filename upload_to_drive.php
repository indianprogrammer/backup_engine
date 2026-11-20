<?php

declare(strict_types=1);

use Google\Client;
use Google\Http\MediaFileUpload;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Google\Service\Exception as GoogleServiceException;

if ($argc !== 2) {
    fwrite(STDERR, "Usage: php upload_to_drive.php <local-file>\n");
    exit(2);
}

require __DIR__ . '/vendor/autoload.php';

$path = realpath($argv[1]);
if ($path === false || !is_file($path) || !is_readable($path)) {
    fwrite(STDERR, "Error: file does not exist or is not readable: {$argv[1]}\n");
    exit(2);
}

$fileSize = filesize($path);
if ($fileSize === false) {
    fwrite(STDERR, "Error: could not determine the file size: {$path}\n");
    exit(2);
}

$fileName = basename($path);
$timestamp = (new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata')))
    ->format('Y-m-d_H-i-s');
$driveFileName = "{$timestamp}_{$fileName}";
$folderId = getenv('GOOGLE_DRIVE_FOLDER_ID') ?: null;
$mimeType = function_exists('mime_content_type')
    ? (mime_content_type($path) ?: 'application/octet-stream')
    : 'application/octet-stream';

$metadata = new DriveFile(['name' => $driveFileName]);
if ($folderId !== null) {
    $metadata->setParents([$folderId]);
}

$client = new Client();
$client->useApplicationDefaultCredentials();
$client->addScope(Drive::DRIVE);
$drive = new Drive($client);

try {
    if ($fileSize === 0) {
        $uploadedFile = $drive->files->create($metadata, [
            'data' => '',
            'mimeType' => $mimeType,
            'uploadType' => 'multipart',
        ]);
    } else {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Could not open file for reading: {$path}");
        }

        try {
            $chunkSize = 1 * 1024 * 1024;
            $client->setDefer(true);
            $request = $drive->files->create($metadata);
            $upload = new MediaFileUpload(
                $client,
                $request,
                $mimeType,
                null,
                true,
                $chunkSize
            );
            $upload->setFileSize($fileSize);

            $status = false;
            while ($status === false && !feof($handle)) {
                $chunk = fread($handle, $chunkSize);
                if ($chunk === false || ($chunk === '' && !feof($handle))) {
                    throw new RuntimeException("Could not read file: {$path}");
                }
                if ($chunk !== '') {
                    $status = $upload->nextChunk($chunk);
                }
            }

            $client->setDefer(false);
            if ($status === false) {
                throw new RuntimeException('Upload ended before Google Drive confirmed completion.');
            }
            $uploadedFile = $status;
        } finally {
            fclose($handle);
        }
    }
} catch (GoogleServiceException $exception) {
    fwrite(STDERR, "Google Drive upload failed: {$exception->getMessage()}\n");
    exit(1);
}

printf("Uploaded: %s\n", $driveFileName);
printf("Drive file ID: %s\n", $uploadedFile->getId());
