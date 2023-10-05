#!/usr/bin/env php
<?php
declare(strict_types=1);

include 'vendor/autoload.php';

try {
    $config = new config('/etc/syncer/config.ini');
    $config->checkGlobal();
    $config->defaultsGlobal();
} catch (Exception $e) {
    log::emergency($e->getMessage());
    die(1);
}

try {
    log::info(sprintf("Setting log level: %s", log::lvl2text((int)$config->getValue('global', 'log_level'))));
    log::setLevel((int)$config->getValue('global', 'log_level'));
} catch (Exception $e) {
    die($e->getMessage());
}

$syncer = new syncer($config);

// initial scan
$syncer->addNewFilesToDb();
$syncer->syncNewFiles(true);
// attach inotify watches
$syncer->attachInotifyWatches();
while(true) {
    // scan new files if new inotify event occurs
    $syncer->checkInotifyAndAddNewFiles();
    // remove old files
    $syncer->removeExpiredFiles();
    // retry failed => if there are some
    if($syncer->syncFailedFiles()) {
        // copy new files to the temp dir
        $syncer->enqueueNewFiles();
        log::info("All new files enqueued -> going to sync");
        // run rsync
        $syncer->syncNewFiles();
    }
    log::info("Sleeping for " . $config->getValue('global', 'folder_check_period'));
    sleep((int)$config->getValue('global', 'folder_check_period'));
}






