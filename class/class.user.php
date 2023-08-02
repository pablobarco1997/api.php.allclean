<?php

class User
{

    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function autenticar($user, $pass)
    {
        $response = $this->db->fetchObject("select * from all_user where users = '$user' and pass = '$pass' ");
        return $response;
    }

    public function tokenObject($token)
    {
        $response = $this->db->fetchObject("select * from all_user where concat(users, pass) = '$token' ");
        return $response;
    }

    public function validarToken($token)
    {
        $response = $this->db->Count("all_user", "where concat(users, pass) = '$token' ");
        return $response;
    }

}


?>