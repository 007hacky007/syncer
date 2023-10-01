create table main.files
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

create index main.files_synced_index
    on main.files (synced);

