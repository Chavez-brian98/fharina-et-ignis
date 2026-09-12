<?php

class Producto{
    private $conn;
    private $table = 'productos';

    public function __construct($db){
        $this->conn = $db;
    }

    public  function getAll(){
        $query = "SELECT p.id, p.nombre, p.precio, c.nombre as nombre_categoria
                    FROM " . $this->table . " AS p
                    INNER JOIN categorias AS c ON p.categoria_id = c.id
                    ORDER BY p.id DESC;";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function crear($nombre, $precio, $categoria_id){
        $query = "INSERT INTO " . $this->table . "(nombre, precio, categoria_id) VALUES (:nombre, :precio, :categoria_id);";
        $stmt = $this->conn->prepare($query);
        
        $nombre = htmlspecialchars(strip_tags($nombre));
        $precio = htmlspecialchars(strip_tags($precio));
        $categoria_id = htmlspecialchars(strip_tags($categoria_id));

        $stmt->bindparam(':nombre', $nombre);
        $stmt->bindparam(':precio', $precio);
        $stmt->bindparam(':categoria_id', $categoria_id);

        return $stmt->execute();
    }
}