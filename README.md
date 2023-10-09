# Syncer

## Features:
* One-way syncing of one or more folders via rsync
* Limits the maximum amount of space used on the destination
* Keeps files on the destination for selected amount of time before rotating it out and replacing it with newer files
* The use-case is i.e. syncing photos from the NAS/server to the Android phone with Termux (with sshd and rsync) 
which uploads files to some cloud service (i.e. Google Photos). 
* Tested on PHP 8.2 (php-sqlite3 and pecl inotify extensions required)

## Example usage:
1. G Pixel phone with G photos sync enabled
   1. Termux from F-Droid
   2. Termux:API from F-Droid
   3. (optional but recommended) Termux:Boot from F-Droid - to start sshd automatically
   4. setup sshd, install rsync, setup Termux API (`pkg install termux-api`), setup storage mount `termux-setup-storage`
2. Server/NAS with Nextcloud connected through VPN (i.e. Wireguard) 
to the network, where you keep your G Pixel phone (i.e. home network) 
3. Nextcloud client on your phone with photo sync enabled
4. Syncer (this app) running on the server/NAS with proper configuration
   1. don't forget to add your server's/NAS public ssh key to the `~/.ssh/authorized_keys` on your G Pixel Termux
   2. set `/etc/syncer/config.ini` on your server/NAS with proper values
   3. your `post_sync_command` should probably contain `ssh -p 8022 user@<your G Pixel's VPN IP> termux-media-scan -r /mnt/sdcard/DCIM`
   4. your `directory[name]` should contain path where your Nextcloud keeps 
   photos synced from your phone
   5. set `dest_size_max` to the amount of free storage on your G Pixel device (in bytes) 
   `15000000000` is recommended value for 32GB variant 
   6. set `keep_time_min` it's the minimum time (in seconds) to keep photos on the G Pixel device 
   to allow them to be synced completely to G photos. Change this according to your needs. 
   If your upload speed is slow, you probably want to increase this value.
   7. set `temp_dir` path so it is at the same mountpoint as (photos) source directory 

 ```
          |                                                                      |
+--------+                                                             +--------+
| source |                                                             | G Pixel|
| phone  |                                                             |(Termux)|
+--------+                       +--------------------+                +--------+  
| 1 2 3  | ---Nextcloud sync-->  | Server/NAS         |   ---syncer--> | 1 2 3  |
| 4 5 6  |                       | running Nextcloud  |                | 4 5 6  |  
| 7 8 9  |                       +--------------------+                | 7 8 9  | 
| * 0 #  |                                                             | * 0 #  |
+--------+                                                             +--------+
                                                                           |
                                                                           |
                                                                           v
                                                                      .-~~~-.
                                                              .- ~ ~-(       )_ _
                                                             /                     ~ -.
                                                            |      G Photos             \
                                                             \                         .'
                                                               ~- . _____________ . -~
 ```  

## TODO:
* maybe create single phar archive?
* exception handling
* ~~implement Google Photos API~~ - it can't be used as it does not 
provide way to filter or sort by uploaded date or total count of objects.