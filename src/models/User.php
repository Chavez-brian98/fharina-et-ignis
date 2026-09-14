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
        $query = "SELECT u.id, u.username, u.email, u.password_hash, u.status, u.role_id, r.name AS role_name
                    FROM users u
                    INNER JOIN roles r ON r.id = u.role_id
                    WHERE u.email = :email
                    LIMIT 1;";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        return $stmt->fetch();
    }
}