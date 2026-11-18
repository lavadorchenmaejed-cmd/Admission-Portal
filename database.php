<?php
class Database{
    private $conn;

    public function __construct(array $config)
    {
       $this -> conn = new mysqli(
        $config['HOST'],
        $config['USER'],
        $config['PASSWORD'],
        $config['DATABASE']
       );
       if($this -> conn -> connect_error){
        die("DB Connection failed: " . $this -> conn -> connect_error);
       }
    }
    
    public function getConnection(){
        return $this -> conn;
    }
};