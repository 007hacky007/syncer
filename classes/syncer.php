<?php
declare(strict_types=1);

class syncer {
    private config $config;
    private array $dirs;
    private db $db;

    private inotify $inotify;
    public function __construct(config $config)
    {
        $this->config = $config;
        $this->setDirectories($config->getValue('global', 'directory'));
        $this->db = new db($config->getValue('global', 'sqlite_db_file'));
        $this->inotify = new inotify();
    }

    private function setDirectories(array $directories): void
    {
        foreach ($directories as $dirName => $path) {
            if($path[strlen($path)-1] === '/')
                $path = substr($path, 0, -1);

            $this->dirs[$dirName] = $path;
        }
    }

    public function addNewFilesToDb(): void
    {
        foreach ($this->dirs as $dirName => $path) {
            log::debug("scanning $dirName => $path ...");
            $filesDetails = $this->scanPath($path);
            foreach ($filesDetails as $fileDetails) {
                if(!$this->db->getFileDetails($fileDetails['path'], $dirName))
                    $this->db->addNewFile($fileDetails['path'], $dirName, $fileDetails['size']);
            }
        }
    }

    public function scanPath(string $path): array
    {
        $result = [];
        $files = self::scanAllDir($path);
        clearstatcache();
        foreach ($files as $file) {
            $stat = stat($path . '/' . $file);
            $result[] = ['path' => $file, 'size' => $stat['size']];
        }

        return $result;
    }

    public function attachInotifyWatches(): void
    {
        $this->inotify->addWatchedItems($this->dirs);
    }

    public function checkInotifyAndAddNewFiles(): void
    {
        if(!($items = $this->inotify->getChangedItems()))
            return;

        log::info(sprintf("Files changed in watched directories: %s", implode(", ", $items)));
        $this->addNewFilesToDb();
    }

    public function removeExpiredFiles(): void
    {
        // check whether we have any new files, otherwise don't do anything
        if(!$this->db->getNewFiles())
            return;

        $expiredFiles = $this->db->getCompletedOlderThan((int)$this->config->getValue('global', 'keep_time_min'));
        foreach ($expiredFiles as $fileToRemoval) {
            log::info("Removing $fileToRemoval");
            unlink($fileToRemoval);
            $this->db->setFileToDeleted($fileToRemoval);
        }
    }

    /**
     * @throws Exception
     */
    public function enqueueNewFiles(): void
    {
        $newFiles = $this->db->getNewFiles();
        if(!$newFiles)
            return;

        $usedSpace = $this->db->getCurrentUsedSpaceOnRemote();
        log::info("Current used space on the destination device: $usedSpace bytes");
        foreach ($newFiles as $newFile) {
            $newFileFullPath = $this->getFullSourcePath($newFile['src_dir_name'], $newFile['path']);
            $size = stat($newFileFullPath)['size'];
            if($size > (int)$this->config->getValue('global', 'dest_size_max')) {
                log::warning("File $newFileFullPath is bigger than maximum allowed: " . $this->config->getValue('global', 'dest_size_max'));
                $this->db->setFileToSkipped($newFile['path'], $newFile['src_dir_name']);
                continue;
            }
            if(($size + $usedSpace) > (int)$this->config->getValue('global', 'dest_size_max'))
                continue;

            log::info(sprintf("Enqueueing %s | used space: %d MB | remaining: %d MB",
                $newFile['path'],
                $usedSpace/1024/1024,
                ((int)$this->config->getValue('global', 'dest_size_max')-$usedSpace)/1024/1024));
            $usedSpace = $usedSpace + $size;
            // create directory if needed
            if(!is_dir(dirname($this->config->getValue('global', 'temp_dir') . '/' . $newFile['src_dir_name'] . '/' . $newFile['path']))) {
                if(mkdir(dirname($this->config->getValue('global', 'temp_dir') . '/' . $newFile['src_dir_name'] . '/' . $newFile['path']), 0777, true) === false)
                    throw new Exception("Failed to create temp dir: " . dirname($this->config->getValue('global', 'temp_dir') . '/' . $newFile['src_dir_name'] . '/' . $newFile['path']));
            }
            if(copy($newFileFullPath, $this->config->getValue('global', 'temp_dir') . '/' . $newFile['src_dir_name'] . '/' . $newFile['path']) === false)
                throw new Exception("Failed to copy file $newFileFullPath to temp destination: " . $this->config->getValue('global', 'temp_dir') . '/' . $newFile['src_dir_name'] . '/' . $newFile['path']);

            $this->db->setFileToQueued($newFile['path'], $newFile['src_dir_name'], $this->config->getValue('global', 'temp_dir') . '/' . $newFile['src_dir_name'] . '/' . $newFile['path']);
        }
    }

    /**
     * @throws Exception
     */
    private function getFullSourcePath(string $srcDirName, string $relativePath): string
    {
        if(!isset($this->dirs[$srcDirName]))
            throw new Exception("$srcDirName is not set in the configuration!");

        return $this->dirs[$srcDirName] . '/' . $relativePath;
    }

    public function syncToDestination(): bool
    {
        $retval = -1;
        $output = '';
        $cmd = sprintf('rsync --timeout=%d -e \'ssh -p %d\' --size-only -rl --delete -h --stats %s %s@%s:%s',
            $this->config->getValue('global', 'rsync_timeout'),
            $this->config->getValue('global', 'ssh_port'),
            $this->config->getValue('global', 'temp_dir'),
            $this->config->getValue('global', 'ssh_user'),
            $this->config->getValue('global', 'ssh_host'),
            $this->config->getValue('global', 'dest_folder'),
        );
        exec($cmd, $output, $retval);
        /** @noinspection PhpParamsInspection */
        log::info(implode("\n", $output));
        if($retval === 0) {
            log::info("Sync successful");
            if(!empty($this->config->getValue('global', 'post_sync_command'))) {
                log::debug('Executing: ' . $this->config->getValue('global', 'post_sync_command'));
                $output_post_command = '';
                $retval_post_command = -1;
                exec($this->config->getValue('global', 'post_sync_command'), $output_post_command, $retval_post_command);
                log::info(implode("\n", $output_post_command));
                if($retval_post_command === 0){
                    /** @noinspection PhpParamsInspection */
                    log::info("Post-sync command execution successful");
                } else {
                    log::error("Post-sync command execution failed: $retval_post_command");
                }
            }
            return true;
        }
        log::error("Sync failed");

        return false;
    }

    public function syncNewFiles(bool $bypassSyncCheck = false): void
    {
        if(!$this->db->getQueuedAndFailedFiles() && !$bypassSyncCheck) {
            log::debug("Nothing to sync, skipping rsync");
            return;
        }

        if($this->syncToDestination()) {
            $this->db->setQueuedToCompleted();
            $this->db->setFailedToCompleted();
        } else {
            $this->db->setQueuedToFailed();
        }
    }

    public function syncFailedFiles(): bool
    {
        if(count($this->db->getFailedFiles()) === 0)
            return true;

        log::info("Retrying previously failed transfer...");
        if($this->syncToDestination()) {
            $this->db->setFailedToCompleted();
            log::info('Successfully synced previously failed transfers');

            return true;
        }
        log::warning("Attempt to sync previously failed transfer failed again");

        return false;
    }

    private static function scanAllDir(string $dir): array
    {
        $result = [];
        foreach(scandir($dir) as $filename) {
            if ($filename[0] === '.') continue;
            if (str_ends_with($filename, '.part')) continue; // ignore partial files '21-01-09 17-10-12 play.mp4.ocTransferId1209501074.part'
            $filePath = $dir . '/' . $filename;
            if (is_dir($filePath)) {
                foreach (self::scanAllDir($filePath) as $childFilename) {
                    $result[] = $filename . '/' . $childFilename;
                }
            } else {
                $result[] = $filename;
            }
        }

        return $result;
    }
}