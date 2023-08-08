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

    public  function pedido_status($id_orden_pedido){
        if($id_orden_pedido === "" || $id_orden_pedido == 0)
            return "";
        $response = $this->db->fetchObject("select estado from all_pedidos_c where rowid = $id_orden_pedido");
        return $response->estado;
    }

    public function system_access_users(){
        $response = $this->db->fetchObject("SELECT access_not FROM system_access limit 1");
        return $response->access_not;
    }

}


?>