<?php
declare(strict_types=1);

class config {
    private array $config;

    /**
     * @throws Exception
     */
    public function __construct(string $file = 'config.ini')
    {
        if (!$this->config = parse_ini_file($file, TRUE)) {
            throw new Exception("Could not parse config file $file");
        }
    }


    /**
     * @throws Exception
     */
    public function checkGlobal(): void
    {
        $check = ["ssh_user", "ssh_host", "dest_folder", "ssh_port", "sqlite_db_file", "folder_check_period", "dest_size_max", "keep_time_min", "temp_dir"];
        $this->check('global', $check);
    }


    public function defaultsGlobal(): void
    {
        $defaults = [
            'log_level' => 6,
            'rsync_timeout' => 300,
        ];
        $this->setDefaults('global', $defaults);
    }

    /**
     * @throws Exception
     */
    private function check(string $section, array $fields): void
    {
        foreach ($fields as $field) {
            if (!isset($this->config[$section][$field])) {
                throw new Exception("Config test failed: $section => $field is not defined in the config file");
            }
        }
    }

    private function setDefaults(string $section, array $defaults): void
    {
        foreach ($defaults as $key => $value) {
            if(!isset($this->config[$section][$key]))
                $this->config[$section][$key] = $value;
        }
    }

    public function getValue(string $section, $field)
    {
        return $this->config[$section][$field] ?? null;
    }
}