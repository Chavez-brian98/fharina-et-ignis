<?php

class Setting
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function getAll()
    {
        $query = "SELECT setting_key, setting_value FROM settings ORDER BY id ASC;";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        $result = [];
        while ($row = $stmt->fetch()) {
            $result[$row['setting_key']] = $row['setting_value'];
        }

        return $result;
    }

    public function get($key, $default = null)
    {
        $settings = $this->getAll();

        return isset($settings[$key]) ? $settings[$key] : $default;
    }

    public function update($key, $value)
    {
        $query = "INSERT INTO settings (setting_key, setting_value) VALUES (:setting_key, :setting_value)
                  ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':setting_key', $key);
        $stmt->bindParam(':setting_value', $value);

        return $stmt->execute();
    }
}