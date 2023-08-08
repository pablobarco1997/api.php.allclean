<?php


class db
{

    /**
     * PASS REMOTO
     * URL: ec2-52-15-181-14.us-east-2.compute.amazonaws.com
     */


    private $servername = "";
    private $DataBase = "";
    private $username = "";
    private $password = "";

    public function __construct($servername = "")
    {
        $hostAws = 'ec2-18-117-100-224.us-east-2.compute.amazonaws.com';
        $userName = "root";
        $passName = "";
        $this->DataBase = "sch_all_cleaned";
        if ($servername === "localhost" && $_SERVER["SERVER_NAME"] != $hostAws) {
            //Localhost
            $this->servername = "localhost";
            $this->username = $userName;
            $this->password = $passName;
        } else {
            //Remoto
            $this->servername = $hostAws;
            $this->username = "";
            $this->password = "";
        }
    }

    public function tableInsertRow($name, $colunmValues = array(), $mysql_errno = false)
    {
        try {
            $_colunm = array();
            $_values = array();
            if (count($colunmValues) > 0) {
                $db = $this->open();
                foreach ($colunmValues as $value) {
                    $_colunm[] = $value[0];
                    $_values[] = "'$value[1]'";
                }
                $str = "INSERT INTO $name (" . implode(',', $_colunm) . ") VALUES (" . implode(",", $_values) . ")";
                $result = $db->query($str);
                $errno = $db->error; //codigo de error de insercion
                $insert_id = $db->insert_id;
                //$db->close();
                if ($result)
                    return $insert_id;
                else{
                    if($mysql_errno === true)
                        return $errno;
                    else
                        return false;
                }
            }
            return false;
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
        //$db->close();
        return $fetch;
    }

    public function Count($tableJoin, $where = "")
    {
        $db = $this->open();
        $str = "select count(*) as count_number from $tableJoin $where";
        $object = $db->query($str);
        //$db->close();
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
        //$db->close();
        if ($mysql_error)
            return $mysql_error;
        else
            return $response;
    }

    public function quote($params = "")
    {
        $db = $this->open();
        $response = $db->escape_string($params);
        //$db->close();
        return $response;
    }

    public function fetchObject($query = "")
    {
        $db = $this->open();
        $obj = new stdClass();
        $response = $db->query($query);
        if ($response && $response->num_rows > 0)
            $obj = $response->fetch_object();
        else {
            //$db->close();
            return false;
        }
        //$db->close();
        return $obj;
    }

    public function tableInsertRowsMasive($tableName, $data)
    {
        if (empty($data) || !is_array($data)) {
            return false;
        }
        $db = $this->open();
        $columns = implode(', ', array_keys($data[0]));
        $values = array();
        foreach ($data as $row) {
            $rowValues = array_map(function ($value) use ($db) {
                return "'" . mysqli_real_escape_string($db, $value) . "'";
            }, $row);
            $values[] = '(' . implode(', ', $rowValues) . ')';
        }
        $values = implode(', ', $values);
        $query = "INSERT INTO $tableName ($columns) VALUES $values";
        $result = $db->query($query);
        //$db->close();
        return $result;
    }

    public function param_array_empty($data, $key, $cero = false){
        if(is_array($data))
            return isset($data[$key]) ? $data[$key] : ($cero === true ? "0" : "");
        else
            return "";
    }

    private function open()
    {
        $mysql = new mysqli($this->servername, $this->username, $this->password, $this->DataBase, 3306);
        $mysql->set_charset("utf8");
        return $mysql;
    }
}


?>