<?php
/**
 * @file
 * Backup to GoogleDrive script.
 *
 * Main script file which creates gzip files and sends them to GoogleDrive.
 */

set_time_limit(0);

include_once DIRECTORY_SEPARATOR.'tools'. DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
include_once __DIR__ . DIRECTORY_SEPARATOR . "settings.inc.php";

define('GOOGLECREDENTIALSPATH', __DIR__ . DIRECTORY_SEPARATOR . 'google-drive');
define('GOOGLEREQUESTURI', $request_uri);
define('BACKUPSTMPDIR', $fileroot);
define('STORAGELIMIT', $globals_settings['storage_limit']);
define('WEBROOT', $webroot);
define('SCOPES', implode(' ', array(
  Google_Service_Drive::DRIVE
)));

/**
 * Iterate over $sites.
 */
foreach ($sites as $site_name => $site_config) {
  if (!existsBackup($site_name, $site_config)) {
    echo ('Starting backup for ' . $site_name . '.' . PHP_EOL);

    // Generate the site archive. If database credentials were included generate
    // the database archive as well.
    archive($site_name, $site_config, $webroot);

    // Cleanup any archive files leftover in the tmp directory.
    cleanup();

    echo ('Backup complete for ' . $site_name . '.' . PHP_EOL);
  }
}

/**
 * Create codebase and database archives.
 *
 * @param array $site
 *   A site configuration array, as in example.site.inc.
 * @param string $webroot
 *   (optional) The webroot. The site docroot will be determined based on the
 *   webroot.
 *
 * @return bool
 *   Status of the operation.
 */
function archive($site_name, $site, $webroot = '') {
  if (!$site['docroot']) {
    return FALSE;
  }

  // Use the current date/time as unique identifier.
  $timestamp = date("YmdHis");
  $destination = $site_name . DIRECTORY_SEPARATOR. $timestamp;
  if (isset($site['parent_folder'])) {
    $destination = $site['parent_folder'] . DIRECTORY_SEPARATOR . $destination;
  }
  if (isset($site['docroot'])) {
    $fileroot = BACKUPSTMPDIR;
    $filepath = $fileroot . DIRECTORY_SEPARATOR.$site_name . DIRECTORY_SEPARATOR . $timestamp;
    shell_exec("mkdir -p ".$filepath);
    $site_archive = $filepath .DIRECTORY_SEPARATOR. $site_name . '_' . $timestamp . ".tar.gz.part_";

    // Create tar.gz file.
    $command = "cd " . $webroot . " && tar";
    if (isset($site['exclude_paths'])) {
      foreach ($site['exclude_paths'] as $exclusion) {
        $command .= ' --exclude ' . $exclusion;
      }
    }

    $command .= " -C " . $webroot . DIRECTORY_SEPARATOR . $site['docroot'] . " -cf " . " - . | gzip -9 | split -b ".STORAGELIMIT." - ".$site_archive;
    shell_exec($command);
    foreach (glob($fileroot.DIRECTORY_SEPARATOR.$site_name.DIRECTORY_SEPARATOR.'*') as $bkps_folders) {
      $bkp_file_idx = 0;
      foreach (glob($bkps_folders.DIRECTORY_SEPARATOR.'*') as $bkp_file) {
        send_archive_to_drive($site, $bkp_file, $destination, $bkp_file_idx);
        $bkp_file_idx += 1;
      }
    }
  }
  if (isset($site['dbname'])) {
    $db_archive = $fileroot . DIRECTORY_SEPARATOR . $site['dbname'] . '_' . $timestamp . ".sql.gz";
    $command = "mysqldump -u" . $site['dbuser'] . " -p'" . $site['dbpass'] . "'";
    if (isset($site['dbhost'])) {
      $command .= " -h " .  $site['dbhost'];
    }
    if (isset($site['dbport'])) {
      $command .= " --port " .  $site['dbport'];
    }
    $command .=  " " . $site['dbname'];

    $command .= " | gzip -9 > " . $db_archive; 
    shell_exec($command);
    send_archive_to_drive($site, $db_archive, $destination . 'database');
  }
}

/**
 * Send a single file to drive.
 *
 * @param string $file_path
 *   The path to the file to upload.
 * @param string $directory
 *   The directory the file belongs to. The name is used to assign the archive to a
 *   directory.
 * @param bool $cleanup
 *   If true, remove the file after upload.
 */
function send_archive_to_drive($site, $file_path, $directory, $gIdx = 0, $cleanup = TRUE) {
  $client = get_client($site, $gIdx);
  $service = new Google_Service_Drive($client);
  $result = uploadArchive($client, $service, $file_path, $directory);

  if ($result && $cleanup) {
    unlink($file_path);
  }
}

function uploadArchive($client, $service, $file_path, $directory) {
  $chunkSizeBytes = 1 * 1024 * 1024;

  $file = new Google_Service_Drive_DriveFile();
  $file->setName(basename($file_path));
  $file->setDescription("Reese Creative Backup file.");
  $file->setMimeType("application/gzip");
  $file->setParents(array(prepare_drive_path($service, $directory)));

  $client->setDefer(true);
  $request = $service->files->create($file);

  // Create a media file upload to represent our upload process.
  $media = new Google_Http_MediaFileUpload(
      $client,
      $request,
      'application/gzip',
      null,
      true,
      $chunkSizeBytes
  );
  $media->setFileSize(filesize($file_path));
  // Upload the various chunks. $status will be false until the process is
  // complete.
  $status = false;
  $handle = fopen($file_path, "rb");
  while (!$status && !feof($handle)) {
    // read until you get $chunkSizeBytes from TESTFILE
    // fread will never return more than 8192 bytes if the stream is read buffered and it does not represent a plain file
    // An example of a read buffered file is when reading from a URL
    $chunk = readFromBigChunk($handle, $chunkSizeBytes);
    $status = $media->nextChunk($chunk);
  }
  // The final value of $status will be the data from the API for the object
  // that has been uploaded.
  $result = false;
  if ($status != false) {
    $result = $status;
  }
  fclose($handle);

  return $result;
}

function readFromBigChunk($handle, $chunkSize)
{
    $byteCount = 0;
    $giantChunk = "";
    while (!feof($handle)) {
        // fread will never return more than 8192 bytes if the stream is read buffered and it does not represent a plain file
        $chunk = fread($handle, 8192);
        $byteCount += strlen($chunk);
        $giantChunk .= $chunk;
        if ($byteCount >= $chunkSize)
        {
            return $giantChunk;
        }
    }
    return $giantChunk;
}


/**
 * Get an authenticated google Client.
 *
 * @return \Google_Client
 *   The google client.
 */
function get_client($site, $gIdx) {
  $client = new Google_Client();

  // Get your credentials from the APIs Console.
  if (!isset($site['client_id'][$gIdx])) throw new Exception("Not enought GStorage", 1);
  
  $client->setClientId($site['client_id'][$gIdx]);
  $client->setClientSecret($site['client_secret'][$gIdx]);
  $client->setRedirectUri(GOOGLEREQUESTURI);
  $client->setAccessType("offline");
  $client->setApprovalPrompt('force');
  $client->setScopes(SCOPES);

  $credentials = GOOGLECREDENTIALSPATH.str_replace('.apps.googleusercontent.com', '', $site['client_id']).'.scrt';
  if (!file_exists($credentials) || strpos(file_get_contents($credentials), 'error_description') > 0) {
    // Exchange authorization code for access token.
    $auth_url = $client->createAuthUrl();
    printf("Open the following link in your browser:\n%s\n", $auth_url);
    shell_exec("
      if command -v open >/dev/null
      then
        open '" . $auth_url . "'
      else
        xdg-open '" . $auth_url . "'
      fi"
    );
    print 'Enter verification code at the configuration';
    if (isset($site['client_code'][$gIdx])) {
      $auth_code = $site['client_code'][$gIdx];
    } else {
      throw new Exception("Missing auth code", 1);
      
      //$auth_code = trim(fgets(STDIN));
    }
    $access_token = $client->authenticate($auth_code);

    // Save token for future use.
    if (!file_exists(dirname($credentials))) {
      mkdir(dirname($credentials));
    }

    file_put_contents($credentials, json_encode($access_token));
    printf("Credentials saved to %s\n", $credentials);
  }
  else {
    $access_token = file_get_contents($credentials);
  }

  $client->setAccessToken($access_token);

  if ($client->isAccessTokenExpired()) {
    $refresh_token = $client->getRefreshToken();
    $client->refreshToken($refresh_token);
    $new_access_token = $client->getAccessToken();
    $new_access_token['refresh_token'] = $refresh_token;
    file_put_contents($credentials, json_encode($new_access_token));
  }

  return $client;
}

/**
 * Parse path components and establish parent folder hierarchy for Google Drive.
 *
 * @param \Google_Service_Drive $service
 *   The google drive service.
 * @param string $path
 *   The path to prepare.
 *
 * @return string
 *   The id of the last folder component in the path.
 */
function prepare_drive_path($service, $path) {
  $folders = explode(DIRECTORY_SEPARATOR, $path);
  $id = NULL;
  for ($i = 0; $i < count($folders); $i++) {
    $parent = $i > 0 ? $folders[$i - 1] : NULL;
    $id = prepare_folder($service, $folders[$i], $parent);
  }

  return $id;
}

/**
 * Return the id of a folder in google drive.
 *
 * The method will create the folder if it does not exist already. If a parent
 * directory is provided, the method will attempt to create the parent first.
 *
 * @param \Google_Service_Drive $service
 *   The google drive service.
 * @param string $folder
 *   The name of the folder.
 * @param string|NULL $parent
 *   The name if the parent folder.
 *
 * @return string
 *   The file id of the google drive folder.
 */
function prepare_folder(Google_Service_Drive $service, $folder, $parent = NULL) {
  $parent_id = $parent ? prepare_folder($service, $parent) : FALSE;
  $params = array(
    'q' => "
      mimeType = 'application/vnd.google-apps.folder' and
      name = '" . $folder . "' and
      trashed = false
    ",
  );

  if ($parent_id) {
    $params['q'] = $params['q'] . " and '" . $parent_id . "' in parents";
  }

  $directories = $service->files->listFiles($params)->getFiles();
  if (!empty($directories)) {
    return $directories[0]->getId();
  }
  else {
    // Create the folder and return the id.
    $file = new Google_Service_Drive_DriveFile();
    $file->setName($folder);
    $file->setDescription("Reese Creative Backup directory.");
    $file->setMimeType("application/vnd.google-apps.folder");
    if ($parent_id) {
      $file->setParents(array($parent_id));
    }
    return $service->files->create($file, array('fields' => "id"))->id;
  }
}

/**
 * Send orphaned archives to google drive.
 *
 * This is not run by default, but could be used to backup stray files to google
 * drive before cleanup().
 */
function send_orphaned_archives_to_drive($site) {
  $client = get_client($site);
  $service = new Google_Service_Drive($client);

  if ($files = find_archives()) {
    foreach ($files as $file_path) {
      uploadArchive($client, $service, $file_path, 'unsorted');
      unlink($file_path);
    }
  };
}

/**
 * Attempt to clean up any leftover archive files.
 */
function cleanup() {
  $stray_archives = find_archives();
  foreach ($stray_archives as $stray_archive) {
    unlink($stray_archive);
  }
}

/**
 * Find archives in the BACKUPSTMPDIR directory.
 *
 * @return array
 *   The array of files matching the globbing pattern "*.gz".
 */
function find_archives() {
  return glob(BACKUPSTMPDIR . DIRECTORY_SEPARATOR . '*.gz');
}



function existsBackup($site_name, $site)
{
  $result = false;
  for ($site_drive=0; $site_drive < count($site["client_id"]); $site_drive++) { 

    $client = get_client($site, $site_drive);
    $service = new Google_Service_Drive($client);

    $optParams = array(
      'q' => '(name contains \'' . $site['dbname'] . '\' or name contains \'' . $site_name . '\')
        and mimeType contains \'folder\' and trashed = false'
    );
    $site_folder = $service->files->listFiles($optParams)->getFiles();
    if (count($site_folder) > 0) {
      if (isset($site['remove_after'])) {
        $remove_after = date('c', strtotime('-' . $site['remove_after']));
        // Print the names and IDs for up to 10 files.

        $optParams = array(
          'q' => 'modifiedTime < \'' . $remove_after . '\'
            and parents in \''.$site_folder[0]->getId().'\'
            and mimeType contains \'folder\' and trashed = false'
        );
        $files_to_remove = $service->files->listFiles($optParams);
        if (count($files_to_remove->getFiles()) > 0) {
          print "Removing Files:\n";
          foreach ($files_to_remove->getFiles() as $file) {
            printf("%s (%s)\n", $file->getName(), $file->getId());
            $service->files->delete($file->getId());
          }
        }
      }
      if (isset($site['backup_every'])) {
        $backup_every = date('c', strtotime('-' . $site['backup_every']));
        // Print the names and IDs for up to 10 files.
        $optParams = array(
          'q' => 'modifiedTime > \'' . $backup_every . '\'
            and parents in \''.$site_folder[0]->getId().'\'
            and mimeType contains \'folder\' and trashed = false'
        );

        $result = count($service->files->listFiles($optParams)->getFiles()) > 0;
      }
    }

  }
  return $result;
}
