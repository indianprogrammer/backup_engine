# Upload a local file to Google Drive

`upload_to_drive.php` uploads one local file using the Google account authenticated
on the command line. The uploaded filename is prefixed with the current time in
`Asia/Kolkata`, for example `2026-11-20_19-37-51_backup.tar.gz`.

## Install

Install PHP 8.1+ and Composer, then in this directory run:

```sh
composer install
```

Install the Google Cloud CLI (`gcloud`) on the server. In a Google Cloud project
where the Drive API is enabled, authenticate without a browser on the server:

```sh
gcloud auth application-default login --no-browser --scopes=https://www.googleapis.com/auth/drive
```

Follow the URL/code instructions printed by `gcloud` using a browser on another
device. The resulting Application Default Credentials are stored for the server
user who ran the command. Do not copy or publish those credentials; anyone who
gets them can act as that Google account.

If needed, enable the Drive API in the selected project:

```sh
gcloud services enable drive.googleapis.com --project=YOUR_PROJECT_ID
```

## Upload

```sh
php upload_to_drive.php /path/to/local-file
```

By default, the file is uploaded to the authenticated account's My Drive root.
To upload into a specific folder, set its Drive folder ID:

```sh
GOOGLE_DRIVE_FOLDER_ID=your_folder_id php upload_to_drive.php /path/to/local-file
```

The authenticated account must have permission to add files to that folder.
Uploads use resumable chunks, so large files do not need to be loaded fully into
memory.
