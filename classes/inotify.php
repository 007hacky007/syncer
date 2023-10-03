<?php
declare(strict_types=1);

class inotify
{
    private array $wd_constants = [
        IN_ACCESS => array('IN_ACCESS', 'File was accessed (read)'),
        IN_MODIFY => array('IN_MODIFY', 'File was modified'),
        IN_ATTRIB => array('IN_ATTRIB', 'Metadata changed (e.g. permissions, mtime, etc.)'),
        IN_CLOSE_WRITE => array('IN_CLOSE_WRITE', 'File opened for writing was closed'),
        IN_CLOSE_NOWRITE => array('IN_CLOSE_NOWRITE', 'File not opened for writing was closed'),
        IN_OPEN => array('IN_OPEN', 'File was opened'),
        IN_MOVED_TO => array('IN_MOVED_TO', 'File moved into watched directory'),
        IN_MOVED_FROM => array('IN_MOVED_FROM', 'File moved out of watched directory'),
        IN_CREATE => array('IN_CREATE', 'File or directory created in watched directory'),
        IN_DELETE => array('IN_DELETE', 'File or directory deleted in watched directory'),
        IN_DELETE_SELF => array('IN_DELETE_SELF', 'Watched file or directory was deleted'),
        IN_MOVE_SELF => array('IN_MOVE_SELF', 'Watch file or directory was moved'),
        IN_CLOSE => array('IN_CLOSE', 'Equals to IN_CLOSE_WRITE | IN_CLOSE_NOWRITE'),
        IN_MOVE => array('IN_MOVE', 'Equals to IN_MOVED_FROM | IN_MOVED_TO'),
        IN_ALL_EVENTS => array('IN_ALL_EVENTS', 'Bitmask of all the above constants'),
        IN_UNMOUNT => array('IN_UNMOUNT', 'File system containing watched object was unmounted'),
        IN_Q_OVERFLOW => array('IN_Q_OVERFLOW', 'Event queue overflowed (wd is -1 for this event)'),
        IN_IGNORED => array('IN_IGNORED', 'Watch was removed (explicitly by inotify_rm_watch() or because file was removed or filesystem unmounted'),
        IN_ISDIR => array('IN_ISDIR', 'Subject of this event is a directory'),
        1073741840 => array('IN_CLOSE_NOWRITE', 'High-bit: File not opened for writing was closed'),
        1073741856 => array('IN_OPEN', 'High-bit: File was opened'),
        1073742080 => array('IN_CREATE', 'High-bit: File or directory created in watched directory'),
        1073742336 => array('IN_DELETE', 'High-bit: File or directory deleted in watched directory'),
        IN_ONLYDIR => array('IN_ONLYDIR', 'Only watch pathname if it is a directory (Since Linux 2.6.15)'),
        IN_DONT_FOLLOW => array('IN_DONT_FOLLOW', 'Do not dereference pathname if it is a symlink (Since Linux 2.6.15)'),
        IN_MASK_ADD => array('IN_MASK_ADD', 'Add events to watch mask for this pathname if it already exists (instead of replacing mask).'),
        IN_ONESHOT => array('IN_ONESHOT', 'Monitor pathname for one event, then remove from watch list.')
    ];

    /**
     * @var resource
     */
    private $inotifyFd;
    private array $watchedItemsWd = [];
    private array $watchedItemsPathsToNames = [];
    private array $foldersToWatch = [];

    /**
     * @throws Exception
     */
    public function __construct()
    {
        if (!function_exists('inotify_init'))
            throw new Exception("php-inotify extension is missing (not loaded)");
        if (($this->inotifyFd = inotify_init()) === false)
            throw new Exception("Could not create inotify resource");
        stream_set_blocking($this->inotifyFd, false);
    }

    public function __destruct()
    {
        foreach ($this->watchedItemsWd as $wd => $folder)
            inotify_rm_watch($this->inotifyFd, $wd);

        fclose($this->inotifyFd);
    }

    public function addWatchedItems(array $items): void
    {
        $this->foldersToWatch = $items;
        foreach ($items as $folderName => $folder) {
            $folders = self::recursiveGetDirectories($folder);
            foreach ($folders as $folderRecursive) {
                $this->addWatchedDirectory($folderRecursive, $folderName);
            }
        }
    }

    public function getChangedItems(): array
    {
        $changedItems = [];
        $newDirs = [];
        $events = inotify_read($this->inotifyFd);
        if (!$events)
            return [];

        $eventNum = 0;
        foreach ($events as $event) {
            $eventNum++;
            log::debug(sprintf("inotify Event #%d - Object: %s: %s (%s)", $eventNum, $event['name'], $this->wd_constants[$event['mask']][0], $this->wd_constants[$event['mask']][1]));
            $changedItems[$this->watchedItemsPathsToNames[$this->watchedItemsWd[$event['wd']]]] = $this->watchedItemsWd[$event['wd']];
            if(self::maskContains($event['mask'], IN_CREATE) && self::maskContains($event['mask'], IN_ISDIR)){
                $newDirs[$this->watchedItemsPathsToNames[$this->watchedItemsWd[$event['wd']]]] = true;
            }

            if(self::maskContains($event['mask'], IN_DELETE_SELF)) {
                log::debug("Removing watched directory: " . $this->watchedItemsWd[$event['wd']]);
                unset($this->watchedItemsPathsToNames[$this->watchedItemsWd[$event['wd']]], $this->watchedItemsWd[$event['wd']]);
            }
        }

        foreach($newDirs as $dirName => $val) {
            $missingDirs = array_diff(self::recursiveGetDirectories($this->foldersToWatch[$dirName]), $this->watchedItemsWd);
            foreach ($missingDirs as $missingDir)
                $this->addWatchedDirectory($missingDir, $dirName);
        }


        return $changedItems;
    }

    private static function recursiveGetDirectories(string $directory): array
    {
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
            RecursiveIteratorIterator::CATCH_GET_CHILD // Ignore "Permission denied"
        );

        $paths = array($directory);
        foreach ($iter as $path => $dir) {
            if ($dir->isDir()) {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    private static function maskContains(int $mask, int $flag): bool
    {
        return ($mask & $flag) === $flag;
    }

    public function addWatchedDirectory(string $path, int|string $folderName): void
    {
        $watchDescriptor = inotify_add_watch($this->inotifyFd, $path, IN_CREATE | IN_DELETE | IN_MODIFY | IN_MOVED_TO | IN_MOVED_FROM | IN_DELETE_SELF);
        log::info("Monitoring directory: $path");
        $this->watchedItemsWd[$watchDescriptor] = $path;
        $this->watchedItemsPathsToNames[$path] = $folderName;
    }
}