# Syncer

Features:
* One-way syncing of one or more folders via rsync
* Limits the maximum amount of space used on the destination
* Keeps files on the destination for selected amount of time before rotating it out and replacing it with newer files
* The use-case is i.e. syncing photos from the NAS/server to the Android phone with Termux (with sshd and rsync) 
which uploads files to some cloud service (i.e. Google Photos). 
* Tested on PHP 8.2 (php-sqlite3 and pecl inotify extensions required)

TODO:
* maybe create single phar archive?
* exception handling
* implement Google Photos API