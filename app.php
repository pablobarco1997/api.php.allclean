<?php

define("SERVER_NAME", "localhost");

require_once "class/class.user.php";
require_once "class/class.connection.php";
require_once "class/class.send.response.php";

header('Content-Type: application/json');

$requestData = $_POST;

if (!isset($requestData['accion'])) {
    $response = new Response();
    $response->errorAlert = "No se proporcionó la acción requerida.";
    $response->send();
}


if ($requestData["accion"] != "autentication") {
    $db = new db(SERVER_NAME);
    $User = new User($db);
    if (!$User->validarToken($requestData["token"])) {
        $response->errorAlert = "Token Invalido cierre session y vuelva a iniciar";
        $response->send();
    }
}


$accion = $requestData["accion"];

switch ($accion) {
    case "autentication":
        $response = new Response();
        $usuario = !isset($requestData["user"]) ? "" : $requestData["user"];
        $pass = !isset($requestData["pass"]) ? "" : $requestData["pass"];
        $db = new db(SERVER_NAME);
        $User = new User($db);
        $au = $User->autenticar($usuario, $pass);
        if ($au == false)
            $response->errorAlert = "autenticacion invalida compruebe el usuario o contraseña";
        else {
            $response->success = "ok";
            $response->data = (array)$au;
        }
        $response->send();
        break;

    case "ProductsList":
        $response = new Response();
        $db = new db(SERVER_NAME);
        $User = new User($db);
        $start = !isset($requestData["start"]) ? 0 : $requestData["start"];
        $length = 10;
        $limit = "limit $start, $length";
        if (isset($_POST["end_limit"]) && $_POST["end_limit"])
            $limit = "";
        $where = " where ";
        if (isset($_POST["where"])) {
            $where .= "1=1";
            if (isset($_POST["productos"])) { //obtengo el id de los productos
                $where .= " and rowid in(" . implode(",", $_POST["productos"]) . ")";
            }
        } else
            $where = "";
        $arr = $db->fetchArray("SELECT *, '' as observacion FROM all_products " . $where . " order by rowid desc " . $limit);
        $response->data = $arr;
        $response->send();
        break;

    case "createProducts":
        $response = new Response();
        $db = new db(SERVER_NAME);
        $User = new User($db);
        $numbre = $requestData["nombre"];
        $cantidad = $requestData["cantidad"];
        $idproducts = $requestData["idproducts"];

        $count = $db->Count("all_products", "where rowid = $idproducts");
        if ($count) {
            //update
            $value = $db->tableUpdateRow("all_products", array(
                array("nombre", $numbre),
                array("cantidad", $cantidad)
            ), $idproducts);
            if ($value) {
                $response->success = "ok";
            } else {
                $response->errorAlert = "Ocurrio un error con la operación";
            }
        } else {
            //create
            $value = $db->tableInsertRow("all_products", array(
                array("nombre", $numbre),
                array("cantidad", $cantidad)
            ));
            if ($value) {
                $response->success = "ok";
            } else {
                $response->errorAlert = "Ocurrio un error con la operación";
            }
        }

        $response->send();
        break;


    case "crearPedido":
        $response = new Response();
        $db = new db(SERVER_NAME);
        $User = new User($db);
        $token = $requestData["token"];
        $idUser = $User->tokenObject($token)->rowid;
        $descripcion = $requestData["descripcion"];
        //print_r($idUser); die();
        if ($idUser) {
            $idOrden = $requestData["idorden"];
            $situacion = $requestData["situacion"];

            if ((int)$idOrden > 0) { //actualizar pedido
                $pedidos_c = $db->tableUpdateRow("all_pedidos_c", array(
                    array("descripcion", $descripcion),
                    array("estado", $situacion),
                    array("id_users", $idUser)
                ), $idOrden);
                if ($idOrden) {
                    $dele = $db->query("DELETE  from all_pedidos_d where rowid > 0 and fk_pc = $idOrden");
                    if ($dele) {
                        $productos = $requestData["productos"];
                       // print_r($productos); die();
                        $columns = [];
                        foreach ($productos as $key => $value) {
                            $columns[] = array("observacion" => $value["observacion"], "fk_pc" => $idOrden, "fk_producto" => (int)$value["rowid"], "cantidad" => (double)$value["cantidad"]);
                        }
                        $pedidos_d = $db->tableInsertRowsMasive("all_pedidos_d", $columns);
                        if (!$pedidos_d) { //ok creacion detalle
                            $response->errorAlert = "Ocurrio un error con la operación actualización pedido detalle";
                            //$db->query("DELETE FROM `all_pedidos_c` WHERE (`rowid` = '" . $idOrden . "');");
                        }
                    }
                }

            } else { //crear nuevo pedido
                $pedidos_c = $db->tableInsertRow("all_pedidos_c", array(
                    array("descripcion", $descripcion),
                    array("estado", "P"),
                    array("id_users", $idUser),
                ));
                if ($pedidos_c) { //ok cabezera returna el ultimo id
                    $productos = $requestData["productos"];
                    $last_id = $pedidos_c;
                    $columns = [];
                    foreach ($productos as $key => $value) {
                        $columns[] = array("observacion" => $value["observacion"], "fk_pc" => $last_id, "fk_producto" => (int)$value["rowid"], "cantidad" => (double)$value["cantidad"]);
                    }
                    $pedidos_d = $db->tableInsertRowsMasive("all_pedidos_d", $columns);
                    if (!$pedidos_d) { //ok creacion detalle
                        $response->errorAlert = "Ocurrio un error con la operación crear pedido detalle";
                        $db->query("DELETE FROM `all_pedidos_c` WHERE (`rowid` = '" . $last_id . "');");
                    }
                } else {
                    $response->errorAlert = "Ocurrio un error con la operación crear pedido";
                }
            }

        }
        $response->success = "ok";
        $response->send();
        break;

    case "PedidosList":
        $response = new Response();
        $db = new db(SERVER_NAME);
        $User = new User($db);
        $token = $requestData["token"];
        $idUser = $User->tokenObject($token)->rowid;

        $start = !isset($requestData["start"]) ? 0 : $requestData["start"];
        $length = 10;
        $limit = " limit $start, $length";
        if (isset($_POST["end_limit"]) && $_POST["end_limit"])
            $limit = "";
        $where = " where ";
        if (isset($_POST["where"])) {
            $where .= "1=1";
        } else
            $where = "";

        $query = " select c.rowid, c.date_cc, c.descripcion,
              case c.estado
                    when 'p' then 'Pendiente' 
                    when 'A' then 'Autorizado '
                    when 'E' then 'Entregado'
                    when 'C' then 'Cancelado'
                end as estado , count(d.rowid) as numero_item , 
             u.nom
             from 
             all_pedidos_c  c
              inner join 
             all_pedidos_d d on d.fk_pc = c.rowid
              inner join 
             all_user u on u.rowid = c.id_users
             group by d.fk_pc " . $limit;
        $fetch = $db->fetchArray($query);
        $response->data = $fetch;
        $response->send();
        break;

    case "PedidosDetalle":
        $response = new Response();
        $db = new db(SERVER_NAME);
        $User = new User($db);
        $token = $requestData["token"];
        $idUser = $User->tokenObject($token)->rowid;
        $idOrden = $requestData["idorden"];
        $query = "SELECT 
                        p.rowid, p.nombre, d.observacion, d.cantidad
                    FROM
                        all_products AS p
                            INNER JOIN
                        all_pedidos_d AS d ON d.fk_producto = p.rowid
                    WHERE
                        d.fk_pc = $idOrden; ";
        $fetch = $db->fetchArray($query);
        $response->data = $fetch;
        $response->send();
        break;


}


die();
?>
