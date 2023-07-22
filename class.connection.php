<?php



class db
{

    private $servername = "";
    private $DataBase = "";
    private $username = "";
    private $password = "";

    public function __construct($servername = "", $namedb = "")
    {
        $userName = "root";
        $passName = "";
        $this->DataBase = $namedb;
        if (empty($servername) || $servername === "localhost") {
            //Localhost
            $this->servername = $servername;
            $this->username = $userName;
            $this->password = $passName;
        } else {
            //Remoto
        }
    }

    public function tableInsertRow($name, $colunmValues = array())
    {
        try {
            $_colunm = array();
            $_values = array();
            if (count($colunmValues) > 0) {
                foreach ($colunmValues as $value) {
                    $_colunm[] = $value[0];
                    $_values[] = "'$value[1]'";
                }
                $str = "INSERT INTO $name (" . implode(',', $_colunm) . ") VALUES (" . implode(",", $_values) . ")";
                $result = $this->query($str);
                return $result;
            }
            return null;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    public function tableUpdateRow($name, $colunmValues = array(), $id = "")
    {
        $_values = array();
        if (count($colunmValues) > 0) {
            foreach ($colunmValues as $value) {
                $_col = $value[0];
                $_val = $value[1];
                $_values[] = "$_col = '$_val'";
            }
            if (is_numeric($id)) {
                $str = "UPDATE $name SET  " . implode(',', $_values) . " WHERE rowid = $id";
//                print_r($str); die();
                $res = $this->query($str);
                if ($res === 1)
                    return true;
                else
                    return $res;
            } else {
                return false;
            }
        }
        return null;
    }

    public function fetchArray($query = "")
    {
        $db = $this->open();
        $fetch = $db->query($query)->fetch_all(MYSQLI_ASSOC);
        $db->close();
        return $fetch;
    }

    public function Count($tableJoin, $where = "")
    {
        $db = $this->open();
        $str = "select count(*) as count_number $tableJoin $where";
        $object = $db->query($str);
        $db->close();
        if ($object && $object->num_rows > 0) {
            return $object->fetch_object()->count_number;
        } else {
            return 0;
        }
    }

    public function query($query = "")
    {
        $db = $this->open();
        $response = $db->query($query);
        $mysql_error = $db->error;
        $db->close();
        if ($mysql_error)
            return $mysql_error;
        else
            return $response;
    }

    public function quote($params = "")
    {
        $db = $this->open();
        $response = $db->escape_string($params);
        $db->close();
        return $response;
    }

    public function fetchObject($query = "")
    {
        $db = $this->open();
        $obj = new stdClass();
        $response = $db->query($query);
        if ($response && $response->num_rows > 0)
            $obj = $response->fetch_object();
        else
            return false;
        $db->close();
        return $obj;
    }

    private function open()
    {
        $mysql = new mysqli($this->servername, $this->username, $this->password, $this->DataBase, 3306);
        $mysql->set_charset("utf8");
        return $mysql;
    }
}




?>