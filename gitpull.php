<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
/*
* Script to pull a repository from Github to your web server
* Written by Simon Köhler / kohlercode.com
*
* Basic script configuration
*/

// URL of the ZIP file of a git repository
$conf['download_url'] = 'https://github.com/fullstackfreelancer/business_theme/archive/master.zip';
$conf['root'] = dirname(__FILE__).'/';

// Target directory
$conf['target_dir'] = 'typo3conf/ext/';

// Temporary file name
$conf['temp_filename'] = 'business_theme.zip';

// Target folder name
$conf['folder_name'] = 'business_theme';

/*
* ----------------------------------------------------------
* From here better be a PHP expert before you change things!
* ----------------------------------------------------------
*/

$errors = [];

// Check if ZIP extension is available
if (!class_exists('ZipArchive')) {
    $errors[] = 'ERROR: PHP ZIP module not loaded!';
}

// Check if allow_url_fopen is enabled (may still be useful depending on other code)
if (!ini_get('allow_url_fopen')) {
    $errors[] = 'ERROR: PHP setting "allow_url_fopen" is not enabled!';
}

// Check if target directory exists and is writable
$targetPath = $conf['root'] . $conf['target_dir'];
if (!file_exists($targetPath) || !is_writable($targetPath)) {
    $errors[] = "ERROR: Folder $targetPath not found or not writable!";
}

// If any errors occurred, display them and exit safely
if (!empty($errors)) {
    foreach ($errors as $err) {
        echo $err . "<br>\n";
    }
    exit(1); // Graceful exit with error code
}

$output = "Url: \t\t\t".$conf['download_url']."\n";
$output .= "Target: \t\t<strong>".$conf['root'].$conf['target_dir']."</strong>\n";
$output .= "Root: \t\t\t".$conf['root']."\n";
$output .= "Ext: \t\t\t".$conf['folder_name']."\n";
$output .= "\n----------------\n\n";

class GitPull {

    private $conf = [];
    private $messages = [];
    private $errors = [];

    public function __construct($conf){
        $this->conf = $conf;
    }

    // Recursive deletion of directory
    private function rrmdir($dir) {
        if (!is_dir($dir)) return;

        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object === '.' || $object === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $object;
            if (is_dir($path)) {
                $this->rrmdir($path); // recurse
            } else {
                unlink($path); // delete file
            }
        }
        rmdir($dir); // now empty, can remove
    }

    public function downloadFile() {
        $temp = $this->conf['root'] . $this->conf['target_dir'] . $this->conf['temp_filename'];

        $ch = curl_init($this->conf['download_url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // follow redirects (e.g., GitHub might redirect)
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);   // connection timeout
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);          // response timeout
        curl_setopt($ch, CURLOPT_USERAGENT, 'GitPullScript/1.0'); // required by GitHub sometimes

        $file = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200 || $file === false) {
            $this->errors[] = "ERROR: Download failed with HTTP status $httpCode. cURL error: $curlError";
            return false;
        }

        if (file_put_contents($temp, $file) === false) {
            $this->errors[] = "ERROR: Failed to write ZIP file to target path: $temp";
            return false;
        }

        $this->messages[] = "SUCCESS: \t\t$temp created via cURL";
        return true;
    }

    public function unzipFile(){

        if(count($this->errors) > 0){
            $this->errors[] = "INFO: \t\tFile could not be unzipped!\n";
        }
        else{
            $temp = $this->conf['root'].$this->conf['target_dir'].$this->conf['temp_filename'];
            $target = $this->conf['root'].$this->conf['target_dir'];

            $zip = new ZipArchive;
            $res = $zip->open($temp);

            if ($res === TRUE) {
                $zip->extractTo($target);
                $zip->close();
                $this->messages[] = "SUCCESS: \t\t".$temp." extracted to ".$target."\n";
            }
            else {
                $this->errors[] = "WARNING: \t\t".$temp." could not be extracted!\n";
            }
        }

    }

    public function removeTempFile(){
        $temp = $this->conf['root'].$this->conf['target_dir'].$this->conf['temp_filename'];
        if(file_exists($temp) && unlink($temp)){
            $this->messages[] = "SUCCESS: \t\t".$temp." removed!\n";
        }
        else{
            $this->errors[] = "WARNING: \t\t".$temp." could not be removed! Please check this manually!\n";
        }
    }


    /*
    * This function renames the directories after downloading in the following order:
    * 1. if exists, rename original extension directory into "(extkey)-backup"
    * 2. rename the downloaded directory with the name "(extkey)-master" into "(extkey)"
    */

    public function renameDirectories() {
        $targetDir = $this->conf['root'] . $this->conf['target_dir'];
        $original = $targetDir . $this->conf['folder_name'];
        $backup = $original . '-backup';

        // Detect the extracted folder (e.g., -master, -main, etc.)
        $extractedFolder = '';
        $pattern = $targetDir . $this->conf['folder_name'] . '-*';
        foreach (glob($pattern, GLOB_ONLYDIR) as $folder) {
            $extractedFolder = $folder;
            break;
        }

        if (!$extractedFolder || !is_dir($extractedFolder)) {
            $this->errors[] = "ERROR: \t\tExtracted folder not found! Expected something like {$pattern}\n";
            return;
        }

        // Rename original to backup
        if (file_exists($original)) {
            if (file_exists($backup)) {
                $this->rrmdir($backup); // Clean previous backup
            }
            if (rename($original, $backup)) {
                $this->messages[] = "SUCCESS: \t\t$original renamed to $backup\n";
            } else {
                $this->errors[] = "ERROR: \t\tCould not rename $original to $backup\n";
                return;
            }
        }

        // Rename the extracted to original
        if (rename($extractedFolder, $original)) {
            $this->messages[] = "SUCCESS: \t\t$extractedFolder renamed to $original\n";
        } else {
            $this->errors[] = "ERROR: \t\tCould not rename $extractedFolder to $original\n";
        }
    }

    /*
    * This function deletes the old backup file if it exists
    */

    public function deleteBackup(){

        $file = $this->conf['root'].$this->conf['target_dir'].$this->conf['folder_name'].'-backup';

        if(file_exists($file)){
            $this->rrmdir($file);
            if(file_exists($file)){
                $this->errors[] = "ERROR: \t\t".$file.' could not be removed!<br>';
            }
            $this->messages[] = "SUCCESS: \t\t".$file.' removed!<br>';
        }
        else{
            $this->messages[] = "INFO: \t\t\t".$file.' does not exist anymore!<br>';
        }

    }

    public function getOutput(){
        $output = '';
        foreach ($this->errors as $key => $value) {
            $output .= $value."\n";
        }
        foreach ($this->messages as $key => $value) {
            $output .= $value."\n";
        }
        return $output;
    }

}

$gitpull = new GitPull($conf);
$action = $_GET['a'] ?? '';
$action = preg_match('/^[a-z]+$/', $action) ? $action : '';
$link = '';

switch ($action) {
    case 'execute':
        if(!file_exists($conf['root'].$conf['target_dir'].$conf['folder_name'].'-backup')){
            $gitpull->downloadFile();
            $gitpull->unzipFile();
            $gitpull->removeTempFile();
            $gitpull->renameDirectories();
            $link .= "<a href=\"?a=clean\" style=\"color:#eee;\">DELETE BACKUP FILES?</a>\n";
        }
        else{
            $link .= "The backup file still exists. Please delete it:\n";
            $link .= "<a href=\"?a=clean\" style=\"color:#eee;\">DELETE OLD BACKUP</a>\n";
        }
    break;

    case 'clean':
        $gitpull->deleteBackup();
        $link .= "<a href=\"?a=\" style=\"color:#eee;\">BACK TO THE START</a>\n";
    break;

    default:
        $link .= '<a href="?a=execute" style="color:#eee;">EXECUTE SCRIPT</a>'."\n";
    break;
}

?>

<!DOCTYPE html>
<html lang="en" dir="ltr">
    <head>
        <meta charset="utf-8">
        <title>TYPO3 GIT Download Script</title>
    </head>
    <body style="background:#2b2b2b;color:steelblue;">
        <pre>Welcome to GITPull v2.0.0<br><br><?php echo $gitpull->getOutput().$link; ?></pre>
    </body>
</html>
