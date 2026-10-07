<?php

class Employee
{
    private $conn;
    private $table = 'empleados';

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public static function roleLabel($role)
    {
        if ($role === null || $role === '') {
            return null;
        }

        return Role::label($role);
    }

    public function getAll()
    {
        $query = "SELECT e.id, e.name, e.last_name, e.id_document, e.email, e.role_id,
                         r.name AS role, r.is_admin AS role_is_admin,
                         e.phone, e.address, e.profile_photo, e.birth_date, e.hire_date, e.base_salary, e.status,
                         e.qr_token,
                         (e.password_hash IS NOT NULL) AS has_login
                    FROM " . $this->table . " e
                    JOIN roles r ON r.id = e.role_id
                    ORDER BY e.last_name ASC, e.name ASC;";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getById($id)
    {
        $query = "SELECT e.id, e.name, e.last_name, e.id_document, e.email, e.role_id,
                         r.name AS role, r.is_admin AS role_is_admin,
                         e.phone, e.address, e.profile_photo, e.birth_date, e.hire_date, e.base_salary, e.status,
                         e.qr_token,
                         (e.password_hash IS NOT NULL) AS has_login
                    FROM " . $this->table . " e
                    JOIN roles r ON r.id = e.role_id
                    WHERE e.id = :id
                    LIMIT 1;";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch();
    }

    public function documentExists($idDocument, $excludeId = null)
    {
        return $this->fieldExists('id_document', $idDocument, $excludeId);
    }

    public function emailExists($email, $excludeId = null)
    {
        return $this->fieldExists('email', $email, $excludeId);
    }

    private function fieldExists($field, $value, $excludeId = null)
    {
        $query = "SELECT COUNT(*) AS total FROM " . $this->table . " WHERE " . $field . " = :value";
        $params = [':value' => $value];

        if ($excludeId !== null) {
            $query .= " AND id != :id";
            $params[':id'] = $excludeId;
        }
        $query .= ";";

        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);

        $row = $stmt->fetch();
        return (int) $row['total'] > 0;
    }

    public function create($name, $last_name, $idDocument, $email, $password_hash, $roleId, $phone, $address, $birth_date, $hire_date, $baseSalary, $profilePhoto = null)
    {
        $query = "INSERT INTO " . $this->table . "(name, last_name, id_document, email, password_hash, role_id, phone, address, profile_photo, birth_date, hire_date, base_salary)
                    VALUES (:name, :last_name, :id_document, :email, :password_hash, :role_id, :phone, :address, :profile_photo, :birth_date, :hire_date, :base_salary);";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':last_name', $last_name);
        $stmt->bindParam(':id_document', $idDocument);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password_hash', $password_hash);
        $roleId = (int) $roleId;
        $stmt->bindParam(':role_id', $roleId, PDO::PARAM_INT);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':profile_photo', $profilePhoto);
        $stmt->bindParam(':birth_date', $birth_date);
        $stmt->bindParam(':hire_date', $hire_date);
        $stmt->bindParam(':base_salary', $baseSalary);

        return $stmt->execute();
    }

    public function update($id, $name, $last_name, $idDocument, $email, $password_hash, $roleId, $phone, $address, $birth_date, $hire_date, $baseSalary, $status, $profilePhoto = null)
    {
        // email y password_hash se mueven juntos: chk_empleados_login exige los
        // dos o ninguno. Si el email va vacio se elimina la cuenta de acceso
        // (hash incluido); si viene pero sin password, se conserva el hash
        // actual. Con COALESCE(:password_hash, password_hash) pelado, anular el
        // email dejaba el hash huerfano y el UPDATE reventaba el CHECK.
        $query = "UPDATE " . $this->table . "
                    SET name = :name,
                        last_name = :last_name,
                        id_document = :id_document,
                        email = NULLIF(:email, ''),
                        password_hash = CASE
                            WHEN NULLIF(:email, '') IS NULL THEN NULL
                            ELSE COALESCE(:password_hash, password_hash)
                        END,
                        role_id = :role_id,
                        phone = :phone,
                        address = :address,
                        profile_photo = :profile_photo,
                        birth_date = :birth_date,
                        hire_date = :hire_date,
                        base_salary = :base_salary,
                        status = :status
                    WHERE id = :id;";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':last_name', $last_name);
        $stmt->bindParam(':id_document', $idDocument);
        $stmt->bindValue(':email', $email === null ? null : (string) $email, $email === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':password_hash', $password_hash, $password_hash === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $roleId = (int) $roleId;
        $stmt->bindParam(':role_id', $roleId, PDO::PARAM_INT);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':profile_photo', $profilePhoto);
        $stmt->bindParam(':birth_date', $birth_date);
        $stmt->bindParam(':hire_date', $hire_date);
        $stmt->bindParam(':base_salary', $baseSalary);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function setRole($id, $roleId)
    {
        $roleId = (int) $roleId;
        $stmt = $this->conn->prepare("UPDATE " . $this->table . " SET role_id = :role_id WHERE id = :id;");
        $stmt->bindParam(':role_id', $roleId, PDO::PARAM_INT);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function toggleStatus($id)
    {
        $query = "UPDATE " . $this->table . "
                    SET status = IF(status = 'active', 'inactive', 'active')
                    WHERE id = :id;";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Resuelve un token de QR al empleado activo que lo porta. Es el camino
     * "sin cara" del quiosco: si el token existe y el empleado esta activo,
     * devuelve el registro; cualquier otra cosa devuelve null.
     */
    public function findByQrToken($token)
    {
        if (!is_string($token) || $token === '') {
            return null;
        }

        $stmt = $this->conn->prepare(
            "SELECT id, name, last_name, email, profile_photo, status
             FROM " . $this->table . "
             WHERE qr_token = ? AND status = 'active'
             LIMIT 1"
        );
        $stmt->bindValue(1, $token);
        $stmt->execute();
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }

    /**
     * Token del codigo QR de asistencia, generado bajo demanda la primera vez.
     * No es una credencial de inicio de sesion: solo identifica al empleado en
     * el reloj de marcacion.
     */
    public function qrToken($id)
    {
        $stmt = $this->conn->prepare("SELECT qr_token FROM " . $this->table . " WHERE id = ?");
        $stmt->bindValue(1, (int) $id, PDO::PARAM_INT);
        $stmt->execute();
        $token = $stmt->fetchColumn();

        if ($token !== false && $token !== null && $token !== '') {
            return $token;
        }

        return $this->regenerateQr($id);
    }

    /**
     * Emite un token nuevo e invalida el QR impreso anterior.
     */
    public function regenerateQr($id)
    {
        $nuevo = bin2hex(random_bytes(16));

        $stmt = $this->conn->prepare("UPDATE " . $this->table . " SET qr_token = ? WHERE id = ?");
        $stmt->bindValue(1, $nuevo);
        $stmt->bindValue(2, (int) $id, PDO::PARAM_INT);
        $stmt->execute();

        return $nuevo;
    }

    public function delete($id)
    {
        try {
            $stmt = $this->conn->prepare("DELETE FROM " . $this->table . " WHERE id = :id;");
            $stmt->execute([':id' => $id]);

            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }
}
