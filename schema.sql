CREATE TABLE files
(
    id           INTEGER
        primary key autoincrement,
    path         TEXT,
    size         INTEGER,
    state        TEXT,
    created      INTEGER,
    synced       INTEGER,
    temp_path    TEXT,
    src_dir_name TEXT,
    constraint files_pk
        unique (src_dir_name, path)
);
CREATE INDEX files_synced_index
    on files (synced);