<?php

class User
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function findByEmail($email)
    {
        $query = "SELECT e.id, e.username, e.email, e.password_hash, e.status, e.role_id, r.name AS role_name
                    FROM empleados e
                    INNER JOIN roles r ON r.id = e.role_id
                    WHERE e.email = :email
                      AND e.password_hash IS NOT NULL
                    LIMIT 1;";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        return $stmt->fetch();
    }
}