<?php
declare(strict_types=1);

class db {
    private PDO $connection;

    public function __construct(string $db_path)
    {
        try {
            $this->connection = new PDO("sqlite:$db_path");
            $this->connection->setAttribute(PDO::ATTR_ERRMODE,
                PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            log::emergency("Fatal SQLite (file: $db_path) Error: " . $e->getMessage());
            die();
        }
    }

    public function getFileDetails(string $path, string $srcDirName): array
    {
        $sth = $this->connection->prepare("select * from files where path = :path and src_dir_name = :src_dir_name");
        $sth->execute(['path' => $path, 'src_dir_name' => $srcDirName]);
        return $sth->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addNewFile(string $path, string $srcDirName, int $size): void
    {
        $sth = $this->connection->prepare('insert into files (path, src_dir_name, size, state, created) VALUES (:path, :srcDirName, :size, \'new\', :time)');
        $sth->execute(['path' => $path, 'srcDirName' => $srcDirName, 'size' => $size, 'time' => time()]);
    }

    public function getNewFiles(): array
    {
        return $this->query("select path,src_dir_name from files where state = 'new'");
    }

    public function getFailedFiles(): array
    {
        return $this->query("select path from files where state = 'failed'", PDO::FETCH_COLUMN);
    }

    public function setFileToQueued(string $path, string $srcDirName, string $tmpPath): void
    {
        $sth = $this->connection->prepare("update files set state = 'queued',temp_path = :tmp_path where path = :path and src_dir_name = :src_dir_name");
        $sth->execute(['path' => $path, 'src_dir_name' => $srcDirName, 'tmp_path' => $tmpPath]);
    }

    public function setQueuedToCompleted(): void
    {
        $time = time();
        $this->query("update files set state = 'completed',synced = '$time' where state = 'queued'");
    }

    public function setFailedToCompleted(): void
    {
        $time = time();
        $this->query("update files set state = 'completed',synced = '$time' where state = 'failed'");
    }

    public function setQueuedToFailed(): void
    {
        $this->query("update files set state = 'failed' where state = 'queued'");
    }

    public function setFileToDeleted(string $path): void
    {
        $sth = $this->connection->prepare("update files set state = 'deleted' where temp_path = :path");
        $sth->execute(['path' => $path]);
    }

    public function setFileToSkipped(string $path, string $srcDirName): void
    {
        $sth = $this->connection->prepare("update files set state = 'skipped' where path = :path and src_dir_name = :src_dir_name");
        $sth->execute(['path' => $path, 'src_dir_name' => $srcDirName]);
    }

    public function getCompletedOlderThan(int $timestamp): array
    {
        $time = time() - $timestamp;
        return $this->query("select temp_path from files where synced < $time and state='completed'", PDO::FETCH_COLUMN);
    }

    public function getCurrentUsedSpaceOnRemote(): int
    {
        return (int)$this->query("select sum(size) from files where state='completed'", PDO::FETCH_COLUMN)[0];
    }

    private function query(string $query, int $mode = PDO::FETCH_ASSOC): array
    {
        $sth = $this->connection->prepare($query);
        $sth->execute();

        return $sth->fetchAll($mode);
    }
}