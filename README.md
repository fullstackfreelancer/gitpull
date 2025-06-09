# gitpull.php - Pull a repository to your web server and unpack it!

I created this PHP script to quickly and easily replace TYPO3 extensions into my TYPO3 installation after committing them to Github. It can certainly be used for other applications as well.

## 🚀 Features

- Download ZIP files from public GitHub repositories
- Unzip and install into a target directory (e.g., `typo3conf/ext/`)
- Automatically rename extracted folders
- Creates backups of existing extension folders
- Deletes old backup folders on request
- Simple browser interface with safe click-to-run actions
- Logs all steps (success and error messages)

---

## 🧩 Use Case

Ideal for TYPO3 developers who want a quick way to deploy and update extensions on servers **without SSH or CLI access**. Just upload this script, visit the URL, and click a button to install/update your extension.

---

## 📁 File Structure

The script works in these steps:

1. **Downloads** a ZIP file from GitHub (e.g. `https://github.com/user/repo/archive/master.zip`)
2. **Unzips** the archive to the target directory (e.g. `typo3conf/ext/`)
3. **Backs up** the current extension folder as `business_theme-backup/`
4. **Renames** the extracted folder (e.g. `business_theme-main/`) to `business_theme/`
5. **Deletes** the old backup manually (optional)
6. **Logs** messages to the browser for visibility

---

## 🛠 Configuration

Edit the script near the top to set your own parameters:

```php
$conf['download_url'] = 'https://github.com/youruser/yourrepo/archive/main.zip';
$conf['root'] = dirname(__FILE__) . '/';
$conf['target_dir'] = 'typo3conf/ext/';
$conf['temp_filename'] = 'your_extension.zip';
$conf['folder_name'] = 'your_extension';
