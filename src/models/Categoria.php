<?php

class Categoria{
    private $conn;
    private  $table = 'categorias';

    public function __construct($db){
        $this->conn = $db;
    }

    public  function getAll(){
        $query = "SELECT * FROM " . $this->table;

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll();
    }


}