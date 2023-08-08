<?php


// Permite que cualquier origen (dominio) pueda acceder a los recursos de este servidor.
header("Access-Control-Allow-Origin: *");
// Permite que el cliente pueda incluir los encabezados personalizados en la solicitud.
header("Access-Control-Allow-Headers: *");
// Permite que el cliente pueda realizar solicitudes con los siguientes métodos HTTP.
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header('Content-Type: application/json');

define("SERVER_NAME", "localhost");

require_once "class/class.user.php";
require_once "class/class.connection.php";
require_once "class/class.send.response.php";



function SystemAccess(){
    $response = new Response();
    $db = new db(SERVER_NAME);
    $User = new User($db);
    if($User->system_access_users() === 1){ //acceso denegato
        $response->errorAlert = "Acceso Denegado";
        $response->send();
        die();
    }

}



$requestData = $_POST;


//if (1==1) {
//    $response = new Response();
//    $response->errorAlert = "Error de acceso";
//    $response->send();
//    return;
//}

SystemAccess(); 

if (!isset($requestData['accion'])) {
    $response = new Response();
    $response->errorAlert = "No se proporcionó la acción requerida.";
    $response->send();
    return;
}


if ($requestData["accion"] != "autentication") {
    $db = new db(SERVER_NAME);
    $User = new User($db);
    if (!$User->validarToken($requestData["token"])) {
        $response->errorAlert = "Token Invalido cierre session y vuelva a iniciar";
        $response->send();
        return;
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
        $is_admin = $User->tokenObject($token)->tipo;
        $descripcion = $requestData["descripcion"];
        $idOrden = $db->param_array_empty($requestData, "idorden");
        $productos = isset($requestData["productos"]) ? $requestData["productos"] : [];

        if(!empty($idOrden) && $idOrden != 0){
            if($User->pedido_status($idOrden) === "E"){
                $response->errorAlert = "Situacción del pedido Entregado. No puede realizar esta operación";
                $response->send();
                die();
            }
        }

        //se valida que exista los productos
        if (count($productos) == 0) {
            $response->errorAlert = "No se detecto ningun producto. Comprube la informacion antes de continuar";
            $response->send();
            die();
            return;
        }

        //se valida el stock de cada producto
        $idprod = [];
        $cant_pedido = []; //guardo la cantidad de cada producto del pedido por la key rowid
        foreach ($productos as $value) {
            $idprod[] = $value["rowid"];
            $cant_pedido[$value["rowid"]] = (double)$value["cantidad"];
        }
        $acuValid = 0; //valida que contenga stock
        $acuStockNegativo = 0;  // valida que el stock no pase a negativo
        $nomProductos = [];
        $st = $db->fetchArray("select rowid, cantidad, nombre  from all_products where rowid in(" . implode(",", $idprod) . ")");
        foreach ($st as $v => $value) {
            if ((double)$value["cantidad"] == 0)
                $acuValid++;
            if ((double)$value["cantidad"] > 0) {
                $calculoStock = (double)$value["cantidad"] - (double)$cant_pedido[$value["rowid"]];
                if ($calculoStock < 0) {//stock en negativo
                    $acuStockNegativo++;
                    $nomProductos[] = $value["nombre"];
                }
            }

        }

        //valida que el stock no sea cero
        if ($acuValid > 0) {
            $response->errorAlert = "No puede realizar el pedido se detecto productos con stock en 0";
            $response->send();
            die();
            return;
        }

        //valida que el stock no quede en negativo despues de un pedido
        if ($acuStockNegativo > 0) {
            $response->errorAlert = "No puede realizar el pedido se detecto el stock en negativo. Productos ".implode(" - ", $nomProductos);
            $response->send();
            die();
            return;
        }

        //print_r($idUser); die();
        if ($idUser) {
            $situacion = $requestData["situacion"];

            if ((int)$idOrden > 0) { //actualizar pedido

                $estado = $db->fetchObject("select estado from all_pedidos_c where rowid = $idOrden")->estado;
                if ($estado === "E") { //pedido entregado
                    $response->errorAlert = "Este pedido ya se encuentra entregado";
                    $response->send();
                    return;
                }

                $pedidos_c = $db->tableUpdateRow("all_pedidos_c", array(
                    array("descripcion", $descripcion),
                    array("estado", $situacion)
                ), $idOrden);

                if ($idOrden) {
                    $dele = $db->query("DELETE  from all_pedidos_d where rowid > 0 and fk_pc = $idOrden");
                    if ($dele) {
                        $columns = [];
                        foreach ($productos as $key => $value) {
                            $columns[] = array("observacion" => $value["observacion"], "fk_pc" => $idOrden, "fk_producto" => (int)$value["rowid"], "cantidad" => (double)$value["cantidad"]);
                        }

                        //se crea el detalle del pedido
                        $pedidos_d = $db->tableInsertRowsMasive("all_pedidos_d", $columns);
                        if (!$pedidos_d) { //ok creacion detalle
                            $response->errorAlert = "Ocurrio un error con la operación actualización pedido detalle";
                            //$db->query("DELETE FROM `all_pedidos_c` WHERE (`rowid` = '" . $idOrden . "');");
                        } else {
                            //actualizar stock de producto
                            if ($situacion == "E") {
                                $str = "update 
                                        all_products as p  
                                    set 
                                            p.cantidad = (round(p.cantidad, 2) - ifnull((select d.cantidad  from all_pedidos_d as d where d.fk_producto = p.rowid and  d.fk_pc = $idOrden), 0)) 
                                    where 
                                      p.rowid > 0 and 
                                      p.rowid in(((select a.fk_producto from all_pedidos_d as a where a.fk_pc = $idOrden)))";
                                $db->query($str);
                            }
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
             group by d.fk_pc order by c.rowid desc   " . $limit;
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

    case "ListaUsuarios":
        $response = new Response();
        $db = new db(SERVER_NAME);
        $User = new User($db);
        $token = $requestData["token"];
        $idUser = $User->tokenObject($token)->rowid;
        $query = "select * , FLOOR(RAND() * 6)  as avatar from all_user";
        $fetch = $db->fetchArray($query);
        $response->data = $fetch;
        $response->send();
        break;


    case "createUpdateUsers":

        $response = new Response();
        $db = new db(SERVER_NAME);
        $User = new User($db);
        $token = $requestData["token"];
        $idUser = $User->tokenObject($token)->rowid;
        $rowid_users = isset($requestData["rowid"]) ? $requestData["rowid"] : "";

        //asignos las columnas values
        $columns = array(
            array("nom", $db->param_array_empty($requestData, "nom")),
            array("email", $db->param_array_empty($requestData, "email")),
            array("pass", $db->param_array_empty($requestData, "password")),
            array("users", $db->param_array_empty($requestData, "usuario")),
            array("tipo", $db->param_array_empty($requestData, "tipo")),
        );

        if ($rowid_users > 0) {
            //update
            $value = $db->tableUpdateRow("all_user", $columns, $rowid_users);
            if ($value) {
                $response->success = "ok";
            } else {
                $response->errorAlert = "Ocurrio un error con la operación, verfique los datos e intentelo nuevamente";
            }
        } else {
            //create
            $value = $db->tableInsertRow("all_user", $columns, true);
            if ($value > 0) {
                //seccuse
                $response->success = "ok";
            } else {
                //error
                //se valida si es duplicado el string
                $posDuplicate = strpos($value, "Duplicate");

                if ($posDuplicate !== false) //es duplicate
                {
                    $response->errorAlert = "duplicidad de registro. usuario ya se encuentra registrado seleccione otro";
                } else {
                    $response->errorAlert = "ocurrio un error con la operación";
                }
                $response->errorAlert = $value;
            }
        }

        $response->send();
        break;

}


die();
?>
