<?php
class Profile {
    private $database;
    private $table = "applicants";
    public function __construct(mysqli $database) {
        $this->database = $database;
    }
    public function create(
    $Applicant_name, $user_Id, $Father_name, $Mother_name, $birthdate, $gender, 
    $contact_number, $address, $religion, $nationality, $photoPath, $student_type, $program_id
) {
    // Prepare statement
    $stmt = $this->database->prepare("INSERT INTO {$this->table}  
        (Applicant_name, user_Id, Father_name, Mother_name, birthdate, Gender, 
        contact_number, address, religion, nationality, photo, student_type, program_id) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $this->database->error);
    }
   
   $stmt->bind_param("sissssssssssi", 
    $Applicant_name,   
    $user_Id,          
    $Father_name,      
    $Mother_name,      
    $birthdate,        
    $gender,           
    $contact_number,   
    $address,          
    $religion,         
    $nationality,      
    $photoPath,        
    $student_type,     
    $program_id        
);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    return $this->database->insert_id;
}
    // fetch applicant by id
    public function findById($id) {
        $stmt = $this->database->prepare("SELECT * FROM {$this->table} WHERE applicant_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
    // fetch applicant by user id
    public function findByUserId($user_Id) {
        $stmt = $this->database->prepare("SELECT * FROM {$this->table} WHERE user_Id = ? LIMIT 1");
        $stmt->bind_param("i", $user_Id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
  public function findAll() {
    $result = $this->database->query("SELECT * FROM {$this->table}");
    return $result->fetch_all(MYSQLI_ASSOC);
}
    public function update($Applicant_name, $Father_name, $Mother_name, $birthdate, $gender, 
            $contact_number, $address, $religion, $nationality, $id){
                $stmt = $this->database->prepare("UPDATE {$this->table} SET Applicant_name = ?, Father_name = ?, Mother_name = ?, birthdate = ?, 				Gender = ?, contact_number = ?, address = ?, Religion = ?, Nationality = ? WHERE applicant_id = ?");
                $stmt -> bind_param('sssssssssi', $Applicant_name, $Father_name, $Mother_name, $birthdate, $gender, $contact_number, $address, 					$religion, $nationality, $id);
                return $stmt -> execute();
             
        }
    
    public function findByContactNumber($contact_number) {
    $stmt = $this->database->prepare("SELECT * FROM {$this->table} WHERE contact_number = ? LIMIT 1");
    $stmt->bind_param("s", $contact_number);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}
   
}