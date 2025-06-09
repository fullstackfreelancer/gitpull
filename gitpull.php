<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
/*
* Script to pull a TYPO3 extension from Github to your TYPO3 installation
* Written by Simon Köhler / kohlercode.com
*
* Basic script configuration
*/

// URL of the ZIP file of a git repository
$conf['download_url'] = 'https://github.com/fullstackfreelancer/showcase/archive/master.zip';
$conf['root'] = dirname(__FILE__).'/';
// Target directory
$conf['target_dir'] = 'typo3conf/ext/';
$conf['temp_filename'] = 'showcase.zip';
$conf['extension_key'] = 'showcase';

/*
* From here better be a PHP expert before you change things!
*/

class_exists('ZipArchive') ?: die('PHP ZIP Module not loaded!');
ini_get('allow_url_fopen') ?: die('PHP function "allow_url_fopen" not enabled!');
file_exists($conf['root'].$conf['target_dir']) && is_writable($conf['root'].$conf['target_dir']) ?: die('Folder '.$conf['root'].$conf['target_dir'].' not found or not writable!');

$output = "Url: \t\t\t".$conf['download_url']."\n";
$output .= "Target: \t\t<strong>".$conf['root'].$conf['target_dir']."</strong>\n";
$output .= "Root: \t\t\t".$conf['root']."\n";
$output .= "Ext: \t\t\t".$conf['extension_key']."\n";
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
        if (is_dir($dir)) {
          $objects = scandir($dir);
          foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
              if (filetype($dir."/".$object) == "dir")
                 rmdir($dir."/".$object);
              else unlink($dir."/".$object);
            }
          }
          reset($objects);
          rmdir($dir);
        }
    }


    public function downloadFile(){
        $temp = $this->conf['root'].$this->conf['target_dir'].$this->conf['temp_filename'];
        $file = @file_get_contents($this->conf['download_url']);

        if($file){
            file_put_contents($temp, $file) ?: die("ERROR: Error writing ZIP file in target folder!\n");
            $this->messages[] = "SUCCESS: \t\t".$temp." created\n";
        }
        else{
            die('ERROR: Error downloading file from source server!');
            $this->errors[] = "ERROR: \t\tError downloading file from source server!\n";
        }
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

    public function renameDirectories(){

        $original = $this->conf['root'].$this->conf['target_dir'].$this->conf['extension_key'];
        $backup = $original.'-backup';
        $downloaded = $original.'-master';

        // If original extension extists, add slash and string to the directory
        if(file_exists($original)){
            if(rename($original,$backup)){
                $this->messages[] = "SUCCESS: \t\t".$original.' renamed to '.$backup."\n";
            }
        }

        // Rename the downloaded directory with the original name
        if(rename($downloaded,$original)){
            $this->messages[] = "SUCCESS: \t\t".$downloaded.' renamed to '.$original."\n";
        }
    }

    /*
    * This function deletes the old backup file if it exists
    */

    public function deleteBackup(){

        $file = $this->conf['root'].$this->conf['target_dir'].$this->conf['extension_key'].'-backup';

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
$action = @$_GET['a'];
$link = '';

switch ($action) {
    case 'execute':
        if(!file_exists($conf['root'].$conf['target_dir'].$conf['extension_key'].'-backup')){
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
