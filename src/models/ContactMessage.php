<?php

class ContactMessage
{
    private $conn;
    private $table = 'contact_messages';

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function create($name, $email, $phone, $message)
    {
        $query = "INSERT INTO " . $this->table . " (name, email, phone, message)
                  VALUES (:name, :email, :phone, :message);";

        $stmt = $this->conn->prepare($query);
        $cleanName = htmlspecialchars(strip_tags(trim($name)));
        $cleanEmail = filter_var(strip_tags(trim($email)), FILTER_SANITIZE_EMAIL);
        $cleanPhone = htmlspecialchars(strip_tags(trim($phone ?? ''))) ?: null;
        $cleanMessage = htmlspecialchars(strip_tags(trim($message)));

        $stmt->bindParam(':name', $cleanName);
        $stmt->bindParam(':email', $cleanEmail);
        $stmt->bindParam(':phone', $cleanPhone);
        $stmt->bindParam(':message', $cleanMessage);

        return $stmt->execute();
    }
}