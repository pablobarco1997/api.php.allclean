

<?php

class Response
{
    public $success = "";
    public $error = "";
    public $data = array();
    public $errorAlert = "";


    public function send()
    {
        header('Content-Type: application/json');
        echo json_encode(array(
            'success' => $this->success,
            'error' => $this->error,
            'result' => $this->data,
            'errorAlert' => $this->errorAlert
        ));
        die();
    }
}

?>

